<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\MfToken;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MoneyForwardQuoteSynchronizer
{
    private array $staffCacheByExternalId = [];
    private array $staffCacheById = [];
    private array $staffCacheByEmail = [];
    private array $productCacheByMfId = [];
    private array $productCacheBySku = [];
    private array $mfItemCacheById = [];
    private array $mfItemCacheByCode = [];
    private bool $mfItemCacheLoaded = false;
    private ?string $mfItemCacheToken = null;

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
            $tokenUserIds = MfToken::query()->pluck('user_id')->all();
            if (empty($tokenUserIds)) {
                return [
                    'status' => 'unauthorized',
                    'message' => 'Money Forwardのアクセストークンが登録されていません。',
                    'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
                ];
            }

            $totalSynced = 0;
            $aggregatedQuoteIds = [];
            $hadFailure = false;
            $hadSuccess = false;

            foreach (array_unique($tokenUserIds) as $tokenUserId) {
                $accessToken = $this->apiService->getValidAccessToken($tokenUserId, 'mfc/invoice/data.read');
                if (!$accessToken) {
                    Log::warning('Money Forward access token unavailable for user during sync.', [
                        'token_user_id' => $tokenUserId,
                    ]);
                    $hadFailure = true;
                    continue;
                }

                $result = $this->syncQuotesForToken($accessToken);
                if ($result['failed']) {
                    $hadFailure = true;
                    continue;
                }

                $hadSuccess = true;
                $totalSynced += $result['count'];
                if (!empty($result['synced_quote_ids'])) {
                    $aggregatedQuoteIds = array_merge($aggregatedQuoteIds, $result['synced_quote_ids']);
                }
            }

            if (!$hadSuccess) {
                return [
                    'status' => 'unauthorized',
                    'message' => '有効なMoney Forwardアクセストークンがありません。再認証してください。',
                    'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
                ];
            }

            if (!$hadFailure && !empty($aggregatedQuoteIds)) {
                $this->markMissingQuotesAsDeleted(array_values(array_unique($aggregatedQuoteIds)));
            }

            if ($hadFailure) {
                return [
                    'status' => 'error',
                    'message' => '一部のMoney Forwardアカウントとの同期に失敗しました。',
                    'count' => $totalSynced,
                    'synced_at' => Cache::get($this->lastSyncedCacheKey($userId)),
                ];
            }

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

    private function syncQuotesForToken(string $accessToken): array
    {
        $this->ensureMfItemCache($accessToken);
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

        return [
            'count' => $totalSynced,
            'synced_quote_ids' => $syncedQuoteIds,
            'failed' => $fetchFailed,
        ];
    }

    private function updateEstimate(array $quoteData): ?Estimate
    {
        $quoteId = Arr::get($quoteData, 'id');
        if (!$quoteId) {
            return null;
        }

        $quoteNumber = Arr::get($quoteData, 'quote_number');

        $linkSource = null;
        $estimate = Estimate::where('mf_quote_id', $quoteId)->first();
        if ($estimate) {
            $linkSource = 'mf_quote_id';
        }

        $normalizedQuoteNumber = $this->normalizeQuoteNumber($quoteNumber);

        if (!$estimate && $quoteNumber) {
            $estimate = Estimate::where('estimate_number', $quoteNumber)->first();
            if ($estimate) {
                $linkSource = 'estimate_number';
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
                $linkSource = 'normalized_quote_number';
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
            if (!$linkSource) {
                $linkSource = $attributes ? 'first_or_new_estimate_number' : 'first_or_new_mf_quote_id';
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

        $staffResolution = $this->resolveStaffFromQuote($quoteData, $payload['staff_name'] ?? null);
        if (!is_null($staffResolution['staff_id'] ?? null)) {
            $payload['staff_id'] = $staffResolution['staff_id'];
        }
        if (!empty($staffResolution['staff_name']) && empty($payload['staff_name'])) {
            $payload['staff_name'] = $staffResolution['staff_name'];
        }
        $staffMatchSource = $staffResolution['matched_via'] ?? null;
        if (empty($payload['staff_id']) && !empty($payload['estimate_number'])) {
            $parsedStaff = $this->extractStaffIdFromEstimateNumber($payload['estimate_number']);
            if (!is_null($parsedStaff)) {
                $payload['staff_id'] = $parsedStaff;
                $staffMatchSource = $staffMatchSource ?: 'estimate_number';
                Log::info('MF quote staff matched via estimate number', [
                    'quote_id' => $quoteId,
                    'estimate_number' => $payload['estimate_number'],
                    'staff_id' => $parsedStaff,
                ]);
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
                $product = $this->resolveProductFromQuoteItem($item, [
                    'quote_id' => $quoteId,
                    'index' => $index,
                ]);
                if ($product) {
                    $itemPayload['product_id'] = $product->id;
                    $nameCandidate = $itemPayload['name'] ?? '';
                    if ($nameCandidate === '' || str_starts_with($nameCandidate, '項目')) {
                        $itemPayload['name'] = $product->name;
                    }
                    if (empty($itemPayload['unit']) && !is_null($product->unit)) {
                        $itemPayload['unit'] = $product->unit;
                    }
                    if (empty($itemPayload['description']) && !is_null($product->description)) {
                        $itemPayload['description'] = $product->description;
                        $itemPayload['detail'] = $product->description;
                    }
                    if (!array_key_exists('cost', $itemPayload) && !is_null($product->cost)) {
                        $itemPayload['cost'] = (float) $product->cost;
                    }
                }
                $items[] = $itemPayload;
            }
        }

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        Log::debug('MF quote sync applying payload', [
            'quote_id' => $quoteId,
            'estimate_id' => $estimate->id ?? null,
            'estimate_exists' => $estimate->exists,
            'link_source' => $linkSource,
            'staff_id' => $payload['staff_id'] ?? null,
            'staff_match_source' => $staffMatchSource,
            'item_count' => count($items),
        ]);

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

    private function ensureMfItemCache(string $accessToken): void
    {
        if ($this->mfItemCacheLoaded && $this->mfItemCacheToken === $accessToken) {
            return;
        }

        $items = $this->apiService->getItems($accessToken);
        if (!is_array($items)) {
            Log::warning('MF quote sync: failed to build item cache.');
            $this->mfItemCacheLoaded = true;
            $this->mfItemCacheToken = $accessToken;
            return;
        }

        $this->mfItemCacheById = [];
        $this->mfItemCacheByCode = [];

        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                $this->mfItemCacheById[$id] = $item;
            }
            $code = trim((string) ($item['code'] ?? ''));
            if ($code !== '') {
                $upper = strtoupper($code);
                $this->mfItemCacheByCode[$upper] = $item;
            }
        }

        Log::info('MF quote sync: item cache loaded', [
            'count' => count($this->mfItemCacheById),
        ]);

        $this->mfItemCacheLoaded = true;
        $this->mfItemCacheToken = $accessToken;
    }

    private function resolveStaffFromQuote(array $quoteData, ?string $currentStaffName = null): array
    {
        $memberName = trim((string) Arr::get($quoteData, 'member_name', ''));
        $memo = Arr::get($quoteData, 'memo');
        $fallbackName = $currentStaffName ?: ($memberName !== '' ? $memberName : null);

        if (!$fallbackName && is_string($memo) && $memo !== '') {
            if (preg_match('/自社担当[:：]\\s*(.+)/u', $memo, $matches)) {
                $fallbackName = trim($matches[1]);
            }
        }

        $lookups = [];

        $memberCode = Arr::get($quoteData, 'member_code');
        if ($memberCode !== null && (string) $memberCode !== '') {
            $lookups[] = ['type' => 'external', 'value' => (string) $memberCode];
        }

        $memberId = Arr::get($quoteData, 'member_id');
        if ($memberId !== null && (string) $memberId !== '') {
            $memberIdStr = (string) $memberId;
            $lookups[] = ['type' => 'external', 'value' => $memberIdStr];
            if (ctype_digit($memberIdStr)) {
                $lookups[] = ['type' => 'id', 'value' => $memberIdStr];
            }
        }

        $memberEmail = Arr::get($quoteData, 'member_email');
        if ($memberEmail !== null && trim((string) $memberEmail) !== '') {
            $lookups[] = ['type' => 'email', 'value' => (string) $memberEmail];
        }

        foreach ($lookups as $lookup) {
            $user = match ($lookup['type']) {
                'external' => $this->getUserByExternalId($lookup['value']),
                'id' => $this->getUserById((int) $lookup['value']),
                'email' => $this->getUserByEmail($lookup['value']),
                default => null,
            };

            if ($user) {
                Log::info('MF quote staff matched', [
                    'quote_id' => Arr::get($quoteData, 'id'),
                    'matched_via' => $lookup['type'],
                    'lookup_value' => $lookup['value'],
                    'user_id' => $user->id,
                    'user_external_id' => $user->external_user_id,
                ]);
                return [
                    'staff_id' => $user->id,
                    'staff_name' => $currentStaffName ?: ($user->name ?: $fallbackName),
                    'matched_via' => $lookup['type'],
                ];
            }
        }

        Log::warning('MF quote staff unresolved', [
            'quote_id' => Arr::get($quoteData, 'id'),
            'member_id' => Arr::get($quoteData, 'member_id'),
            'member_code' => Arr::get($quoteData, 'member_code'),
            'member_email' => Arr::get($quoteData, 'member_email'),
            'member_name' => $memberName,
            'fallback_name' => $fallbackName,
        ]);

        return [
            'staff_id' => null,
            'staff_name' => $fallbackName,
            'matched_via' => null,
        ];
    }

    private function getUserByExternalId(string $externalId): ?User
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }

        if (!array_key_exists($externalId, $this->staffCacheByExternalId)) {
            $this->staffCacheByExternalId[$externalId] = User::where('external_user_id', $externalId)->first();
        }

        return $this->staffCacheByExternalId[$externalId];
    }

    private function getUserById(int $id): ?User
    {
        if (!array_key_exists($id, $this->staffCacheById)) {
            $this->staffCacheById[$id] = User::find($id);
        }

        return $this->staffCacheById[$id];
    }

    private function getUserByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        if (!array_key_exists($normalized, $this->staffCacheByEmail)) {
            $this->staffCacheByEmail[$normalized] = User::whereRaw('LOWER(email) = ?', [$normalized])->first();
        }

        return $this->staffCacheByEmail[$normalized];
    }

    private function resolveProductFromQuoteItem(array $item, array $context = []): ?Product
    {
        $selectColumns = ['id', 'name', 'unit', 'description', 'cost'];
        $quoteId = $context['quote_id'] ?? null;
        $index = $context['index'] ?? null;
        $itemName = Arr::get($item, 'name');
        $codeCandidates = [];
        $remoteItem = null;

        $mfItemId = Arr::get($item, 'id') ?? Arr::get($item, 'item_id');
        if ($mfItemId !== null && (string) $mfItemId !== '') {
            $mfItemIdStr = (string) $mfItemId;
            if (!array_key_exists($mfItemIdStr, $this->productCacheByMfId)) {
                $this->productCacheByMfId[$mfItemIdStr] = Product::query()
                    ->select($selectColumns)
                    ->where('mf_id', $mfItemIdStr)
                    ->first();
            }
            $matched = $this->productCacheByMfId[$mfItemIdStr];
            if ($matched) {
                Log::info('MF quote item matched product', [
                    'quote_id' => $quoteId,
                    'item_index' => $index,
                    'matched_via' => 'mf_id',
                    'mf_item_id' => $mfItemIdStr,
                    'item_code' => Arr::get($item, 'code'),
                    'item_name' => $itemName,
                    'product_id' => $matched->id,
                    'product_name' => $matched->name,
                ]);
                return $matched;
            }

            $remoteItem = $this->mfItemCacheById[$mfItemIdStr] ?? null;
            if ($remoteItem) {
                $remoteCode = trim((string) ($remoteItem['code'] ?? ''));
                if ($remoteCode !== '') {
                    $codeCandidates[] = $remoteCode;
                }
            }
        }

        foreach (['code', 'item_code'] as $codeKey) {
            $value = Arr::get($item, $codeKey);
            if ($value !== null) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    $codeCandidates[] = $trimmed;
                }
            }
        }

        $normalizedCandidates = [];
        foreach ($codeCandidates as $candidate) {
            $normalizedCandidates[] = $candidate;
            $upper = strtoupper($candidate);
            if ($upper !== $candidate) {
                $normalizedCandidates[] = $upper;
            }
        }

        foreach (array_unique($normalizedCandidates) as $candidate) {
            if (!array_key_exists($candidate, $this->productCacheBySku)) {
                $this->productCacheBySku[$candidate] = Product::query()
                    ->select($selectColumns)
                    ->where('sku', $candidate)
                    ->first();
            }
            $matched = $this->productCacheBySku[$candidate];
            if ($matched) {
                $matchedVia = 'sku';
                if (isset($remoteItem) && !empty($remoteItem) && isset($remoteItem['code'])) {
                    $remoteCodeUpper = strtoupper((string) ($remoteItem['code'] ?? ''));
                    if ($remoteCodeUpper === strtoupper($candidate)) {
                        $matchedVia = 'remote_item_code';
                    }
                }
                Log::info('MF quote item matched product', [
                    'quote_id' => $quoteId,
                    'item_index' => $index,
                    'matched_via' => $matchedVia,
                    'mf_item_id' => $mfItemId,
                    'item_code' => $candidate,
                    'item_name' => $itemName,
                    'product_id' => $matched->id,
                    'product_name' => $matched->name,
                ]);
                return $matched;
            }
        }

        Log::warning('MF quote item unresolved', [
            'quote_id' => $quoteId,
            'item_index' => $index,
            'mf_item_id' => $mfItemId,
            'item_codes' => $codeCandidates,
            'item_name' => $itemName,
            'unit' => Arr::get($item, 'unit'),
        ]);

        return null;
    }

    private function extractStaffIdFromEstimateNumber(string $estimateNumber): ?int
    {
        $parts = explode('-', $estimateNumber);
        if (count($parts) < 3) {
            return null;
        }

        $candidate = $parts[1] ?? null;
        if ($candidate === null || $candidate === '') {
            return null;
        }

        if (!ctype_digit($candidate)) {
            return null;
        }

        $staffId = (int) $candidate;
        if ($staffId <= 0) {
            return null;
        }

        $user = $this->getUserById($staffId);
        if (!$user) {
            Log::warning('MF quote staff parsed from estimate number not found locally', [
                'estimate_number' => $estimateNumber,
                'staff_id' => $staffId,
            ]);
            return null;
        }

        return $staffId;
    }
}
