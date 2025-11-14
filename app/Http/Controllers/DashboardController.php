<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Estimate;
use App\Models\Partner;
use App\Models\LocalInvoice;
use App\Services\MoneyForwardApiService;
use App\Services\MoneyForwardQuoteSynchronizer;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request, MoneyForwardApiService $apiService)
    {
        $user = Auth::user();

        $partnerSyncFlash = [
            'status' => $request->session()->pull('mf_partner_sync_status'),
            'message' => $request->session()->pull('mf_partner_sync_message'),
        ];

        $partnerSyncStatus = null;
        if ($request->session()->pull('mf_skip_partner_auto_sync', false)) {
            $partnerSyncStatus = ['status' => 'skipped'];
        } else {
            $partnerSyncStatus = $this->attemptAutoPartnerSync($request, $apiService);
            if ($partnerSyncStatus === 'redirect') {
                return redirect()->route('partners.auth.start');
            }
        }

        if (
            empty($partnerSyncFlash['message'])
            && is_array($partnerSyncStatus)
            && isset($partnerSyncStatus['message'])
            && ($partnerSyncStatus['status'] ?? null) !== 'skipped'
        ) {
            $partnerSyncFlash = [
                'status' => $partnerSyncStatus['status'] ?? null,
                'message' => $partnerSyncStatus['message'],
            ];
        }

        // Show tasks purely based on approval_flow (未承認が存在するもの)。status には依存しない。
        $estimatesWithFlow = Estimate::whereNotNull('approval_flow')->get();
        $toDoEstimates = [];

        foreach ($estimatesWithFlow as $estimate) {
            $approvalFlow = is_array($estimate->approval_flow)
                ? $estimate->approval_flow
                : json_decode($estimate->approval_flow, true);
            if (!is_array($approvalFlow) || empty($approvalFlow)) {
                continue; // Skip if approval_flow is not a valid array or empty
            }

            $isCurrentUserNextApprover = false;
            $waitingForApproverName = null;
            $isCurrentUserInFlow = false;

            // Check if current user is in the flow at all
            foreach ($approvalFlow as $approver) {
                $approverIdInFlow = $approver['id'] ?? null;
                $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
                $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
                $userExt = (string)($user->external_user_id ?? '');
                $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);

                if ($matchesLocalId || $matchesExternalId) {
                    $isCurrentUserInFlow = true;
                    break;
                }
            }

            $currentStepIndex = -1;
            foreach ($approvalFlow as $idx => $approver) {
                $status = $approver['status'] ?? (empty($approver['approved_at']) ? 'pending' : 'approved');
                if ($status !== 'approved' && $status !== 'rejected') {
                    $currentStepIndex = $idx;
                    break;
                }
            }

            if ($currentStepIndex !== -1) {
                $currentApprover = $approvalFlow[$currentStepIndex];

                $approverIdInFlow = $currentApprover['id'] ?? null;
                $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
                $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
                $userExt = (string)($user->external_user_id ?? '');
                $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);
                if ($matchesLocalId || $matchesExternalId) {
                    $isCurrentUserNextApprover = true;
                }

                if (!$isCurrentUserNextApprover) {
                    $waitingForApproverName = $currentApprover['name'];
                }
            } else {
                // All steps approved → ダッシュボード対象外
                continue;
            }

            $status_for_dashboard = '';
            if ($isCurrentUserNextApprover) {
                $status_for_dashboard = '確認して承認';
            } elseif ($waitingForApproverName) {
                $status_for_dashboard = "{$waitingForApproverName}さんの承認待ち";
            } else {
                // Should not happen if there is a current step, but as a fallback
                continue;
            }

            $toDoEstimates[] = [
                'id' => $estimate->id,
                'title' => $estimate->title,
                'issue_date' => $estimate->issue_date,
                'status_for_dashboard' => $status_for_dashboard,
                'estimate_number' => $estimate->estimate_number,
                'estimate' => $estimate->toArray(),
                'is_current_user_in_flow' => $isCurrentUserInFlow,
            ];
        }

        usort($toDoEstimates, function($a, $b) {
            return strtotime($b['issue_date']) - strtotime($a['issue_date']);
        });

        $metrics = $this->buildDashboardMetrics();
        $salesRanking = $this->buildSalesRanking(
            Carbon::parse($metrics['periods']['current']['start']),
            Carbon::parse($metrics['periods']['current']['end'])
        );

        return Inertia::render('Dashboard', [
            'toDoEstimates' => $toDoEstimates,
            'partnerSyncStatus' => $partnerSyncStatus,
            'partnerSyncFlash' => $partnerSyncFlash,
            'dashboardMetrics' => $metrics,
            'salesRanking' => $salesRanking,
        ]);
    }

    public function syncPartners(Request $request, MoneyForwardApiService $apiService)
    {
        if ($token = $apiService->getValidAccessToken(null, ['mfc/invoice/data.read', 'mfc/invoice/data.write'])) {
            return $this->doPartnerSync($token, $apiService);
        } else {
            $request->session()->put('mf_redirect_action', 'sync_partners');
            $request->session()->put('mf_partners_redirect_back', url()->previous() ?: route('dashboard'));
            return redirect()->route('partners.auth.start');
        }
    }

    public function redirectToAuthForPartners(Request $request)
    {
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => env('MONEY_FORWARD_PARTNER_AUTH_REDIRECT_URI', route('partners.auth.callback')),
            'scope' => 'mfc/invoice/data.read mfc/invoice/data.write',
        ]);
        return \Inertia\Inertia::location($authUrl);
    }

    public function handlePartnersCallback(Request $request, MoneyForwardApiService $apiService)
    {
        if (!$request->has('code')) {
            return redirect()->route('dashboard')->with('error', 'Authorization failed.');
        }

        $tokenData = $apiService->getAccessTokenFromCode($request->code, env('MONEY_FORWARD_PARTNER_AUTH_REDIRECT_URI', route('partners.auth.callback')));
        if (!$tokenData) {
            return redirect()->route('dashboard')->with('error', 'Failed to get access token.');
        }

        $apiService->storeToken($tokenData, Auth::id());
        $token = $tokenData['access_token'];

        return $this->doPartnerSync($token, $apiService);
    }

    private function doPartnerSync(string $token, MoneyForwardApiService $apiService)
    {
        $result = $this->performPartnerSync($token, $apiService);

        session()->flash('mf_skip_partner_auto_sync', true);
        session()->flash('mf_partner_sync_status', $result['status'] ?? null);
        session()->flash('mf_partner_sync_message', $result['message'] ?? '同期結果が不明です。');

        $redirectBack = session()->pull('mf_partners_redirect_back');
        $redirectResponse = $redirectBack ? redirect()->to($redirectBack) : redirect()->route('dashboard');

        return $redirectResponse;
    }

    private function attemptAutoPartnerSync(Request $request, MoneyForwardApiService $apiService): array|string|null
    {
        $token = $apiService->getValidAccessToken(null, ['mfc/invoice/data.read', 'mfc/invoice/data.write']);
        if (!$token) {
            $request->session()->put('mf_redirect_action', 'sync_partners');
            $request->session()->put('mf_partners_redirect_back', url()->full());
            return 'redirect';
        }

        return $this->performPartnerSync($token, $apiService);
    }

    private function performPartnerSync(string $token, MoneyForwardApiService $apiService): array
    {
        if (!Schema::hasTable('partners')) {
            return [
                'status' => 'error',
                'message' => 'partners table does not exist. Please run `php artisan migrate`.',
            ];
        }

        $partners = $apiService->fetchAllPartners($token);
        if (!is_array($partners)) {
            return [
                'status' => 'error',
                'message' => 'Could not fetch partners. Please check permissions (scope) and settings.',
            ];
        }

        $count = 0;
        foreach ($partners as $p) {
            $mfId = $p['id'] ?? null;
            if (!$mfId) { continue; }

            $detail = $apiService->fetchPartnerDetail($mfId, $token);
            $merged = is_array($detail) ? array_merge($detail, $p) : $p;

            Partner::updateOrCreate(
                ['mf_partner_id' => $mfId],
                [
                    'code' => $merged['code'] ?? null,
                    'name' => $merged['name'] ?? null,
                    'payload' => $merged,
                ]
            );
            $count++;
        }

        return [
            'status' => 'success',
            'message' => "{$count}件の顧客情報を更新しました",
            'count' => $count,
        ];
    }

    private function buildDashboardMetrics(): array
    {
        $timezone = config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo'));
        $now = Carbon::now($timezone);

        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $previousStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();

        $currentEstimatesTotal = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->whereBetween('issue_date', [$currentStart->toDateString(), $currentEnd->toDateString()])
            ->sum('total_amount');

        $previousEstimatesTotal = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->whereBetween('issue_date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('total_amount');

        $currentInvoices = Schema::hasTable('local_invoices')
            ? LocalInvoice::query()
                ->whereBetween('billing_date', [$currentStart->toDateString(), $currentEnd->toDateString()])
                ->get()
            : collect();

        $previousInvoices = Schema::hasTable('local_invoices')
            ? LocalInvoice::query()
                ->whereBetween('billing_date', [$previousStart->toDateString(), $previousEnd->toDateString()])
                ->get()
            : collect();

        $currentGrossProfit = $this->sumInvoiceGrossProfit($currentInvoices);
        $previousGrossProfit = $this->sumInvoiceGrossProfit($previousInvoices);

        $currentSalesTotal = $currentInvoices->sum('total_amount');
        $previousSalesTotal = $previousInvoices->sum('total_amount');

        return [
            'periods' => [
                'current' => [
                    'label' => $currentStart->format('Y年n月'),
                    'start' => $currentStart->toDateString(),
                    'end' => $currentEnd->toDateString(),
                ],
                'previous' => [
                    'label' => $previousStart->format('Y年n月'),
                    'start' => $previousStart->toDateString(),
                    'end' => $previousEnd->toDateString(),
                ],
            ],
            'estimates' => [
                'current' => (float) $currentEstimatesTotal,
                'previous' => (float) $previousEstimatesTotal,
            ],
            'gross_profit' => [
                'current' => (float) $currentGrossProfit,
                'previous' => (float) $previousGrossProfit,
            ],
            'sales' => [
                'current' => (float) $currentSalesTotal,
                'previous' => (float) $previousSalesTotal,
            ],
        ];
    }

    private function sumInvoiceGrossProfit(Collection $invoices): float
    {
        return $invoices->reduce(function ($carry, $invoice) {
            $items = $invoice->items ?? [];
            if (!is_array($items)) {
                return $carry;
            }
            $profit = 0.0;
            foreach ($items as $item) {
                $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 1));
                if ($qty === 0.0) {
                    $qty = 1.0;
                }
                $price = (float) (data_get($item, 'price') ?? data_get($item, 'unit_price', 0));
                $cost = (float) (data_get($item, 'cost') ?? data_get($item, 'unit_cost', 0));
                $profit += ($price - $cost) * $qty;
            }

            return $carry + $profit;
        }, 0.0);
    }

    private function buildSalesRanking(Carbon $periodStart, Carbon $periodEnd): array
    {
        if (!Schema::hasTable('local_invoices')) {
            return [];
        }

        $rows = LocalInvoice::query()
            ->selectRaw('COALESCE(customer_name, "不明") as customer_name, SUM(total_amount) as total_amount')
            ->whereBetween('billing_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->groupBy('customer_name')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        return $rows->values()->map(function ($row, $index) {
            return [
                'rank' => $index + 1,
                'customer_name' => $row->customer_name,
                'amount' => (float) $row->total_amount,
            ];
        })->toArray();
    }
}
