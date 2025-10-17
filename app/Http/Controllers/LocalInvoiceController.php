<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\LocalInvoice;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

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
            'sales_date' => now()->format('Y-m-d'),
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
        // 明細の品目名などをローカル商品マスタから補完（表示用のみ、DBは更新しない）
        try {
            $items = is_array($invoice->items) ? $invoice->items : [];
            if (!empty($items) && \Illuminate\Support\Facades\Schema::hasTable('products')) {
                // 参照候補の id/sku/code を収集（旧データの互換: productId, product.id も拾う）
                $ids = [];
                $codes = [];
                foreach ($items as $it) {
                    // snake_case
                    if (!empty($it['product_id'])) { $ids[] = (int) $it['product_id']; }
                    // camelCase（旧データ互換）
                    if (!empty($it['productId'])) { $ids[] = (int) $it['productId']; }
                    // nested object (e.g. { product: { id, name, ... } })
                    if (!empty($it['product']) && is_array($it['product']) && !empty($it['product']['id'])) {
                        $ids[] = (int) $it['product']['id'];
                    }

                    if (!empty($it['sku'])) { $codes[] = (string) $it['sku']; }
                    if (!empty($it['code'])) { $codes[] = (string) $it['code']; }
                }
                $ids = array_values(array_unique(array_filter($ids)));
                $codes = array_values(array_unique(array_filter($codes)));

                $byId = [];
                $bySku = [];
                if (!empty($ids)) {
                    $byId = \App\Models\Product::query()
                        ->whereIn('id', $ids)
                        ->get(['id','sku','name','unit','price','description','cost'])
                        ->keyBy('id');
                }
                if (!empty($codes)) {
                    $bySku = \App\Models\Product::query()
                        ->whereIn('sku', $codes)
                        ->get(['id','sku','name','unit','price','description','cost'])
                        ->keyBy('sku');
                }

                $items = array_map(function ($it) use ($byId, $bySku) {
                    $p = null;
                    // Try resolving product by multiple keys
                    if (!empty($it['product_id']) && isset($byId[$it['product_id']])) {
                        $p = $byId[$it['product_id']];
                    } elseif (!empty($it['productId']) && isset($byId[$it['productId']])) {
                        $p = $byId[$it['productId']];
                    } elseif (!empty($it['product']) && is_array($it['product']) && !empty($it['product']['id']) && isset($byId[$it['product']['id']])) {
                        $p = $byId[$it['product']['id']];
                    } elseif (!empty($it['sku']) && isset($bySku[$it['sku']])) {
                        $p = $bySku[$it['sku']];
                    } elseif (!empty($it['code']) && isset($bySku[$it['code']])) {
                        $p = $bySku[$it['code']];
                    }

                    if ($p) {
                        // name/description/unit/price が欠けていれば商品マスタから補完
                        $it['name'] = $it['name'] ?? $p->name;
                        if (empty($it['description']) && !empty($p->description)) { $it['description'] = $p->description; }
                        if (empty($it['unit']) && !empty($p->unit)) { $it['unit'] = $p->unit; }
                        if (!isset($it['price']) && isset($p->price)) { $it['price'] = (float) $p->price; }
                    }

                    // 最後のフォールバック（旧データで name が空のままの場合、description を name に映す）
                    if (empty($it['name']) && !empty($it['description'])) {
                        $it['name'] = $it['description'];
                    }
                    return $it;
                }, $items);

                // 表示用に差し替え（保存はしない）
                $invoice->setAttribute('items', $items);
            }
        } catch (\Throwable $e) {
            // 補完失敗時も表示は継続
        }

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
            'sales_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,final',
        ]);

        // 請求番号は編集禁止（サーバ側でも強制維持）
        $validated['billing_number'] = $invoice->billing_number;
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
        // まずPDF閲覧要求のコールバックかを判定
        $pdfInvoiceId = session('local_invoice_id_for_pdf');
        $stateParam = (string) $request->input('state', '');
        if (!$pdfInvoiceId && $stateParam) {
            $decoded = json_decode(base64_decode(strtr($stateParam, '-_', '+/')) ?: 'null', true);
            if (is_array($decoded) && ($decoded['k'] ?? '') === 'pdf' && !empty($decoded['i'])) {
                $pdfInvoiceId = (int) $decoded['i'];
            }
        }
        if ($pdfInvoiceId) {
            if (!$request->has('code')) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'Authorization failed.');
            }
            $sent = session('mf_invoice_pdf_state');
            $recv = $request->input('state');
            if ($sent && $recv && !hash_equals($sent, $recv)) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'State mismatch. Please try again.');
            }
            $invoice = LocalInvoice::find($pdfInvoiceId);
            if (!$invoice || empty($invoice->mf_pdf_url)) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'PDF URL が見つかりません。');
            }
            $redirectUsed = env('MONEY_FORWARD_INVOICE_REDIRECT_URI', route('invoices.send.callback'));
            $tokenData = $api->getAccessTokenFromCode($request->code, $redirectUsed);
            if (!$tokenData || empty($tokenData['access_token'])) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'トークン取得に失敗しました。');
            }
            try {
                $client = new \GuzzleHttp\Client();
                $res = $client->get($invoice->mf_pdf_url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Accept' => 'application/pdf',
                    ],
                    'http_errors' => false,
                ]);
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                if ($res->getStatusCode() !== 200) {
                    return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'PDF取得に失敗しました。');
                }
                $content = (string) $res->getBody()->getContents();
                $path = 'public/billings/local-' . $pdfInvoiceId . '.pdf';
                \Illuminate\Support\Facades\Storage::put($path, $content);
                return redirect()->route('invoices.downloadPdf', $pdfInvoiceId);
            } catch (\Throwable $e) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $pdfInvoiceId)->with('error', 'PDF表示時にエラーが発生しました。');
            }
        }

        // ここからは送信（作成）フロー
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
            // Exchange authorization code for tokens
            $tokenData = $api->getAccessTokenFromCode($request->code, $redirectUsed);
            if (!$tokenData || empty($tokenData['access_token'])) {
                return redirect()->route('invoices.edit', $invoice->id)->with('error', 'トークン取得に失敗しました。');
            }
            // Persist tokens for 2回目以降の自動実行
            try { if (Auth::id()) { $api->storeToken($tokenData, Auth::id()); } } catch (\Throwable $e) {}
            $accessToken = $tokenData['access_token'];

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

    public function redirectToAuthForPdf(LocalInvoice $invoice, \App\Services\MoneyForwardApiService $api)
    {
        if (empty($invoice->mf_billing_id)) {
            return redirect()->route('invoices.edit', $invoice->id)->with('error', 'MF請求書がまだ作成されていません。');
        }
        // 2回目以降は有効なアクセストークンがあれば直取得
        if ($token = $api->getValidAccessToken(null, 'mfc/invoice/data.read')) {
            try {
                $client = new \GuzzleHttp\Client();
                $res = $client->get($invoice->mf_pdf_url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/pdf',
                    ],
                    'http_errors' => false,
                ]);
                if ($res->getStatusCode() === 200) {
                    $content = (string) $res->getBody()->getContents();
                    return response($content, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="invoice-' . $invoice->id . '.pdf"',
                    ]);
                }
            } catch (\Throwable $e) {
                // フォールバックでOAuthへ遷移
            }
        }

        // フォールバック: OAuth 認可 → コールバックでPDFストリーム
        $rand = bin2hex(random_bytes(8));
        $stateObj = ['k' => 'pdf', 'i' => (int) $invoice->id, 's' => $rand];
        $state = rtrim(strtr(base64_encode(json_encode($stateObj)), '+/', '-_'), '=');
        session(['mf_invoice_pdf_state' => $state, 'local_invoice_id_for_pdf' => $invoice->id]);
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => env('MONEY_FORWARD_INVOICE_REDIRECT_URI', route('invoices.send.callback')),
            'scope' => 'mfc/invoice/data.read',
            'state' => $state,
        ]);
        return \Inertia\Inertia::location($authUrl);
    }

    public function handleViewPdfCallback(Request $request, \App\Services\MoneyForwardApiService $api)
    {
        $invoiceId = session('local_invoice_id_for_pdf');
        if (!$request->has('code')) {
            return $invoiceId
                ? redirect()->route('invoices.edit', $invoiceId)->with('error', 'Authorization failed.')
                : redirect()->route('billing.index')->with('error', 'Authorization failed.');
        }
        // CSRF state check
        $sent = session('mf_invoice_pdf_state');
        $recv = $request->input('state');
        if ($sent && $recv && !hash_equals($sent, $recv)) {
            session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
            return $invoiceId
                ? redirect()->route('invoices.edit', $invoiceId)->with('error', 'State mismatch. Please try again.')
                : redirect()->route('billing.index')->with('error', 'State mismatch.');
        }
        if (!$invoiceId) {
            return redirect()->route('billing.index')->with('error', 'Invoice not found in session.');
        }
        $invoice = LocalInvoice::find($invoiceId);
        if (!$invoice || empty($invoice->mf_pdf_url)) {
            session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
            return redirect()->route('invoices.edit', $invoiceId)->with('error', 'PDF URL が見つかりません。');
        }

        try {
            $redirectUsed = env('MONEY_FORWARD_INVOICE_PDF_REDIRECT_URI', route('invoices.viewPdf.callback'));
            $tokenData = $api->getAccessTokenFromCode($request->code, $redirectUsed);
            if (!$tokenData || empty($tokenData['access_token'])) {
                session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
                return redirect()->route('invoices.edit', $invoiceId)->with('error', 'トークン取得に失敗しました。');
            }

            // 直接PDFを取得してストリーム返却
            $client = new \GuzzleHttp\Client();
            $res = $client->get($invoice->mf_pdf_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Accept' => 'application/pdf',
                ],
                'http_errors' => false,
            ]);

            session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);

            if ($res->getStatusCode() !== 200) {
                return redirect()->route('invoices.edit', $invoiceId)->with('error', 'PDF取得に失敗しました。');
            }

            $content = $res->getBody()->getContents();
            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="invoice-' . $invoiceId . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            session()->forget(['mf_invoice_pdf_state', 'local_invoice_id_for_pdf']);
            return redirect()->route('invoices.edit', $invoiceId)->with('error', 'PDF表示時にエラーが発生しました。');
        }
    }

    public function destroy(LocalInvoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('billing.index')->with('success', '請求書を削除しました。');
    }

    public function downloadPdf(LocalInvoice $invoice)
    {
        $path = 'public/billings/local-' . $invoice->id . '.pdf';
        if (!\Illuminate\Support\Facades\Storage::exists($path)) {
            return redirect()->route('invoices.edit', $invoice->id)->with('error', '保存済みPDFが見つかりません。「PDFを確認」で取得してください。');
        }
        return \Illuminate\Support\Facades\Storage::response($path, 'invoice-' . $invoice->id . '.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->id . '.pdf"',
        ]);
    }
}
