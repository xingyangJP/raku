<?php

namespace App\Services;

use App\Models\Estimate;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MoneyForwardApiService
{
    protected $client;
    protected $apiUrl;
    protected $tokenUrl;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('services.money_forward.api_url');
        $this->tokenUrl = config('services.money_forward.token_url');
        $this->clientId = config('services.money_forward.client_id');
        $this->clientSecret = config('services.money_forward.client_secret');
        $this->redirectUri = config('services.money_forward.redirect_uri');
    }

    // This is a placeholder. A proper implementation would require a robust way
    // to get a valid token, likely involving refresh tokens and persistent storage.
    // For now, this will require a new authorization code for each use.
    public function getAccessToken($code)
    {
        try {
            $response = $this->client->post($this->tokenUrl, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ],
            ]);

            return json_decode($response->getBody()->getContents())->access_token;
        } catch (\Exception $e) {
            Log::error('Failed to get Money Forward access token: ' . $e->getMessage());
            return null;
        }
    }

    public function createInvoiceFromEstimate(Estimate $estimate, $accessToken)
    {
        $items = [];
        foreach ($estimate->items as $item) {
            $items[] = [
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['qty'],
                'unit' => $item['unit'],
                'detail' => $item['description'] ?? '',
            ];
        }

        $invoiceData = [
            'billing' => [
                'partner_id' => $estimate->client_id,
                'title' => $estimate->title,
                'billing_date' => $estimate->issue_date->format('Y-m-d'),
                'due_date' => $estimate->due_date->format('Y-m-d'),
                'sales_date' => $estimate->issue_date->format('Y-m-d'), // Assuming sales date is same as issue date
                'notes' => $estimate->notes,
                'items' => $items,
            ]
        ];

        try {
            $response = $this->client->post($this->apiUrl . '/invoice_template_billings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $invoiceData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to create Money Forward invoice: ' . $e->getMessage());
            report($e);
            return null;
        }
    }

    public function createQuoteFromEstimate(Estimate $estimate, $accessToken)
    {
        $items = [];
        foreach ($estimate->items as $item) {
            $excise = 'ten_percent'; // Default
            if (isset($item['tax_category'])) {
                switch ($item['tax_category']) {
                    case 'standard':
                        $excise = 'ten_percent';
                        break;
                    case 'reduced':
                        $excise = 'eight_percent_as_reduced_tax_rate';
                        break;
                    case 'exempt':
                        $excise = 'tax_exemption';
                        break;
                }
            }

            $items[] = [
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['qty'],
                'unit' => $item['unit'],
                'detail' => $item['description'] ?? '',
                'excise' => $excise,
            ];
        }

        $quoteData = [
            'department_id' => $estimate->mf_department_id,
            'partner_id' => $estimate->client_id,
            'quote_number' => $estimate->estimate_number,
            'title' => $estimate->title,
            'memo' => $estimate->internal_memo ?? '',
            'quote_date' => $estimate->issue_date->format('Y-m-d'),
            'expired_date' => $estimate->due_date->format('Y-m-d'),
            'note' => $estimate->notes,
            'document_name' => '見積書',
            'items' => $items,
        ];

        try {
            $response = $this->client->post($this->apiUrl . '/api/v3/quotes', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $quoteData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to create Money Forward quote: ' . $e->getMessage());
            report($e);
            return null;
        }
    }

    public function convertQuoteToBilling($quoteId, $accessToken)
    {
        try {
            $response = $this->client->post($this->apiUrl . "/api/v3/quotes/{$quoteId}/convert_to_billing", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to convert Money Forward quote to billing: ' . $e->getMessage());
            report($e);
            return null;
        }
    }
}
