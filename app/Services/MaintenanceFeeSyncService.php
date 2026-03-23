<?php

namespace App\Services;

use App\Models\MaintenanceFeeSnapshot;
use App\Models\MaintenanceFeeSnapshotItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaintenanceFeeSyncService
{
    public const SNAPSHOT_SOURCE_API = 'api';
    public const SNAPSHOT_SOURCE_MANUAL = 'manual';
    public const SNAPSHOT_SOURCE_MIXED = 'mixed';
    public const SNAPSHOT_SOURCE_DEMO = 'dashboard_demo_seed_v1';

    public const ITEM_SOURCE_API = 'api';
    public const ITEM_SOURCE_MANUAL = 'manual';

    public function parseMonth(?string $monthInput): ?Carbon
    {
        if ($monthInput === null || $monthInput === '') {
            return null;
        }

        try {
            return Carbon::parse($monthInput)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSnapshotForMonth(?string $monthInput): array
    {
        $month = $this->parseMonth($monthInput);
        if (!$month) {
            return ['snapshot' => null, 'error' => null];
        }

        $snapshot = MaintenanceFeeSnapshot::with('items')
            ->whereDate('month', $month)
            ->first();

        if ($snapshot) {
            if ($snapshot->items->isEmpty() || $this->shouldRefreshSnapshotFromApi($snapshot, $month)) {
                return $this->syncSnapshot($snapshot);
            }

            $this->refreshSnapshotMeta($snapshot);

            return ['snapshot' => $snapshot->fresh('items'), 'error' => null];
        }

        return $this->createSnapshotFromApi($month);
    }

    public function createSnapshotFromApi(Carbon $month): array
    {
        $fetch = $this->fetchCustomers();
        if ($fetch['error']) {
            return ['snapshot' => null, 'error' => $fetch['error']];
        }

        $snapshot = MaintenanceFeeSnapshot::create([
            'month' => $month,
            'total_fee' => 0,
            'total_gross' => 0,
            'source' => self::SNAPSHOT_SOURCE_API,
            'last_synced_at' => now(),
        ]);

        $rows = $this->buildRowsFromCustomers($snapshot->id, $fetch['customers']);
        if (!empty($rows)) {
            MaintenanceFeeSnapshotItem::insert($rows);
        }

        $this->refreshSnapshotMeta($snapshot->fresh('items'));

        return ['snapshot' => $snapshot->fresh('items'), 'error' => null];
    }

    public function resyncMonth(Carbon $month): array
    {
        $snapshot = MaintenanceFeeSnapshot::with('items')
            ->whereDate('month', $month->copy()->startOfMonth())
            ->first();

        if ($snapshot) {
            return $this->syncSnapshot($snapshot);
        }

        return $this->createSnapshotFromApi($month->copy()->startOfMonth());
    }

    public function syncSnapshot(MaintenanceFeeSnapshot $snapshot): array
    {
        $fetch = $this->fetchCustomers();
        if ($fetch['error']) {
            return ['snapshot' => $snapshot->fresh('items'), 'error' => $fetch['error']];
        }

        $rows = $this->buildRowsFromCustomers($snapshot->id, $fetch['customers']);

        MaintenanceFeeSnapshotItem::where('maintenance_fee_snapshot_id', $snapshot->id)->delete();
        if (!empty($rows)) {
            MaintenanceFeeSnapshotItem::insert($rows);
        }

        $snapshot->forceFill([
            'source' => self::SNAPSHOT_SOURCE_API,
            'last_synced_at' => now(),
        ])->save();

        $this->refreshSnapshotMeta($snapshot->fresh('items'));

        return ['snapshot' => $snapshot->fresh('items'), 'error' => null];
    }

    public function recalculateSnapshot(MaintenanceFeeSnapshot $snapshot): void
    {
        $sum = $snapshot->items()->sum('maintenance_fee');
        $snapshot->total_fee = $sum;
        $snapshot->total_gross = $sum;
        $snapshot->save();

        $this->updateSnapshotSource($snapshot->fresh('items'));
    }

    public function updateSnapshotSource(MaintenanceFeeSnapshot $snapshot): void
    {
        $manualCount = $snapshot->items()->where('entry_source', self::ITEM_SOURCE_MANUAL)->count();
        $apiCount = $snapshot->items()->where('entry_source', self::ITEM_SOURCE_API)->count();

        if ($manualCount > 0 && $apiCount > 0) {
            $source = self::SNAPSHOT_SOURCE_MIXED;
        } elseif ($manualCount > 0) {
            $source = self::SNAPSHOT_SOURCE_MANUAL;
        } else {
            $source = self::SNAPSHOT_SOURCE_API;
        }

        if ($snapshot->source !== $source) {
            $snapshot->forceFill(['source' => $source])->save();
        }
    }

    public function manualEditCount(?MaintenanceFeeSnapshot $snapshot): int
    {
        if (!$snapshot) {
            return 0;
        }

        return $snapshot->items->where('entry_source', self::ITEM_SOURCE_MANUAL)->count();
    }

    public function hasManualEdits(?MaintenanceFeeSnapshot $snapshot): bool
    {
        return $this->manualEditCount($snapshot) > 0;
    }

    public function shouldProtectFromAutomatedRefresh(?MaintenanceFeeSnapshot $snapshot): bool
    {
        if (!$snapshot) {
            return false;
        }

        if ($this->hasManualEdits($snapshot)) {
            return true;
        }

        return in_array($snapshot->source, [self::SNAPSHOT_SOURCE_MANUAL, self::SNAPSHOT_SOURCE_MIXED], true);
    }

    public function sourceLabel(?string $source): string
    {
        return match ($source) {
            self::SNAPSHOT_SOURCE_API => 'API',
            self::SNAPSHOT_SOURCE_MANUAL => '手修正',
            self::SNAPSHOT_SOURCE_MIXED => 'API + 手修正',
            self::SNAPSHOT_SOURCE_DEMO => 'デモデータ',
            default => '未判定',
        };
    }

    public function displaySyncedAt(?MaintenanceFeeSnapshot $snapshot): ?string
    {
        if (!$snapshot) {
            return null;
        }

        $syncedAt = $snapshot->last_synced_at;
        if (!$syncedAt && in_array($snapshot->source, [self::SNAPSHOT_SOURCE_API, self::SNAPSHOT_SOURCE_MIXED, self::SNAPSHOT_SOURCE_DEMO], true)) {
            $syncedAt = $snapshot->updated_at;
        }

        return $syncedAt?->timezone(config('app.timezone', 'Asia/Tokyo'))->format('Y-m-d H:i');
    }

    public function extractSupportTypes(Collection $items): array
    {
        return $items
            ->flatMap(fn ($item) => $this->splitSupportTypes((string) ($item['support_type'] ?? '')))
            ->unique()
            ->values()
            ->all();
    }

    public function splitSupportTypes(?string $supportType): array
    {
        return collect(preg_split('/[\s,、\/]+/u', (string) $supportType))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    public function getTotalForMonth(Carbon $month): float
    {
        $result = $this->getSnapshotForMonth($month->format('Y-m'));

        return (float) ($result['snapshot']?->total_fee ?? 0);
    }

    private function refreshSnapshotMeta(MaintenanceFeeSnapshot $snapshot): void
    {
        $this->recalculateSnapshot($snapshot);
    }

    private function shouldRefreshSnapshotFromApi(MaintenanceFeeSnapshot $snapshot, Carbon $month): bool
    {
        if ($snapshot->source !== self::SNAPSHOT_SOURCE_DEMO) {
            return false;
        }

        return true;
    }

    private function fetchCustomers(): array
    {
        $base = rtrim((string) env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/public/api'), '/');
        $token = (string) env('EXTERNAL_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ])->withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get($base . '/customers');

            if (!$response->successful()) {
                Log::warning('Failed to fetch maintenance customers', [
                    'status' => $response->status(),
                    'url' => $base . '/customers',
                    'body' => $response->body(),
                ]);

                return [
                    'customers' => [],
                    'error' => '保守売上データの取得に失敗しました。時間をおいて再試行してください。',
                ];
            }

            $json = $response->json();

            return [
                'customers' => is_array($json) ? $json : [],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Error fetching maintenance customers', [
                'message' => $e->getMessage(),
            ]);

            return [
                'customers' => [],
                'error' => '保守売上データの取得中にエラーが発生しました。',
            ];
        }
    }

    private function buildRowsFromCustomers(int $snapshotId, array $customers): array
    {
        $rows = [];
        $now = now();

        foreach ($customers as $customer) {
            $fee = (float) ($customer['maintenance_fee'] ?? 0);
            $status = (string) ($customer['status'] ?? $customer['customer_status'] ?? $customer['status_name'] ?? '');
            if ($status !== '' && (mb_stripos($status, '休止') !== false || mb_strtolower($status) === 'inactive')) {
                continue;
            }
            if ($fee <= 0) {
                continue;
            }

            $rawSupport = $customer['support_type'] ?? '';
            $support = is_array($rawSupport) ? implode(' ', $rawSupport) : (string) $rawSupport;

            $rows[] = [
                'maintenance_fee_snapshot_id' => $snapshotId,
                'customer_name' => $customer['customer_name'] ?? '',
                'maintenance_fee' => $fee,
                'status' => $status,
                'support_type' => $support,
                'entry_source' => self::ITEM_SOURCE_API,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
