<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\LocalInvoice;
use App\Models\MfToken;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MoneyForwardApiService
{
    protected $client;
    protected $apiUrl;
    protected $tokenUrl;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $billingSyncPerPage = 100;
    protected $quoteSyncPerPage = 100;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('services.money_forward.api_url');
        $this->tokenUrl = config('services.money_forward.token_url');
        $this->clientId = config('services.money_forward.client_id');
        $this->clientSecret = config('services.money_forward.client_secret');
        $this->redirectUri = config('services.money_forward.redirect_uri');
        $this->billingSyncPerPage = (int) config('services.money_forward.billing_sync_page_size', 100);
        $this->quoteSyncPerPage = (int) config('services.money_forward.quote_sync_page_size', 100);
    }

    public function fetchBillings(string $accessToken, array $query = []): ?array
    {
        try {
            $normalizedQuery = array_merge([
                'per_page' => $query['per_page'] ?? $this->billingSyncPerPage,
                'page' => $query['page'] ?? 1,
            ], $query);

            // Money Forward API currently rejects the "order" parameter for billings.
            unset($normalizedQuery['order']);

            $response = $this->client->get($this->apiUrl . '/billings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $normalizedQuery,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Money Forward billings: ' . $e->getMessage());
            report($e);
            return null;
        }
    }

    public function fetchQuotes(string $accessToken, array $query = []): ?array
    {
        try {
            $normalizedQuery = array_merge([
                'per_page' => $query['per_page'] ?? $this->quoteSyncPerPage,
                'page' => $query['page'] ?? 1,
            ], $query);

            unset($normalizedQuery['order']);

            $response = $this->client->get($this->apiUrl . '/quotes', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $normalizedQuery,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Money Forward quotes: ' . $e->getMessage());
            report($e);
            return null;
        }
    }

    public function getAccessTokenFromCode(string $code, string $redirectUri): ?array
    {
        try {
            $response = $this->client->post($this->tokenUrl, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get Money Forward access token from code: ' . $e->getMessage());
            return null;
        }
    }

    public function refreshAccessToken(string $refreshToken): ?array
    {
        try {
            $response = $this->client->post($this->tokenUrl, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to refresh Money Forward access token: ' . $e->getMessage());
            // If refresh fails, the old token is likely invalid. The user needs to re-authenticate.
            // Depending on the error, we might want to delete the stored token.
            return null;
        }
    }

    public function storeToken(array $tokenData, int $userId): void
    {
        MfToken::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => Carbon::now()->addSeconds($tokenData['expires_in'] - 60), // Subtract 60s buffer
                'scope' => $tokenData['scope'],
            ]
        );
    }

    public function getValidAccessToken(?int $userId = null, array|string $requiredScopes = []): ?string
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return null;
        }

        $requiredScopes = $this->normalizeScopes($requiredScopes);

        $mfToken = MfToken::where('user_id', $userId)->first();

        if (!$mfToken) {
            return null; // No token, user needs to authorize
        }

        if (!$this->hasRequiredScopes($mfToken->scope, $requiredScopes)) {
            Log::info('MF token missing required scopes', [
                'user_id' => $userId,
                'token_scope' => $mfToken->scope,
                'required' => $requiredScopes,
            ]);
            return null;
        }

        if ($mfToken->isExpired()) {
            Log::info('MF token expired, attempting refresh.', ['user_id' => $userId]);
            $newTokenData = $this->refreshAccessToken($mfToken->refresh_token);

            if (!$newTokenData) {
                Log::error('MF token refresh failed.', ['user_id' => $userId]);
                // Optionally delete the invalid token
                // $mfToken->delete();
                return null; // Refresh failed, user needs to re-authorize
            }

            Log::info('MF token refresh successful.', ['user_id' => $userId]);
            $this->storeToken($newTokenData, $userId);

            if (!$this->hasRequiredScopes($newTokenData['scope'] ?? '', $requiredScopes)) {
                Log::warning('Refreshed MF token still missing required scopes', [
                    'user_id' => $userId,
                    'token_scope' => $newTokenData['scope'] ?? '',
                    'required' => $requiredScopes,
                ]);
                return null;
            }

            return $newTokenData['access_token'];
        }

        return $mfToken->access_token;
    }

    private function normalizeScopes(array|string $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = trim($scopes);
            $scopes = $scopes === '' ? [] : preg_split('/\s+/', $scopes);
        }

        return array_values(array_filter(array_map('trim', $scopes)));
    }

    private function hasRequiredScopes(?string $tokenScope, array $requiredScopes): bool
    {
        if (empty($requiredScopes)) {
            return true;
        }

        $tokenScopes = $this->normalizeScopes($tokenScope ?? '');

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                return false;
            }
        }

        return true;
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

        // Safely resolve dates (fallbacks if null)
        $issue = $estimate->issue_date ? Carbon::parse($estimate->issue_date) : Carbon::now();
        $due = $estimate->due_date ? Carbon::parse($estimate->due_date) : (clone $issue)->addMonth();

        $invoiceData = [
            'billing' => [
                'partner_id' => $estimate->client_id,
                'title' => $estimate->title,
                'billing_date' => $issue->format('Y-m-d'),
                'due_date' => $due->format('Y-m-d'),
                'sales_date' => $issue->format('Y-m-d'), // Assuming sales date is same as issue date
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

    public function createInvoiceFromLocal(LocalInvoice $invoice, $accessToken)
    {
        $items = [];
        foreach (($invoice->items ?? []) as $item) {
            // Map tax category to excise if provided; default to ten_percent
            $excise = 'ten_percent';
            if (isset($item['tax_category'])) {
                switch ($item['tax_category']) {
                    case 'reduced': $excise = 'eight_percent_as_reduced_tax_rate'; break;
                    case 'exempt': $excise = 'untaxable'; break;
                    case 'non_taxable': $excise = 'non_taxable'; break;
                    case 'five_percent': $excise = 'five_percent'; break;
                    case 'eight_percent': $excise = 'eight_percent'; break;
                    case 'ten_percent': $excise = 'ten_percent'; break;
                    default: $excise = 'ten_percent';
                }
            }
            $mapped = [
                'name' => ($item['name'] ?? $item['product_name'] ?? $item['code'] ?? $item['sku'] ?? ''),
                'price' => $item['price'] ?? 0,
                'quantity' => $item['qty'] ?? 1,
                'unit' => $item['unit'] ?? '式',
                'detail' => $item['description'] ?? $item['detail'] ?? '',
            ];
            if (!empty($item['delivery_date'])) {
                $mapped['delivery_date'] = $item['delivery_date'];
            }
            // Always prefer local name/detail over master item by not binding item_id
            $mapped['excise'] = $excise;
            $items[] = $mapped;
        }

        $issue = $invoice->billing_date ? Carbon::parse($invoice->billing_date) : Carbon::now();
        $due = $invoice->due_date ? Carbon::parse($invoice->due_date) : (clone $issue)->addMonth();
        $sales = $invoice->sales_date ? Carbon::parse($invoice->sales_date) : $issue;

        // Build payload by BillingNewTemplateCreateRequest (top-level fields)
        $payload = [
            'partner_id' => $invoice->client_id,
            'department_id' => $invoice->department_id,
            'title' => $invoice->title,
            'memo' => $invoice->notes,
            'payment_condition' => null,
            'billing_date' => $issue->format('Y-m-d'),
            'due_date' => $due->format('Y-m-d'),
            'sales_date' => $sales->format('Y-m-d'),
            'billing_number' => $invoice->billing_number,
            'note' => $invoice->notes,
            'document_name' => '請求書',
            'tag_names' => [],
            'items' => $items,
        ];

        try {
            $response = $this->client->post($this->apiUrl . '/invoice_template_billings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
            Log::error('Failed to create Money Forward invoice (local): ' . $e->getMessage(), [
                'status' => $status,
                'response' => $body,
                'payload' => $payload,
            ]);
            return [
                'error_message' => $e->getMessage(),
                'status' => $status,
                'response' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Money Forward invoice (local): ' . $e->getMessage());
            report($e);
            return ['error_message' => $e->getMessage()];
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
                        $excise = 'eight_percent_reduced';
                        break;
                    case 'exempt':
                        $excise = 'untaxable';
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

        // Safely resolve dates (fallbacks if null)
        $issue = $estimate->issue_date ? Carbon::parse($estimate->issue_date) : Carbon::now();
        $expired = $estimate->due_date ? Carbon::parse($estimate->due_date) : (clone $issue)->addMonth();

        if ($expired->lte($issue)) {
            $expired = (clone $issue)->addMonth();
        }

        // Ensure quote_number length within MF limit (<= 30 chars)
        $quoteNumber = $estimate->estimate_number;
        if (is_string($quoteNumber) && strlen($quoteNumber) > 30) {
            $quoteNumber = substr($quoteNumber, 0, 30);
        }

        $quoteData = [
            'department_id' => $estimate->mf_department_id,
            'partner_id' => $estimate->client_id,
            'quote_number' => $quoteNumber,
            'title' => $estimate->title,
            'memo' => $estimate->internal_memo ?? '',
            'quote_date' => $issue->format('Y-m-d'),
            'expired_date' => $expired->format('Y-m-d'),
            'note' => $estimate->notes,
            'document_name' => '見積書',
            'items' => $items,
        ];

        try {
            $response = $this->client->post($this->apiUrl . '/quotes', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $quoteData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
            Log::error('Failed to create Money Forward quote: ' . $e->getMessage(), [
                'status' => $status,
                'response' => $body,
                'payload' => $quoteData,
            ]);
            return [
                'error_message' => $e->getMessage(),
                'status' => $status,
                'response' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Money Forward quote: ' . $e->getMessage());
            report($e);
            return ['error_message' => $e->getMessage()];
        }
    }

    public function convertQuoteToBilling($quoteId, $accessToken)
    {
        try {
            $response = $this->client->post($this->apiUrl . "/quotes/{$quoteId}/convert_to_billing", [
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

    public function fetchAllPartners(string $accessToken): array
    {
        $all = [];
        $page = 1;
        $perPage = 100;
        try {
            while (true) {
                $url = $this->apiUrl . '/partners';
                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $perPage,
                    ],
                ]);
                $status = $response->getStatusCode();
                if ($status !== 200) {
                    Log::error('MF partners fetch non-200', ['status' => $status]);
                    break;
                }
                $body = json_decode($response->getBody()->getContents(), true);
                $data = $body['data'] ?? [];
                $pagination = $body['pagination'] ?? ['current_page' => $page, 'total_pages' => $page];

                foreach ($data as $p) { $all[] = $p; }

                if (($pagination['current_page'] ?? $page) >= ($pagination['total_pages'] ?? $page)) {
                    break;
                }
                $page++;
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Money Forward partners: ' . $e->getMessage());
            report($e);
        }
        return $all;
    }

    public function fetchPartnerDetail(string $partnerId, string $accessToken): ?array
    {
        try {
            $response = $this->client->get($this->apiUrl . "/partners/{$partnerId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                Log::warning('MF partner detail non-200', ['id' => $partnerId, 'status' => $response->getStatusCode()]);
                return null;
            }
            $body = json_decode($response->getBody()->getContents(), true);
            // Some APIs wrap data; handle both
            return $body['data'] ?? $body;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Money Forward partner detail: ' . $e->getMessage(), ['id' => $partnerId]);
            report($e);
            return null;
        }
    }

    public function getItems(string $accessToken, array $query = []): ?array
    {
        $allItems = [];
        $page = 1;
        $perPage = 100;

        try {
            while (true) {
                $response = $this->client->get($this->apiUrl . '/items', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ],
                    'query' => array_merge($query, [
                        'page' => $page,
                        'per_page' => $perPage,
                    ]),
                ]);

                if ($response->getStatusCode() !== 200) {
                    Log::error('Money Forward items fetch failed with status: ' . $response->getStatusCode());
                    return null;
                }

                $body = json_decode($response->getBody()->getContents(), true);
                $data = $body['data'] ?? [];
                $pagination = $body['pagination'] ?? [];

                $allItems = array_merge($allItems, $data);

                if (empty($pagination) || $pagination['current_page'] >= $pagination['total_pages']) {
                    break;
                }

                $page++;
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Money Forward items: ' . $e->getMessage());
            report($e);
            return null;
        }

        return $allItems;
    }

    public function createItem(string $accessToken, array $data): ?array
    {
        try {
            $response = $this->client->post($this->apiUrl . '/items', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logRequestException($e, 'create');
            return null;
        }
    }

    public function updateItem(string $accessToken, string $itemId, array $data): ?array
    {
        try {
            $response = $this->client->put($this->apiUrl . "/items/{$itemId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 404) {
                return ['error' => 'not_found']; // Special return for 404
            }
            $this->logRequestException($e, 'update');
            return null;
        }
    }

    public function deleteItem(string $accessToken, string $itemId): bool
    {
        try {
            $response = $this->client->delete($this->apiUrl . "/items/{$itemId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            $status = $response->getStatusCode();
            return in_array($status, [200, 202, 204], true);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 404) {
                return true;
            }
            $this->logRequestException($e, 'delete');
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete Money Forward item: ' . $e->getMessage(), [
                'item_id' => $itemId,
            ]);
            report($e);
            return false;
        }
    }

    private function logRequestException(RequestException $e, string $action): void
    {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 'N/A';
        $responseBody = $response ? $response->getBody()->getContents() : 'N/A';

        Log::error("Failed to {$action} Money Forward item", [
            'statusCode' => $statusCode,
            'response' => $responseBody,
            'request' => $e->getRequest(),
        ]);
    }
}
