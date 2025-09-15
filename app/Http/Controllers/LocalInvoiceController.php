<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\LocalInvoice;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

class LocalInvoiceController extends Controller
{
    public function createFromEstimate(Estimate $estimate)
    {
        // Fallbacks: try to carry over missing partner/department info from local partners table
        $clientId = $estimate->client_id;
        if (empty($clientId) && Schema::hasTable('partners')) {
            $byName = Partner::where('name', $estimate->customer_name)->first();
            if ($byName) { $clientId = (string) $byName->mf_partner_id; }
        }

        $deptId = $estimate->mf_department_id;
        if ((empty($deptId) || $deptId === '1') && !empty($clientId) && Schema::hasTable('partners')) {
            $p = Partner::where('mf_partner_id', $clientId)->first();
            if ($p && is_array($p->payload)) {
                $payload = $p->payload;
                // Prefer departments
                if (isset($payload['departments']) && is_array($payload['departments']) && count($payload['departments']) > 0) {
                    $deptId = (string) ($payload['departments'][0]['id'] ?? $deptId);
                }
                // Then look under offices -> departments
                if (empty($deptId) && isset($payload['offices']) && is_array($payload['offices']) && count($payload['offices']) > 0) {
                    foreach ($payload['offices'] as $office) {
                        if (isset($office['departments']) && is_array($office['departments']) && count($office['departments']) > 0) {
                            $deptId = (string) ($office['departments'][0]['id'] ?? $deptId);
                            break;
                        }
                    }
                    if (empty($deptId)) { $deptId = (string) ($payload['offices'][0]['id'] ?? ''); }
                }
            }
        }

        $invoice = LocalInvoice::create([
            'estimate_id' => $estimate->id,
            'customer_name' => $estimate->customer_name,
            'client_id' => $clientId,
            'department_id' => $deptId,
            'title' => $estimate->title,
            'billing_number' => LocalInvoice::generateReadableBillingNumber($estimate->staff_id, $clientId),
            'billing_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'notes' => $estimate->notes,
            'items' => $estimate->items,
            'total_amount' => $estimate->total_amount,
            'tax_amount' => $estimate->tax_amount,
            'staff_id' => $estimate->staff_id,
            'staff_name' => $estimate->staff_name,
            'status' => 'draft',
        ]);

        return redirect()->route('invoices.edit', $invoice->id);
    }

