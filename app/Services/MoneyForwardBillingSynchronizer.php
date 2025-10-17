<?php

namespace App\Services;

use App\Models\Billing;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MoneyForwardBillingSynchronizer
{
    public function __construct(
        private readonly MoneyForwardApiService $apiService
    ) {
    }

    /**
     * 同期が古い場合のみMoney Forwardから請求書を取得してDBへ反映する。
     */
    public function syncIfStale(?int $userId = null): array
    {
        $throttleMinutes = (int) config('services.money_forward.billing_sync_throttle_minutes', 5);
        $lastSyncedAtIso = Cache::get($this->lastSyncedCacheKey());
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
     * Money Forwardから請求書を同期する。
     */
    public function sync(?int $userId = null): array
    {
        if (!Cache::add($this->lockCacheKey(), true, 30)) {
            return [
                'status' => 'skipped',
                'reason' => 'locked',
                'synced_at' => Cache::get($this->lastSyncedCacheKey()),
            ];
        }

        try {
            $token = $this->apiService->getValidAccessToken($userId, 'mfc/invoice/data.read');
            if (!$token) {
                return [
                    'status' => 'unauthorized',
                    'message' => 'Money Forwardのアクセストークンが取得できませんでした。',
                    'synced_at' => Cache::get($this->lastSyncedCacheKey()),
                ];
            }

            $totalSynced = $this->syncBillings($token);
            $now = Carbon::now()->toIso8601String();
            Cache::forever($this->lastSyncedCacheKey(), $now);

            return [
                'status' => 'synced',
                'count' => $totalSynced,
                'synced_at' => $now,
            ];
        } catch (\Throwable $e) {
            Log::error('Money Forward billing sync failed', [
                'exception' => $e,
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'synced_at' => Cache::get($this->lastSyncedCacheKey()),
            ];
        } finally {
            Cache::forget($this->lockCacheKey());
        }
    }

    private function syncBillings(string $accessToken): int
    {
        $page = 1;
        $perPage = (int) config('services.money_forward.billing_sync_page_size', 50);
        $totalSynced = 0;
        $syncedIds = [];

        do {
            $response = $this->apiService->fetchBillings($accessToken, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!is_array($response)) {
                break;
            }

            $data = Arr::get($response, 'data', []);
            usort($data, function ($a, $b) {
                return strcmp(Arr::get($b, 'updated_at', ''), Arr::get($a, 'updated_at', ''));
            });
            $pagination = Arr::get($response, 'pagination', []);

            foreach ($data as $billingData) {
                $billingId = Arr::get($billingData, 'id');
                $billing = $this->upsertBilling($billingData);
                if ($billingId) {
                    $syncedIds[] = (string) $billingId;
                }
                if ($billingId && !empty($billingData['pdf_url'])) {
                    $this->downloadPdf((string) $billingId, $billingData['pdf_url'], $accessToken);
                }
                $totalSynced++;
            }

            $currentPage = (int) Arr::get($pagination, 'current_page', $page);
            $totalPages = (int) Arr::get($pagination, 'total_pages', $page);
            $hasNext = $currentPage < $totalPages;
            $page++;
        } while (!empty($data) && $hasNext);

        $this->markMissingBillingsAsDeleted($syncedIds);

        return $totalSynced;
    }

    private function upsertBilling(array $invoiceData): ?Billing
    {
        $billingId = Arr::get($invoiceData, 'id');
        if (!$billingId) {
            return null;
        }

        $payload = Arr::only($invoiceData, [
            'pdf_url',
            'operator_id',
            'department_id',
            'member_id',
            'member_name',
            'partner_id',
            'partner_name',
            'office_id',
            'office_name',
            'office_detail',
            'title',
            'memo',
            'payment_condition',
            'billing_date',
            'due_date',
            'sales_date',
            'billing_number',
            'note',
            'document_name',
            'payment_status',
            'email_status',
            'posting_status',
            'is_downloaded',
            'is_locked',
            'deduct_price',
            'tag_names',
            'excise_price',
            'excise_price_of_untaxable',
            'excise_price_of_non_taxable',
            'excise_price_of_tax_exemption',
            'excise_price_of_five_percent',
            'excise_price_of_eight_percent',
            'excise_price_of_eight_percent_as_reduced_tax_rate',
            'excise_price_of_ten_percent',
            'subtotal_price',
            'subtotal_of_untaxable_excise',
            'subtotal_of_non_taxable_excise',
            'subtotal_of_tax_exemption_excise',
            'subtotal_of_five_percent_excise',
            'subtotal_of_eight_percent_excise',
            'subtotal_of_eight_percent_as_reduced_tax_rate_excise',
            'subtotal_of_ten_percent_excise',
            'subtotal_with_tax_of_untaxable_excise',
            'subtotal_with_tax_of_non_taxable_excise',
            'subtotal_with_tax_of_tax_exemption_excise',
            'subtotal_with_tax_of_five_percent_excise',
            'subtotal_with_tax_of_eight_percent_excise',
            'subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise',
            'subtotal_with_tax_of_ten_percent_excise',
            'total_price',
            'registration_code',
            'use_invoice_template',
            'config',
        ]);

        $billing = Billing::withTrashed()->find($billingId);
        if ($billing) {
            $billing->fill($payload);
            if ($billing->trashed()) {
                $billing->restore();
            }
            if ($billing->mf_deleted_at) {
                $billing->mf_deleted_at = null;
            }
            $billing->save();
        } else {
            $billing = Billing::create(array_merge(['id' => $billingId], $payload));
        }

        if ($billing && !empty($invoiceData['items']) && is_iterable($invoiceData['items'])) {
            $remoteItemIds = [];
            foreach ($invoiceData['items'] as $itemData) {
                $itemId = Arr::get($itemData, 'id');
                if ($itemId) {
                    $remoteItemIds[] = (string) $itemId;
                }
                $billing->items()->updateOrCreate(
                    ['id' => $itemId],
                    [
                        'name' => Arr::get($itemData, 'name'),
                        'code' => Arr::get($itemData, 'code'),
                        'detail' => Arr::get($itemData, 'detail'),
                        'unit' => Arr::get($itemData, 'unit'),
                        'price' => Arr::get($itemData, 'price'),
                        'quantity' => Arr::get($itemData, 'quantity'),
                        'is_deduct_withholding_tax' => Arr::get($itemData, 'is_deduct_withholding_tax', false),
                        'excise' => Arr::get($itemData, 'excise'),
                        'delivery_number' => Arr::get($itemData, 'delivery_number'),
                        'delivery_date' => Arr::get($itemData, 'delivery_date'),
                    ]
                );
            }

            if (!empty($remoteItemIds)) {
                $billing->items()->whereNotIn('id', $remoteItemIds)->delete();
            } else {
                $billing->items()->delete();
            }
        } elseif ($billing) {
            $billing->items()->delete();
        }

        return $billing;
    }

    private function lastSyncedCacheKey(): string
    {
        return 'mf_billings_last_sync_at';
    }

    private function lockCacheKey(): string
    {
        return 'mf_billings_sync_lock';
    }

    private function downloadPdf(string $billingId, string $pdfUrl, string $accessToken): void
    {
        try {
            $response = Http::withToken($accessToken)->get($pdfUrl);
            if ($response->successful()) {
                Storage::put("public/billings/{$billingId}.pdf", $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to download Money Forward billing PDF', [
                'billing_id' => $billingId,
                'exception' => $e,
            ]);
        }
    }

    private function markMissingBillingsAsDeleted(array $syncedIds): void
    {
        $now = Carbon::now();

        $query = Billing::query()
            ->whereNull('mf_deleted_at');

        if (!empty($syncedIds)) {
            $query->whereNotIn('id', $syncedIds);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        Billing::whereIn('id', $ids->all())->update([
            'mf_deleted_at' => $now,
            'deleted_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
