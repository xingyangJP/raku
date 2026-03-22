<?php

namespace App\Console\Commands;

use App\Models\MaintenanceFeeSnapshot;
use App\Services\MaintenanceFeeSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'maintenance:refresh-snapshots')]
class RefreshMaintenanceSnapshots extends Command
{
    protected $signature = 'maintenance:refresh-snapshots
        {--month= : 対象月 (YYYY-MM)}
        {--from= : 開始月 (YYYY-MM)}
        {--to= : 終了月 (YYYY-MM)}
        {--legacy-only : demo または last_synced_at が空の古い snapshot のみ補正する}
        {--dry-run : 件数確認のみ行い、更新しない}';

    protected $description = '保守売上 snapshot を API ベースで再同期する';

    public function __construct(
        private readonly MaintenanceFeeSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = MaintenanceFeeSnapshot::query()->orderBy('month');

        try {
            $this->applyMonthRangeFilter($query);
        } catch (\Throwable) {
            $this->error('月指定が不正です。YYYY-MM 形式で指定してください。');

            return self::FAILURE;
        }

        if ($this->option('legacy-only')) {
            $query->where(function ($builder) {
                $builder
                    ->where('source', MaintenanceFeeSyncService::SNAPSHOT_SOURCE_DEMO)
                    ->orWhere(function ($legacyApi) {
                        $legacyApi
                            ->where('source', MaintenanceFeeSyncService::SNAPSHOT_SOURCE_API)
                            ->whereNull('last_synced_at');
                    });
            });
        }

        $snapshots = $query->get();
        if ($snapshots->isEmpty()) {
            $this->warn('対象の snapshot がありません。');

            return self::SUCCESS;
        }

        $this->info("対象 snapshot: {$snapshots->count()} 件");

        if ($this->option('dry-run')) {
            foreach ($snapshots as $snapshot) {
                $this->line(sprintf(
                    '%s | source=%s | last_synced_at=%s',
                    $snapshot->month?->format('Y-m-d') ?? 'unknown',
                    $snapshot->source ?? 'null',
                    optional($snapshot->last_synced_at)->format('Y-m-d H:i:s') ?? 'null'
                ));
            }

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($snapshots as $snapshot) {
            $result = $this->syncService->syncSnapshot($snapshot->fresh('items'));
            if ($result['error']) {
                $this->error(sprintf(
                    '%s の再同期に失敗: %s',
                    $snapshot->month?->format('Y-m-d') ?? 'unknown',
                    $result['error']
                ));

                return self::FAILURE;
            }

            $updated++;
            $refreshed = $result['snapshot'];
            $this->info(sprintf(
                '%s を再同期: %d 件 / ¥%s',
                $snapshot->month?->format('Y-m-d') ?? 'unknown',
                $refreshed?->items->count() ?? 0,
                number_format((float) ($refreshed?->total_fee ?? 0))
            ));
        }

        $this->info("完了: {$updated} 件の snapshot を再同期しました。");

        return self::SUCCESS;
    }

    private function applyMonthRangeFilter($query): void
    {
        $month = $this->option('month');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($month) {
            $target = Carbon::parse($month)->startOfMonth();
            $query->whereDate('month', $target);

            return;
        }

        if ($from) {
            $query->whereDate('month', '>=', Carbon::parse($from)->startOfMonth());
        }

        if ($to) {
            $query->whereDate('month', '<=', Carbon::parse($to)->startOfMonth());
        }
    }
}
