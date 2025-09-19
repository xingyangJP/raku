<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Billing;
use Illuminate\Support\Facades\Storage;
use App\Services\MoneyForwardBillingSynchronizer;
use App\Services\MoneyForwardApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BillingController extends Controller
{
    public function index(Request $request, MoneyForwardBillingSynchronizer $billingSynchronizer)
    {
        $syncStatus = $billingSynchronizer->syncIfStale();

        if (($syncStatus['status'] ?? null) === 'unauthorized') {
            $request->session()->put('mf_redirect_back', url()->full());
            return redirect()->route('billing.auth.start');
        }

        if (($syncStatus['status'] ?? null) === 'error' && !$request->session()->has('error')) {
            $message = 'Money Forwardとの同期に失敗しました: ' . ($syncStatus['message'] ?? '理由不明');
            $request->session()->flash('error', $message);
        }

        $timezone = config('app.sales_timezone', 'Asia/Tokyo');
        $currentMonth = Carbon::now($timezone);
        $fromMonth = (string) $request->query('from', $currentMonth->format('Y-m'));
        $toMonth = (string) ($request->query('to') ?? $currentMonth->format('Y-m'));

        $moneyForwardConfig = [
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => env('MONEY_FORWARD_BILLING_REDIRECT_URI', route('money-forward.callback')),
            'authorization_url' => config('services.money_forward.authorization_url'),
            'scope' => 'mfc/invoice/data.read', // Adjust scope as per Money Forward API docs
        ];

        $billingStart = Carbon::createFromFormat('Y-m', $fromMonth, $timezone)->startOfMonth();
        $billingEnd = Carbon::createFromFormat('Y-m', $toMonth, $timezone)->endOfMonth();

        $billings = Billing::with('items')
            ->whereBetween('billing_date', [$billingStart->toDateString(), $billingEnd->toDateString()])
            ->get();

        // Merge local invoices for display convenience
        $local = \App\Models\LocalInvoice::query()
            ->whereBetween('billing_date', [$billingStart->toDateString(), $billingEnd->toDateString()])
            ->get();
        $localMapped = $local->map(function ($inv) {
            return [
                'id' => 'local-' . $inv->id,
                'local_invoice_id' => $inv->id,
                'source' => 'local',
                'billing_number' => $inv->billing_number,
                'partner_name' => $inv->customer_name,
                'title' => $inv->title,
                'billing_date' => optional($inv->billing_date)->format('Y-m-d'),
                'due_date' => optional($inv->due_date)->format('Y-m-d'),
                'total_price' => $inv->total_amount,
                'email_status' => '未設定',
                'posting_status' => '未郵送',
                'payment_status' => '未設定',
                'is_locked' => false,
                'is_downloaded' => false,
                'updated_at' => optional($inv->updated_at)->format('Y-m-d'),
                // Add MF linkage status so UI can show PDF instead of "MF未生成"
                'mf_billing_id' => $inv->mf_billing_id,
                'mf_pdf_url' => $inv->mf_pdf_url,
            ];
        });

        $merged = collect($billings)->map->toArray()->concat($localMapped)->values();

        return Inertia::render('Billing/Index', [
            'moneyForwardConfig' => $moneyForwardConfig,
            'moneyForwardInvoices' => ['data' => $merged],
            'error' => session('error'),
            'syncStatus' => $syncStatus,
            'defaultBillingRange' => [
                'from' => $fromMonth,
                'to' => $toMonth,
            ],
        ]);
    }

    public function redirectToAuth()
    {
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => env('MONEY_FORWARD_BILLING_REDIRECT_URI', route('money-forward.callback')),
            'scope' => 'mfc/invoice/data.read',
        ]);

        return Inertia::location($authUrl);
    }

    public function fetchInvoices(Request $request, MoneyForwardApiService $apiService, MoneyForwardBillingSynchronizer $billingSynchronizer)
    {
        if (!$request->has('code')) {
            return redirect()->route('billing.index')->with('error', 'Authorization code not found.');
        }

        $code = $request->input('code');

        $redirectUri = env('MONEY_FORWARD_BILLING_REDIRECT_URI', route('money-forward.callback'));

        try {
            $tokenData = $apiService->getAccessTokenFromCode($code, $redirectUri);
            if (!$tokenData || empty($tokenData['access_token'])) {
                return redirect()->route('billing.index')->with('error', 'Money Forwardの認証に失敗しました。');
            }

            $apiService->storeToken($tokenData, $request->user()->id);

            $billingSynchronizer->sync($request->user()->id);

            $redirectTo = $request->session()->pull('mf_redirect_back', route('billing.index'));

            return redirect()->to($redirectTo)->with('success', 'Money Forward認証が完了しました。');
        } catch (\Exception $e) {
            Log::error('Money Forward callback processing failed', ['exception' => $e]);
            return redirect()->route('billing.index')->with('error', 'Money Forward連携処理でエラーが発生しました。');
        }
    }

    public function downloadPdf(Billing $billing)
    {
        $path = 'public/billings/' . $billing->id . '.pdf';

        if (!Storage::exists($path)) {
            abort(404, 'PDF not found.');
        }

        return Storage::response($path);
    }
}
