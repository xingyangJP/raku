<?php

namespace App\Services;

use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MoneyForwardQuoteSynchronizer
{
    public function __construct(
        private readonly MoneyForwardApiService $apiService
    ) {
    }

    /**
     * 同期が古い場合のみMoney Forwardから見積書を取得してDBへ反映する。
     */
    public function syncIfStale(?int $userId = null): array
    {
        $throttleMinutes = (int) config('services.money_forward.quote_sync_throttle_minutes', 5);
        $lastSyncedAtIso = Cache::get($this->lastSyncedCacheKey($userId));
        if ($lastSyncedAtIso) {
            $lastSyncedAt = Carbon::parse($lastSyncedAtIso);
            if ($lastSyncedAt->gt(Carbon::now()->subMinutes($throttleMinutes))) {
                return [
                    'status' => 'skipped',
                    'reason' => 'throttled',
                    'synced_at' => $lastSyncedAtIso,
                ];
            }
        }

        return $this->sync($userId);
    }

    /**
     * Money Forwardから見積書を同期する。
     */
    public function sync(?int $userId = null): array
    {
        if (!Cache::add($this->lockCacheKey($userId), true, 30)) {
            return [
                'status' => 'skipped',
                'reason' => 'locked',
                'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
            ];
        }

        try {
            $token = $this->apiService->getValidAccessToken($userId);
            if (!$token) {
                return [
                    'status' => 'unauthorized',
                    'message' => 'Money Forwardのアクセストークンが取得できませんでした。',
                    'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
                ];
            }

            $totalSynced = $this->syncQuotes($token);
            $now = Carbon::now()->toIso8601String();
            Cache::forever($this->lastSyncedCacheKey($userId), $now);

            return [
                'status' => 'synced',
                'count' => $totalSynced,
                'synced_at' => $now,
            ];
        } catch (\Throwable $e) {
            Log::error('Money Forward quote sync failed', [
                'exception' => $e,
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
            ];
        } finally {
            Cache::forget($this->lockCacheKey($userId));
        }
    }

    private function syncQuotes(string $accessToken): int
    {
        $page = 1;
        $perPage = (int) config('services.money_forward.quote_sync_page_size', 100);
        $totalSynced = 0;

        do {
            $response = $this->apiService->fetchQuotes($accessToken, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!is_array($response)) {
                break;
            }

            $data = Arr::get($response, 'data', []);
            Log::debug('MF quote sync page result', [
                'page' => $page,
                'count' => count($data),
            ]);
            foreach ($data as $quoteData) {
                $this->updateEstimate($quoteData);
                $totalSynced++;
            }

            $pagination = Arr::get($response, 'pagination', []);
            $currentPage = (int) Arr::get($pagination, 'current_page', $page);
            $totalPages = (int) Arr::get($pagination, 'total_pages', $page);
            $hasNext = $currentPage < $totalPages;
            $page++;
        } while (!empty($data) && $hasNext);

        return $totalSynced;
    }

    private function updateEstimate(array $quoteData): void
    {
        $quoteId = Arr::get($quoteData, 'id');
        if (!$quoteId) {
            return;
        }

        $quoteNumber = Arr::get($quoteData, 'quote_number');

        $estimate = Estimate::where('mf_quote_id', $quoteId)->first();

        if (!$estimate && $quoteNumber) {
            $estimate = Estimate::where('estimate_number', $quoteNumber)->first();
            if ($estimate) {
                $estimate->mf_quote_id = $quoteId;
                $estimate->save();
                Log::info('MF quote linked to estimate by quote_number', [
                    'quote_id' => $quoteId,
                    'quote_number' => $quoteNumber,
                    'estimate_id' => $estimate->id,
                ]);
            }
        }

        if (!$estimate) {
            $attributes = [];
            if ($quoteNumber) {
                $attributes['estimate_number'] = $quoteNumber;
            }

            $estimate = Estimate::firstOrNew($attributes ?: ['mf_quote_id' => $quoteId]);
            $estimate->status = $estimate->status ?? 'sent';
        }

        $payload = [
            'mf_quote_id' => $quoteId,
        ];

        if ($pdfUrl = Arr::get($quoteData, 'pdf_url')) {
            $payload['mf_quote_pdf_url'] = $pdfUrl;
        }

        if ($quoteNumber && (empty($estimate->estimate_number) || $estimate->estimate_number === $quoteNumber)) {
            $payload['estimate_number'] = $quoteNumber;
        }

        if ($partnerId = Arr::get($quoteData, 'partner_id')) {
            $payload['client_id'] = $partnerId;
        }

        if ($partnerName = Arr::get($quoteData, 'partner_name')) {
            $payload['customer_name'] = $partnerName;
        }

        if ($title = Arr::get($quoteData, 'title')) {
            $payload['title'] = $title;
        } elseif ($documentName = Arr::get($quoteData, 'document_name')) {
            $payload['title'] = $documentName;
        }

        if ($quoteDate = Arr::get($quoteData, 'quote_date')) {
            try {
                $payload['issue_date'] = Carbon::parse($quoteDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                // ignore parse error
            }
        }

        if ($expiredDate = Arr::get($quoteData, 'expired_date')) {
            try {
                $payload['due_date'] = Carbon::parse($expiredDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                // ignore parse error
            }
        }

        if ($totalPrice = Arr::get($quoteData, 'total_price')) {
            $payload['total_amount'] = (int) round((float) $totalPrice);
        }

        $items = [];
        foreach (Arr::get($quoteData, 'items', []) as $item) {
            $items[] = array_filter([
                'name' => Arr::get($item, 'name'),
                'code' => Arr::get($item, 'code'),
                'detail' => Arr::get($item, 'detail'),
                'unit' => Arr::get($item, 'unit'),
                'price' => Arr::has($item, 'price') ? (float) Arr::get($item, 'price') : null,
                'quantity' => Arr::has($item, 'quantity') ? (float) Arr::get($item, 'quantity') : null,
            ], static fn ($value) => !is_null($value));
        }

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        $estimate->fill($payload);
        $estimate->save();

        if (!$quoteNumber && empty($estimate->estimate_number)) {
            $estimate->estimate_number = 'MF-' . $quoteId;
            $estimate->save();
        }
    }

    private function lastSyncedCacheKey(?int $userId = null): string
    {
        $suffix = $userId ? '_user_' . $userId : '';
        return 'mf_quotes_last_sync_at' . $suffix;
    }

    private function lockCacheKey(?int $userId = null): string
    {
        $suffix = $userId ? '_user_' . $userId : '';
        return 'mf_quotes_sync_lock' . $suffix;
    }
}
