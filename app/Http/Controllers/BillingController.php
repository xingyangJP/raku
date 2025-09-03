<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Inertia\Inertia;
use App\Models\Billing;
use App\Models\BillingItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function index()
    {
        $moneyForwardConfig = [
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => config('services.money_forward.redirect_uri'),
            'authorization_url' => config('services.money_forward.authorization_url'),
            'scope' => 'mfc/invoice/data.read', // Adjust scope as per Money Forward API docs
        ];

        $billings = Billing::with('items')->get();

        return Inertia::render('Billing/Index', [
            'moneyForwardConfig' => $moneyForwardConfig,
            'moneyForwardInvoices' => ['data' => $billings->toArray()],
            'error' => session('error'), // Pass error message from session
        ]);
    }

    public function fetchInvoices(Request $request)
    {
        $clientId = config('services.money_forward.client_id');
        $clientSecret = config('services.money_forward.client_secret');
        $redirectUri = config('services.money_forward.redirect_uri');
        $tokenUrl = config('services.money_forward.token_url');
        $apiUrl = config('services.money_forward.api_url');

        $client = new Client();

        if (!$request->has('code')) {
            // Redirect back to the billing index with an error message
            return redirect()->route('billing.index')->with('error', 'Authorization code not found.');
        }

        $code = $request->input('code');

        try {
            // Step 2: Exchange Authorization Code for Access Token
            $response = $client->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ],
            ]);

            $accessToken = json_decode($response->getBody()->getContents())->access_token;

            // Step 3: Fetch Invoices using Access Token
            $invoicesResponse = $client->get($apiUrl . '/billings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $invoices = json_decode($invoicesResponse->getBody()->getContents(), true);

            foreach ($invoices['data'] as $invoiceData) {
                $billing = Billing::updateOrCreate(
                    ['id' => $invoiceData['id']],
                    [
                        'pdf_url' => $invoiceData['pdf_url'] ?? null,
                        'operator_id' => $invoiceData['operator_id'] ?? null,
                        'department_id' => $invoiceData['department_id'] ?? null,
                        'member_id' => $invoiceData['member_id'] ?? null,
                        'member_name' => $invoiceData['member_name'] ?? null,
                        'partner_id' => $invoiceData['partner_id'] ?? null,
                        'partner_name' => $invoiceData['partner_name'] ?? null,
                        'office_id' => $invoiceData['office_id'] ?? null,
                        'office_name' => $invoiceData['office_name'] ?? null,
                        'office_detail' => $invoiceData['office_detail'] ?? null,
                        'title' => $invoiceData['title'] ?? null,
                        'memo' => $invoiceData['memo'] ?? null,
                        'payment_condition' => $invoiceData['payment_condition'] ?? null,
                        'billing_date' => $invoiceData['billing_date'] ?? null,
                        'due_date' => $invoiceData['due_date'] ?? null,
                        'sales_date' => $invoiceData['sales_date'] ?? null,
                        'billing_number' => $invoiceData['billing_number'] ?? null,
                        'note' => $invoiceData['note'] ?? null,
                        'document_name' => $invoiceData['document_name'] ?? null,
                        'payment_status' => $invoiceData['payment_status'] ?? null,
                        'email_status' => $invoiceData['email_status'] ?? null,
                        'posting_status' => $invoiceData['posting_status'] ?? null,
                        'is_downloaded' => $invoiceData['is_downloaded'] ?? false,
                        'is_locked' => $invoiceData['is_locked'] ?? false,
                        'deduct_price' => $invoiceData['deduct_price'] ?? null,
                        'tag_names' => $invoiceData['tag_names'] ?? null,
                        'excise_price' => $invoiceData['excise_price'] ?? null,
                        'excise_price_of_untaxable' => $invoiceData['excise_price_of_untaxable'] ?? null,
                        'excise_price_of_non_taxable' => $invoiceData['excise_price_of_non_taxable'] ?? null,
                        'excise_price_of_tax_exemption' => $invoiceData['excise_price_of_tax_exemption'] ?? null,
                        'excise_price_of_five_percent' => $invoiceData['excise_price_of_five_percent'] ?? null,
                        'excise_price_of_eight_percent' => $invoiceData['excise_price_of_eight_percent'] ?? null,
                        'excise_price_of_eight_percent_as_reduced_tax_rate' => $invoiceData['excise_price_of_eight_percent_as_reduced_tax_rate'] ?? null,
                        'excise_price_of_ten_percent' => $invoiceData['excise_price_of_ten_percent'] ?? null,
                        'subtotal_price' => $invoiceData['subtotal_price'] ?? null,
                        'subtotal_of_untaxable_excise' => $invoiceData['subtotal_of_untaxable_excise'] ?? null,
                        'subtotal_of_non_taxable_excise' => $invoiceData['subtotal_of_non_taxable_excise'] ?? null,
                        'subtotal_of_tax_exemption_excise' => $invoiceData['subtotal_of_tax_exemption_excise'] ?? null,
                        'subtotal_of_five_percent_excise' => $invoiceData['subtotal_of_five_percent_excise'] ?? null,
                        'subtotal_of_eight_percent_excise' => $invoiceData['subtotal_of_eight_percent_excise'] ?? null,
                        'subtotal_of_eight_percent_as_reduced_tax_rate_excise' => $invoiceData['subtotal_of_eight_percent_as_reduced_tax_rate_excise'] ?? null,
                        'subtotal_of_ten_percent_excise' => $invoiceData['subtotal_of_ten_percent_excise'] ?? null,
                        'subtotal_with_tax_of_untaxable_excise' => $invoiceData['subtotal_with_tax_of_untaxable_excise'] ?? null,
                        'subtotal_with_tax_of_non_taxable_excise' => $invoiceData['subtotal_with_tax_of_non_taxable_excise'] ?? null,
                        'subtotal_with_tax_of_tax_exemption_excise' => $invoiceData['subtotal_with_tax_of_tax_exemption_excise'] ?? null,
                        'subtotal_with_tax_of_five_percent_excise' => $invoiceData['subtotal_with_tax_of_five_percent_excise'] ?? null,
                        'subtotal_with_tax_of_eight_percent_excise' => $invoiceData['subtotal_with_tax_of_eight_percent_excise'] ?? null,
                        'subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise' => $invoiceData['subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise'] ?? null,
                        'subtotal_with_tax_of_ten_percent_excise' => $invoiceData['subtotal_with_tax_of_ten_percent_excise'] ?? null,
                        'total_price' => $invoiceData['total_price'] ?? null,
                        'registration_code' => $invoiceData['registration_code'] ?? null,
                        'use_invoice_template' => $invoiceData['use_invoice_template'] ?? false,
                        'config' => $invoiceData['config'] ?? null,
                    ]
                );

                if (isset($invoiceData['items'])) {
                    foreach ($invoiceData['items'] as $itemData) {
                        $billing->items()->updateOrCreate(
                            ['id' => $itemData['id']],
                            [
                                'name' => $itemData['name'] ?? null,
                                'code' => $itemData['code'] ?? null,
                                'detail' => $itemData['detail'] ?? null,
                                'unit' => $itemData['unit'] ?? null,
                                'price' => $itemData['price'] ?? null,
                                'quantity' => $itemData['quantity'] ?? null,
                                'is_deduct_withholding_tax' => $itemData['is_deduct_withholding_tax'] ?? false,
                                'excise' => $itemData['excise'] ?? null,
                                'delivery_number' => $itemData['delivery_number'] ?? null,
                                'delivery_date' => $itemData['delivery_date'] ?? null,
                            ]
                        );
                    }
                }

                if (!empty($invoiceData['pdf_url'])) {
                    try {
                        $pdfResponse = $client->get($invoiceData['pdf_url'], [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $accessToken,
                            ],
                        ]);

                        if ($pdfResponse->getStatusCode() === 200) {
                            $pdfContent = $pdfResponse->getBody()->getContents();
                            Storage::put('public/billings/' . $invoiceData['id'] . '.pdf', $pdfContent);
                        }
                    } catch (\Exception $e) {
                        Log::error('PDF download failed for billing ID ' . ($invoiceData['id'] ?? 'N/A') . ': ' . $e->getMessage());
                    }
                }
            }

            // Redirect back to the billing page, which will now fetch from DB
            return redirect()->route('billing.index');

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
