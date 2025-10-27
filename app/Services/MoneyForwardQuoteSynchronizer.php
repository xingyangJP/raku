<?php

namespace App\Services;

use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            $token = $this->apiService->getValidAccessToken($userId, 'mfc/invoice/data.read');
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
        $syncedQuoteIds = [];

        $fetchFailed = false;

        do {
            $response = $this->apiService->fetchQuotes($accessToken, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!is_array($response)) {
                $fetchFailed = true;
                break;
            }

            $data = Arr::get($response, 'data', []);
            Log::debug('MF quote sync page result', [
                'page' => $page,
                'count' => count($data),
            ]);
            foreach ($data as $quoteData) {
                $estimate = $this->updateEstimate($quoteData);
                $quoteId = Arr::get($quoteData, 'id');
                if ($quoteId) {
                    $syncedQuoteIds[] = (string) $quoteId;
                } elseif ($estimate?->mf_quote_id) {
                    $syncedQuoteIds[] = (string) $estimate->mf_quote_id;
                }
                $totalSynced++;
            }

            $pagination = Arr::get($response, 'pagination', []);
            $currentPage = (int) Arr::get($pagination, 'current_page', $page);
            $totalPages = (int) Arr::get($pagination, 'total_pages', $page);
            $hasNext = $currentPage < $totalPages;
            $page++;
        } while (!empty($data) && $hasNext);

        if (!$fetchFailed && !empty($syncedQuoteIds)) {
            $this->markMissingQuotesAsDeleted($syncedQuoteIds);
        }

        return $totalSynced;
    }

    private function updateEstimate(array $quoteData): ?Estimate
    {
        $quoteId = Arr::get($quoteData, 'id');
        if (!$quoteId) {
            return null;
        }

        $quoteNumber = Arr::get($quoteData, 'quote_number');

        $estimate = Estimate::where('mf_quote_id', $quoteId)->first();

        $normalizedQuoteNumber = $this->normalizeQuoteNumber($quoteNumber);

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

        if (!$estimate && $normalizedQuoteNumber && $normalizedQuoteNumber !== $quoteNumber) {
            $estimate = Estimate::where('estimate_number', $normalizedQuoteNumber)->first();
            if ($estimate) {
                $estimate->mf_quote_id = $quoteId;
                $estimate->save();
                Log::info('MF quote linked to estimate by normalized quote_number', [
                    'quote_id' => $quoteId,
                    'quote_number' => $quoteNumber,
                    'normalized' => $normalizedQuoteNumber,
                    'estimate_id' => $estimate->id,
                ]);
            }
        }

        if (!$estimate) {
            $attributes = [];
            if ($normalizedQuoteNumber) {
                $attributes['estimate_number'] = $normalizedQuoteNumber;
            } elseif ($quoteNumber) {
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

        if ($normalizedQuoteNumber && (empty($estimate->estimate_number) || $estimate->estimate_number === $normalizedQuoteNumber)) {
            $payload['estimate_number'] = $normalizedQuoteNumber;
        } elseif ($quoteNumber && (empty($estimate->estimate_number) || $estimate->estimate_number === $quoteNumber)) {
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

        if ($memberId = Arr::get($quoteData, 'member_id')) {
            if (is_numeric($memberId)) {
                $payload['staff_id'] = (int) $memberId;
            }
        }

        if ($memberName = Arr::get($quoteData, 'member_name')) {
            $memberName = trim((string) $memberName);
            if ($memberName !== '') {
                $payload['staff_name'] = $memberName;
            }
        }

        if ($memo = Arr::get($quoteData, 'memo')) {
            $payload['internal_memo'] = $memo;
            if (empty($payload['staff_name'])) {
                if (preg_match('/自社担当[:：]\\s*(.+)/u', $memo, $matches)) {
                    $payload['staff_name'] = trim($matches[1]);
                }
            }
        }

        $items = [];
        foreach (Arr::get($quoteData, 'items', []) as $index => $item) {
            $quantity = Arr::has($item, 'quantity') ? (float) Arr::get($item, 'quantity') : null;
            $price = Arr::has($item, 'price') ? (float) Arr::get($item, 'price') : null;
            $cost = Arr::has($item, 'cost') ? (float) Arr::get($item, 'cost') : null;
            $excise = Arr::get($item, 'excise');
            $taxCategory = match ($excise) {
                'ten_percent' => 'standard',
                'eight_percent', 'eight_percent_reduced', 'eight_percent_as_reduced_tax_rate' => 'reduced',
                'untaxable', 'non_taxable', 'tax_exemption' => 'exempt',
                default => 'standard',
            };
            $detail = Arr::get($item, 'detail') ?? Arr::get($item, 'description');
            $name = trim((string) Arr::get($item, 'name', ''));
            if ($name === '' && is_string($detail) && $detail !== '') {
                $name = mb_substr($detail, 0, 40);
            }
            if ($name === '') {
                $name = '項目' . ($index + 1);
            }

            $itemPayload = array_filter([
                'name' => $name,
                'code' => Arr::get($item, 'code'),
                'description' => $detail,
                'detail' => $detail,
                'unit' => Arr::get($item, 'unit'),
                'price' => $price,
                'qty' => $quantity,
                'quantity' => $quantity,
                'cost' => $cost,
                'tax_category' => $taxCategory,
            ], static fn ($value) => !is_null($value));

            if (!empty($itemPayload)) {
                $items[] = $itemPayload;
            }
        }

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        if ($estimate->mf_deleted_at) {
            $estimate->mf_deleted_at = null;
        }

        $estimate->fill($payload);
        $estimate->save();

        if (!$quoteNumber && empty($estimate->estimate_number)) {
            $estimate->estimate_number = 'MF-' . $quoteId;
            $estimate->save();
        }

        return $estimate;
    }

    private function normalizeQuoteNumber(?string $quoteNumber): ?string
    {
        if (!$quoteNumber) {
            return null;
        }

        $normalized = preg_replace('/-CRM-/', '-', $quoteNumber);
        $normalized = preg_replace('/CRM/', '', $normalized ?? '');

        return $normalized ?: $quoteNumber;
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

    private function markMissingQuotesAsDeleted(array $syncedQuoteIds): void
    {
        $query = Estimate::query()
            ->whereNotNull('mf_quote_id')
            ->whereNull('mf_deleted_at');

        if (!empty($syncedQuoteIds)) {
            $query->whereNotIn('mf_quote_id', array_unique($syncedQuoteIds));
        }

        $now = Carbon::now();

        $query->chunkById(100, function ($estimates) use ($now) {
            foreach ($estimates as $estimate) {
                $estimate->mf_deleted_at = $now;
                $estimate->mf_quote_id = null;
                $estimate->mf_quote_pdf_url = null;
                if (Schema::hasColumn('estimates', 'mf_invoice_id')) {
                    $estimate->mf_invoice_id = null;
                }
                if (Schema::hasColumn('estimates', 'mf_invoice_pdf_url')) {
                    $estimate->mf_invoice_pdf_url = null;
                }
                $estimate->save();
            }
        });
    }
}