    public function edit(LocalInvoice $invoice)
    {
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, LocalInvoice $invoice)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'department_id' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'billing_number' => 'required|string|max:255|unique:local_invoices,billing_number,' . $invoice->id,
            'billing_date' => 'required|date',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,final',
        ]);

        $invoice->update($validated);

        return redirect()->route('invoices.edit', $invoice->id)->with('success', '請求書を更新しました。');
    }

    public function redirectToAuthForSending(LocalInvoice $invoice)
    {
        session(['local_invoice_id_for_sending' => $invoice->id]);
        // Add state for CSRF protection (invoice-specific key to avoid collisions)
        $state = bin2hex(random_bytes(16));
        session(['mf_invoice_oauth_state' => $state]);

        $redirectUri = env('MONEY_FORWARD_INVOICE_REDIRECT_URI', route('invoices.send.callback'));
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => env('MONEY_FORWARD_QUOTE_SCOPE', 'mfc/invoice/data.write'),
            'state' => $state,
        ];
        \Log::info('MF authorize (local invoice) redirect', $params + ['invoice_id' => $invoice->id]);
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return \Inertia\Inertia::location($authUrl);
    }

    public function handleSendCallback(Request $request, \App\Services\MoneyForwardApiService $api)
    {
        $invoiceId = session('local_invoice_id_for_sending');
        // Validate presence of authorization code
        if (!$request->has('code')) {
            if ($invoiceId) {
                return redirect()->route('invoices.edit', $invoiceId)->with('error', 'Authorization failed.');
            }
            return redirect()->route('billing.index')->with('error', 'Authorization failed.');
        }

        // Validate state to mitigate CSRF
        $sentState = session('mf_invoice_oauth_state');
        $recvState = $request->input('state');
        if ($sentState && $recvState && !hash_equals($sentState, $recvState)) {
            // Clear state and session target to avoid reuse
            session()->forget('mf_invoice_oauth_state');
            session()->forget('local_invoice_id_for_sending');
            if ($invoiceId) {
                return redirect()->route('invoices.edit', $invoiceId)->with('error', 'State mismatch. Please try again.');
            }
            return redirect()->route('billing.index')->with('error', 'State mismatch.');
        }
        if (!$invoiceId) {
            return redirect()->route('billing.index')->with('error', 'Invoice not found in session.');
        }
        $invoice = LocalInvoice::find($invoiceId);
        if (!$invoice) {
            return redirect()->route('billing.index')->with('error', 'Invoice not found.');
        }

        try {
            // Use the exact redirect_uri used during authorization for token exchange
            $redirectUsed = env('MONEY_FORWARD_INVOICE_REDIRECT_URI', route('invoices.send.callback'));
            $accessToken = $api->getAccessToken($request->code, $redirectUsed);

            if (!$accessToken) {
                return redirect()->route('invoices.edit', $invoice->id)->with('error', 'トークン取得に失敗しました。');
            }

            // Validate/repair department_id against MF partner detail before creating invoice
            if (empty($invoice->client_id)) {
                return redirect()->route('invoices.edit', $invoice->id)->with('error', 'MF partner_id が未設定です。');
            }
            try {
                $detail = (new \GuzzleHttp\Client())->get(config('services.money_forward.api_url') . "/partners/{$invoice->client_id}", [
                    'headers' => [ 'Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json' ],
                ]);
                $body = json_decode($detail->getBody()->getContents(), true);
                $ids = [];
                $data = $body['data'] ?? $body;
                if (!empty($data['departments']) && is_array($data['departments'])) {
                    foreach ($data['departments'] as $d) { $ids[] = (string)($d['id'] ?? ''); }
                }
                if (empty($ids) && !empty($data['offices']) && is_array($data['offices'])) {
                    foreach ($data['offices'] as $office) {
                        if (isset($office['departments']) && is_array($office['departments'])) {
                            foreach ($office['departments'] as $d) { $ids[] = (string)($d['id'] ?? ''); }
                        }
                    }
                }
                $ids = array_values(array_filter($ids));
                if (empty($ids)) {
                    return redirect()->route('invoices.edit', $invoice->id)->with('error', 'MF部門が取得できませんでした。MF側で部門を設定し、取引先を再同期してください。');
                }
                if (!in_array((string)($invoice->department_id ?? ''), $ids, true)) {
                    $invoice->department_id = $ids[0];
                    $invoice->save();
                    \Log::info('Replaced invalid local invoice department_id with first available', ['invoice_id' => $invoice->id, 'department_id' => $invoice->department_id]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not verify/repair department_id before sending invoice: ' . $e->getMessage());
            }

            $result = $api->createInvoiceFromLocal($invoice, $accessToken);

            if (is_array($result) && isset($result['id'])) {
                $invoice->mf_billing_id = $result['id'];
                if (isset($result['pdf_url']) && Schema::hasColumn('local_invoices', 'mf_pdf_url')) {
                    $invoice->mf_pdf_url = $result['pdf_url'];
                }
                $invoice->save();
                // Clear session keys used for this flow
                session()->forget('mf_invoice_oauth_state');
                session()->forget('local_invoice_id_for_sending');
                return redirect()->route('invoices.edit', $invoice->id)->with('success', 'MFに請求書を作成しました。');
            }
            // Clear state on failure as well
            session()->forget('mf_invoice_oauth_state');
            session()->forget('local_invoice_id_for_sending');
            $msg = 'MF請求書作成に失敗しました。';
            if (is_array($result) && isset($result['response'])) {
                $decoded = json_decode((string) $result['response'], true);
                if (!empty($decoded['error_description'])) { $msg .= ' ' . $decoded['error_description']; }
            }
            return redirect()->route('invoices.edit', $invoice->id)->with('error', $msg);
        } catch (\Throwable $e) {
            \Log::error('Failed sending local invoice to MF: ' . $e->getMessage());
            session()->forget('mf_invoice_oauth_state');
            session()->forget('local_invoice_id_for_sending');
            return redirect()->route('invoices.edit', $invoice->id)->with('error', 'エラーが発生しました。');
        }
    }

    public function redirectToAuthForPdf(LocalInvoice $invoice)
    {
        if (empty($invoice->mf_billing_id)) {
            return redirect()->route('invoices.edit', $invoice->id)->with('error', 'MF請求書がまだ作成されていません。');
        }
        session(['local_invoice_id_for_pdf' => $invoice->id]);
        $state = bin2hex(random_bytes(16));
        session(['mf_invoice_pdf_state' => $state]);
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => env('MONEY_FORWARD_ESTIMATE_REDIRECT_URI', route('estimates.createQuote.callback')),
            'scope' => env('MONEY_FORWARD_QUOTE_SCOPE', 'mfc/invoice/data.write'),
            'state' => $state,
        ]);
        return \Inertia\Inertia::location($authUrl);
    }
}
