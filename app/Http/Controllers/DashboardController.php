<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Estimate;
use App\Models\Partner;
use App\Models\Billing;
use App\Services\MoneyForwardApiService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
                $userExt = (string)($user->external_user_id ?? '');

                if ($userExt !== '') {
                    if ($approverIdInFlowStr !== '' && $approverIdInFlowStr === $userExt) {
                        $isCurrentUserNextApprover = true;
                    }
                } else {
                    if (is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id) {
                        $isCurrentUserNextApprover = true;
                    }
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
        $horizonStart = $previousStart->copy();
        $horizonEnd = $currentStart->copy()->addMonthsNoOverflow(11)->endOfMonth();
        $monthKeys = collect(range(-1, 11))
            ->map(fn (int $offset) => $currentStart->copy()->addMonthsNoOverflow($offset)->startOfMonth()->toDateString())
            ->values()
            ->all();
        $monthlyCapacity = (float) config('app.monthly_capacity_person_days', 160);
        $productLookup = $this->buildProductLookup();
        $monthly = collect($monthKeys)->mapWithKeys(function (string $monthKey) use ($timezone) {
            $month = Carbon::parse($monthKey, $timezone);
            return [
                $monthKey => [
                    'month_key' => $monthKey,
                    'month_label' => $month->format('Y年n月'),
                    'budget_sales' => 0.0,
                    'budget_gross_profit' => 0.0,
                    'budget_purchase' => 0.0,
                    'budget_purchase_material' => 0.0,
                    'budget_purchase_labor' => 0.0,
                    'actual_sales' => 0.0,
                    'actual_gross_profit' => 0.0,
                    'actual_purchase' => 0.0,
                    'actual_purchase_material' => 0.0,
                    'actual_purchase_labor' => 0.0,
                    'budget_count' => 0,
                    'actual_count' => 0,
                    'budget_effort' => 0.0,
                    'budget_purchase_outflow' => 0.0,
                    'actual_purchase_outflow' => 0.0,
                    'budget_collection_inflow' => 0.0,
                    'actual_collection_inflow' => 0.0,
                ],
            ];
        });

        $estimates = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->whereNotIn('status', ['rejected'])
            ->get([
                'id',
                'status',
                'xero_project_id',
                'xero_project_name',
                'issue_date',
                'due_date',
                'delivery_date',
                'total_amount',
                'items',
                'is_order_confirmed',
            ]);

        foreach ($estimates as $estimate) {
            $recognizedAt = $this->resolveRecognitionDate($estimate, $timezone);
            if (!$recognizedAt) {
                continue;
            }

            $monthKey = $recognizedAt->copy()->startOfMonth()->toDateString();
            if ($monthKey < $horizonStart->toDateString() || $monthKey > $horizonEnd->toDateString()) {
                continue;
            }

            $sales = (float) ($estimate->total_amount ?? 0);
            $purchaseBreakdown = $this->sumEstimatePurchaseBreakdown($estimate->items ?? [], $productLookup);
            $purchase = (float) ($purchaseBreakdown['total'] ?? 0.0);
            $materialPurchase = (float) ($purchaseBreakdown['material'] ?? 0.0);
            $laborPurchase = (float) ($purchaseBreakdown['labor'] ?? 0.0);
            $effort = $this->sumEstimateEffort($estimate->items ?? [], $productLookup);
            $grossProfit = $sales - $purchase;

            $row = $monthly->get($monthKey);
            if (!is_array($row)) {
                continue;
            }

            $row['budget_sales'] += $sales;
            $row['budget_purchase'] += $purchase;
            $row['budget_purchase_material'] += $materialPurchase;
            $row['budget_purchase_labor'] += $laborPurchase;
            $row['budget_gross_profit'] += $grossProfit;
            $row['budget_effort'] += $effort;
            $row['budget_count'] += 1;

            if ((bool) $estimate->is_order_confirmed === true) {
                $row['actual_sales'] += $sales;
                $row['actual_purchase'] += $purchase;
                $row['actual_purchase_material'] += $materialPurchase;
                $row['actual_purchase_labor'] += $laborPurchase;
                $row['actual_gross_profit'] += $grossProfit;
                $row['actual_count'] += 1;
            }

            $purchaseAt = $this->resolvePurchaseDate($estimate, $timezone);
            $purchaseMonthKey = $purchaseAt?->copy()->startOfMonth()->toDateString();
            if ($purchaseMonthKey && $monthly->has($purchaseMonthKey)) {
                $purchaseRow = $monthly->get($purchaseMonthKey);
                $purchaseRow['budget_purchase_outflow'] += $purchase;
                if ((bool) $estimate->is_order_confirmed === true) {
                    $purchaseRow['actual_purchase_outflow'] += $purchase;
                }
                $monthly->put($purchaseMonthKey, $purchaseRow);
            }

            $collectionAt = $this->resolveCollectionDate($estimate, $timezone);
            $collectionMonthKey = $collectionAt?->copy()->startOfMonth()->toDateString();
            if ($collectionMonthKey && $monthly->has($collectionMonthKey)) {
                $collectionRow = $monthly->get($collectionMonthKey);
                $collectionRow['budget_collection_inflow'] += $sales;
                if ((bool) $estimate->is_order_confirmed === true) {
                    $collectionRow['actual_collection_inflow'] += $sales;
                }
                $monthly->put($collectionMonthKey, $collectionRow);
            }

            $monthly->put($monthKey, $row);
        }

        if (Schema::hasTable('billings')) {
            $paidBillings = Billing::query()
                ->whereBetween('due_date', [$horizonStart->toDateString(), $horizonEnd->toDateString()])
                ->get(['id', 'due_date', 'payment_status', 'total_price', 'subtotal_price']);

            foreach ($paidBillings as $billing) {
                if (!$this->isPaidStatus((string) ($billing->payment_status ?? ''))) {
                    continue;
                }
                if (!$billing->due_date) {
                    continue;
                }
                $dueMonthKey = Carbon::parse($billing->due_date, $timezone)->startOfMonth()->toDateString();
                if (!$monthly->has($dueMonthKey)) {
                    continue;
                }
                $row = $monthly->get($dueMonthKey);
                $row['actual_collection_inflow'] += $this->resolveBillingAmount($billing);
                $monthly->put($dueMonthKey, $row);
            }
        }

        $currentRow = $monthly->get($currentStart->toDateString(), []);
        $previousRow = $monthly->get($previousStart->toDateString(), [
            'budget_sales' => 0.0,
            'budget_gross_profit' => 0.0,
            'budget_purchase' => 0.0,
            'actual_sales' => 0.0,
            'actual_gross_profit' => 0.0,
            'actual_purchase' => 0.0,
            'budget_purchase_material' => 0.0,
            'budget_purchase_labor' => 0.0,
            'actual_purchase_material' => 0.0,
            'actual_purchase_labor' => 0.0,
            'budget_count' => 0,
            'actual_count' => 0,
            'budget_effort' => 0.0,
            'budget_purchase_outflow' => 0.0,
            'actual_purchase_outflow' => 0.0,
            'budget_collection_inflow' => 0.0,
            'actual_collection_inflow' => 0.0,
        ]);

        $forecastRows = $monthly->values()->map(function (array $row) {
            return [
                ...$row,
                'sales_variance' => (float) ($row['actual_sales'] - $row['budget_sales']),
                'gross_profit_variance' => (float) ($row['actual_gross_profit'] - $row['budget_gross_profit']),
                'purchase_variance' => (float) ($row['actual_purchase'] - $row['budget_purchase']),
                'budget_net_cash' => (float) ($row['budget_collection_inflow'] - $row['budget_purchase_outflow']),
                'actual_net_cash' => (float) ($row['actual_collection_inflow'] - $row['actual_purchase_outflow']),
            ];
        })->filter(function (array $row) use ($currentStart) {
            return $row['month_key'] >= $currentStart->toDateString();
        })->values()->toArray();

        $currentBudgetEffort = (float) ($currentRow['budget_effort'] ?? 0);
        $previousBudgetEffort = (float) ($previousRow['budget_effort'] ?? 0);

        return [
            'basis' => [
                'budget' => '見積書（Estimate）',
                'actual' => '注文書（受注確定済み）',
                'recognition' => '納期ベース',
                'recognition_fallback' => '納期未設定時は見積期限日、さらに未設定時は見積日を使用',
                'effort' => '計画工数（見積ベース）',
            ],
            'capacity' => [
                'monthly_person_days' => $monthlyCapacity,
            ],
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
            'budget' => [
                'current' => [
                    'sales' => (float) ($currentRow['budget_sales'] ?? 0),
                    'gross_profit' => (float) ($currentRow['budget_gross_profit'] ?? 0),
                    'purchase' => (float) ($currentRow['budget_purchase'] ?? 0),
                    'purchase_material' => (float) ($currentRow['budget_purchase_material'] ?? 0),
                    'purchase_labor' => (float) ($currentRow['budget_purchase_labor'] ?? 0),
                    'effort' => $currentBudgetEffort,
                    'utilization_rate' => $this->calculateRate($currentBudgetEffort, $monthlyCapacity),
                    'productivity' => $this->calculateProductivity((float) ($currentRow['budget_gross_profit'] ?? 0), $currentBudgetEffort),
                    'count' => (int) ($currentRow['budget_count'] ?? 0),
                ],
                'previous' => [
                    'sales' => (float) ($previousRow['budget_sales'] ?? 0),
                    'gross_profit' => (float) ($previousRow['budget_gross_profit'] ?? 0),
                    'purchase' => (float) ($previousRow['budget_purchase'] ?? 0),
                    'purchase_material' => (float) ($previousRow['budget_purchase_material'] ?? 0),
                    'purchase_labor' => (float) ($previousRow['budget_purchase_labor'] ?? 0),
                    'effort' => $previousBudgetEffort,
                    'utilization_rate' => $this->calculateRate($previousBudgetEffort, $monthlyCapacity),
                    'productivity' => $this->calculateProductivity((float) ($previousRow['budget_gross_profit'] ?? 0), $previousBudgetEffort),
                    'count' => (int) ($previousRow['budget_count'] ?? 0),
                ],
            ],
            'actual' => [
                'current' => [
                    'sales' => (float) ($currentRow['actual_sales'] ?? 0),
                    'gross_profit' => (float) ($currentRow['actual_gross_profit'] ?? 0),
                    'purchase' => (float) ($currentRow['actual_purchase'] ?? 0),
                    'purchase_material' => (float) ($currentRow['actual_purchase_material'] ?? 0),
                    'purchase_labor' => (float) ($currentRow['actual_purchase_labor'] ?? 0),
                    'count' => (int) ($currentRow['actual_count'] ?? 0),
                ],
                'previous' => [
                    'sales' => (float) ($previousRow['actual_sales'] ?? 0),
                    'gross_profit' => (float) ($previousRow['actual_gross_profit'] ?? 0),
                    'purchase' => (float) ($previousRow['actual_purchase'] ?? 0),
                    'purchase_material' => (float) ($previousRow['actual_purchase_material'] ?? 0),
                    'purchase_labor' => (float) ($previousRow['actual_purchase_labor'] ?? 0),
                    'count' => (int) ($previousRow['actual_count'] ?? 0),
                ],
            ],
            'effort' => [
                'source' => [
                    'type' => 'plan_only',
                    'label' => '日報未連携のため計画工数（見積ベース）のみ表示',
                ],
                'current' => [
                    'capacity' => $monthlyCapacity,
                    'planned' => $currentBudgetEffort,
                    'planned_remaining' => $monthlyCapacity - $currentBudgetEffort,
                    'planned_fill_rate' => $this->calculateRate($currentBudgetEffort, $monthlyCapacity),
                ],
                'previous' => [
                    'capacity' => $monthlyCapacity,
                    'planned' => $previousBudgetEffort,
                    'planned_remaining' => $monthlyCapacity - $previousBudgetEffort,
                    'planned_fill_rate' => $this->calculateRate($previousBudgetEffort, $monthlyCapacity),
                ],
            ],
            'cash_flow' => [
                'current' => [
                    'purchase_outflow_budget' => (float) ($currentRow['budget_purchase_outflow'] ?? 0),
                    'purchase_outflow_actual' => (float) ($currentRow['actual_purchase_outflow'] ?? 0),
                    'collection_inflow_budget' => (float) ($currentRow['budget_collection_inflow'] ?? 0),
                    'collection_inflow_actual' => (float) ($currentRow['actual_collection_inflow'] ?? 0),
                    'net_budget' => (float) (($currentRow['budget_collection_inflow'] ?? 0) - ($currentRow['budget_purchase_outflow'] ?? 0)),
                    'net_actual' => (float) (($currentRow['actual_collection_inflow'] ?? 0) - ($currentRow['actual_purchase_outflow'] ?? 0)),
                ],
                'previous' => [
                    'purchase_outflow_budget' => (float) ($previousRow['budget_purchase_outflow'] ?? 0),
                    'purchase_outflow_actual' => (float) ($previousRow['actual_purchase_outflow'] ?? 0),
                    'collection_inflow_budget' => (float) ($previousRow['budget_collection_inflow'] ?? 0),
                    'collection_inflow_actual' => (float) ($previousRow['actual_collection_inflow'] ?? 0),
                    'net_budget' => (float) (($previousRow['budget_collection_inflow'] ?? 0) - ($previousRow['budget_purchase_outflow'] ?? 0)),
                    'net_actual' => (float) (($previousRow['actual_collection_inflow'] ?? 0) - ($previousRow['actual_purchase_outflow'] ?? 0)),
                ],
            ],
            'forecast' => [
                'start' => $currentStart->toDateString(),
                'end' => $horizonEnd->toDateString(),
                'months' => $forecastRows,
            ],
        ];
    }
    private function resolveRecognitionDate(Estimate $estimate, string $timezone): ?Carbon
    {
        $recognizedAt = $estimate->delivery_date
            ?? $estimate->due_date
            ?? $estimate->issue_date;

        if (!$recognizedAt) {
            return null;
        }

        return Carbon::parse($recognizedAt, $timezone);
    }

    private function resolvePurchaseDate(Estimate $estimate, string $timezone): ?Carbon
    {
        $purchaseAt = $estimate->issue_date
            ?? $estimate->due_date
            ?? $estimate->delivery_date;

        if (!$purchaseAt) {
            return null;
        }

        return Carbon::parse($purchaseAt, $timezone);
    }

    private function resolveCollectionDate(Estimate $estimate, string $timezone): ?Carbon
    {
        $collectionAt = $estimate->due_date;
        if (!$collectionAt) {
            $recognizedAt = $this->resolveRecognitionDate($estimate, $timezone);
            if (!$recognizedAt) {
                return null;
            }
            return $recognizedAt->copy()->addMonthNoOverflow();
        }

        return Carbon::parse($collectionAt, $timezone);
    }

    private function sumEstimatePurchaseBreakdown($items, array $productLookup): array
    {
        if (!is_array($items)) {
            return ['material' => 0.0, 'labor' => 0.0, 'total' => 0.0];
        }

        $material = 0.0;
        $labor = 0.0;
        $defaultLaborCostPerPersonDay = (float) config('app.labor_cost_per_person_day', 0.0);

        foreach ($items as $item) {
            $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 1));
            if ($qty === 0.0) {
                $qty = 1.0;
            }
            $unitCost = (float) (data_get($item, 'cost') ?? data_get($item, 'unit_cost', 0));
            $lineCost = $unitCost * $qty;

            if ($this->isEffortItem($item, $productLookup)) {
                if ($lineCost <= 0 && $defaultLaborCostPerPersonDay > 0) {
                    $personDays = $this->toPersonDays($qty, (string) (data_get($item, 'unit') ?? ''));
                    $lineCost = $personDays * $defaultLaborCostPerPersonDay;
                }
                $labor += $lineCost;
            } else {
                $material += $lineCost;
            }
        }

        return [
            'material' => $material,
            'labor' => $labor,
            'total' => $material + $labor,
        ];
    }

    private function sumEstimateEffort($items, array $productLookup): float
    {
        if (!is_array($items)) {
            return 0.0;
        }

        $effort = 0.0;
        foreach ($items as $item) {
            if ($this->shouldExcludeEffortItem($item, $productLookup)) {
                continue;
            }

            $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 0));
            if ($qty > 0) {
                $effort += $this->toPersonDays($qty, (string) (data_get($item, 'unit') ?? ''));
            }
        }

        return $effort;
    }

    private function shouldExcludeEffortItem($item, array $productLookup): bool
    {
        $product = $this->resolveProductForItem($item, $productLookup);
        if ($product && (($product['business_division'] ?? null) === 'first_business')) {
            return true;
        }

        $unit = mb_strtolower((string) (data_get($item, 'unit') ?? ''));
        return !($unit === '' || str_contains($unit, '人日') || str_contains($unit, '人月'));
    }

    private function isEffortItem($item, array $productLookup): bool
    {
        return !$this->shouldExcludeEffortItem($item, $productLookup);
    }

    private function resolveProductForItem($item, array $productLookup): ?array
    {
        $productId = data_get($item, 'product_id') ?? data_get($item, 'productId') ?? data_get($item, 'product.id');
        if ($productId !== null && isset($productLookup['by_id'][(int) $productId])) {
            return $productLookup['by_id'][(int) $productId];
        }

        $sku = mb_strtolower(trim((string) (data_get($item, 'code') ?? data_get($item, 'product_code') ?? data_get($item, 'sku') ?? '')));
        if ($sku !== '' && isset($productLookup['by_sku'][$sku])) {
            return $productLookup['by_sku'][$sku];
        }

        $name = mb_strtolower(trim((string) (data_get($item, 'name') ?? data_get($item, 'product_name') ?? '')));
        if ($name !== '' && isset($productLookup['by_name'][$name])) {
            return $productLookup['by_name'][$name];
        }

        return null;
    }

    private function buildProductLookup(): array
    {
        if (!Schema::hasTable('products')) {
            return ['by_id' => [], 'by_sku' => [], 'by_name' => []];
        }

        $rows = DB::table('products')
            ->select(['id', 'sku', 'name', 'business_division'])
            ->get();

        $byId = [];
        $bySku = [];
        $byName = [];

        foreach ($rows as $row) {
            $payload = [
                'id' => (int) $row->id,
                'sku' => (string) ($row->sku ?? ''),
                'name' => (string) ($row->name ?? ''),
                'business_division' => (string) ($row->business_division ?? ''),
            ];
            $byId[(int) $row->id] = $payload;

            $sku = mb_strtolower(trim((string) ($row->sku ?? '')));
            if ($sku !== '') {
                $bySku[$sku] = $payload;
            }

            $name = mb_strtolower(trim((string) ($row->name ?? '')));
            if ($name !== '') {
                $byName[$name] = $payload;
            }
        }

        return ['by_id' => $byId, 'by_sku' => $bySku, 'by_name' => $byName];
    }

    private function calculateRate(float $numerator, float $denominator): float
    {
        if ($denominator === 0.0) {
            return 0.0;
        }

        return ($numerator / $denominator) * 100;
    }

    private function calculateProductivity(float $grossProfit, float $effortPersonDays): float
    {
        if ($effortPersonDays <= 0.0) {
            return 0.0;
        }

        return $grossProfit / $effortPersonDays;
    }

    private function toPersonDays(float $value, string $unit): float
    {
        $normalizedUnit = mb_strtolower(trim($unit));
        $personDaysPerPersonMonth = (float) config('app.person_days_per_person_month', 20.0);
        $hoursPerPersonDay = (float) config('app.person_hours_per_person_day', 8.0);

        if ($normalizedUnit !== '' && str_contains($normalizedUnit, '人月')) {
            return $value * $personDaysPerPersonMonth;
        }

        if (
            $normalizedUnit !== ''
            && (str_contains($normalizedUnit, '人時') || str_contains($normalizedUnit, '時間') || $normalizedUnit === 'h' || $normalizedUnit === 'hr')
        ) {
            return $hoursPerPersonDay > 0 ? ($value / $hoursPerPersonDay) : 0.0;
        }

        return $value;
    }

    private function buildDailyReportSummary(
        Carbon $from,
        Carbon $to,
        string $timezone,
        array $trackedProjectIds = [],
        array $trackedProjectNames = []
    ): array
    {
        $reports = $this->fetchDailyReports($from, $to);
        if (empty($reports)) {
            return [
                'enabled' => false,
                'monthly_person_days' => [],
                'top_projects' => [],
                'match_rate' => 0.0,
                'matched_person_days' => 0.0,
                'unmatched_person_days' => 0.0,
                'tracked_project_count' => count($trackedProjectIds) + count($trackedProjectNames),
            ];
        }

        $monthlyPersonDays = [];
        $projectPersonDays = [];
        $matchedPersonDays = 0.0;
        $unmatchedPersonDays = 0.0;
        $scopeById = collect($trackedProjectIds)->map(fn ($id) => (string) $id)->filter()->values()->all();
        $scopeByName = collect($trackedProjectNames)
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->values()
            ->all();
        $scopeEnabled = !empty($scopeById) || !empty($scopeByName);

        foreach ($reports as $report) {
            $date = (string) ($report['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $hours = (float) ($report['actual_hours'] ?? $report['hours'] ?? 0);
            if ($hours <= 0) {
                continue;
            }
            $personDays = $this->toPersonDays($hours, '時間');

            $monthKey = Carbon::parse($date, $timezone)->startOfMonth()->toDateString();
            $projectId = (string) (data_get($report, 'project_id') ?? data_get($report, 'project.id') ?? '');
            $projectName = trim((string) data_get($report, 'project.name', ''));
            if ($projectName === '') {
                $projectName = '未紐付け';
            }
            $projectPersonDays[$projectName] = ($projectPersonDays[$projectName] ?? 0) + $personDays;

            $nameKey = mb_strtolower($projectName);
            $isMatched = $scopeEnabled
                ? (
                    ($projectId !== '' && in_array($projectId, $scopeById, true))
                    || ($nameKey !== '' && in_array($nameKey, $scopeByName, true))
                )
                : false;

            if ($isMatched) {
                $monthlyPersonDays[$monthKey] = ($monthlyPersonDays[$monthKey] ?? 0) + $personDays;
                $matchedPersonDays += $personDays;
            } else {
                $unmatchedPersonDays += $personDays;
            }
        }

        arsort($projectPersonDays);
        $topProjects = collect($projectPersonDays)->map(function ($personDays, $name) {
            return ['project_name' => $name, 'person_days' => (float) $personDays];
        })->values()->take(5)->toArray();

        $total = $matchedPersonDays + $unmatchedPersonDays;
        $matchRate = $total > 0 ? ($matchedPersonDays / $total) * 100 : 0.0;

        return [
            'enabled' => true,
            'monthly_person_days' => $monthlyPersonDays,
            'top_projects' => $topProjects,
            'match_rate' => $matchRate,
            'matched_person_days' => $matchedPersonDays,
            'unmatched_person_days' => $unmatchedPersonDays,
            'tracked_project_count' => count($scopeById) + count($scopeByName),
        ];
    }

    private function fetchDailyReports(Carbon $from, Carbon $to): array
    {
        $token = $this->resolvePmApiToken();
        if ($token === '') {
            return [];
        }

        $base = rtrim((string) env('XERO_PM_API_BASE', 'https://api.xerographix.co.jp/api'), '/');

        try {
            $response = \Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->get($base . '/daily-reports');

            if (!$response->successful()) {
                \Log::warning('Failed to fetch daily reports for dashboard', [
                    'status' => $response->status(),
                    'url' => $base . '/daily-reports',
                ]);
                return [];
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return [];
            }

            return collect($payload)->filter(function ($row) use ($from, $to) {
                $date = data_get($row, 'date');
                if (!$date) {
                    return false;
                }
                $at = Carbon::parse($date)->startOfDay();
                return $at->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay());
            })->values()->all();
        } catch (\Throwable $e) {
            \Log::error('Error fetching daily reports for dashboard', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function resolvePmApiToken(): string
    {
        return (string) (env('XERO_PM_API_TOKEN')
            ?: env('EXTERNAL_API_TOKEN')
            ?: '');
    }

    private function isPaidStatus(string $status): bool
    {
        $normalized = mb_strtolower(trim($status));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'paid')
            || str_contains($normalized, '入金済')
            || str_contains($normalized, '入金完了')
            || str_contains($normalized, '支払済');
    }

    private function resolveBillingAmount(Billing $billing): float
    {
        $total = (float) ($billing->total_price ?? 0);
        if ($total > 0) {
            return $total;
        }

        return (float) ($billing->subtotal_price ?? 0);
    }

    private function buildSalesRanking(Carbon $periodStart, Carbon $periodEnd): array
    {
        $rows = Estimate::query()
            ->selectRaw('COALESCE(customer_name, "不明") as customer_name, SUM(total_amount) as total_amount')
            ->whereNull('mf_deleted_at')
            ->where('is_order_confirmed', true)
            ->whereBetween(DB::raw('COALESCE(delivery_date, due_date, issue_date)'), [$periodStart->toDateString(), $periodEnd->toDateString()])
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
