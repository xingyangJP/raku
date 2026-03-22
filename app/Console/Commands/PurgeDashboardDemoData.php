<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'dashboard:purge-demo-data')]
class PurgeDashboardDemoData extends Command
{
    private const SNAPSHOT_SOURCE = 'dashboard_demo_seed_v1';
    private const ESTIMATE_PREFIX = 'DEMO-DASH';

    protected $signature = 'dashboard:purge-demo-data {--dry-run : 件数だけ確認して削除しない}';

    protected $description = 'ダッシュボード用 demo seed データを削除する';

    public function handle(): int
    {
        $estimateQuery = Estimate::query()
            ->where('estimate_number', 'like', self::ESTIMATE_PREFIX . '-%');
        $snapshotQuery = MaintenanceFeeSnapshot::query()
            ->where('source', self::SNAPSHOT_SOURCE);

        $estimateCount = (clone $estimateQuery)->count();
        $snapshotCount = (clone $snapshotQuery)->count();

        $this->info("対象見積: {$estimateCount} 件");
        $this->info("対象スナップショット: {$snapshotCount} 件");

        if ($this->option('dry-run')) {
            $this->comment('dry-run のため削除は実行していません。');
            return self::SUCCESS;
        }

        $deletedSnapshots = 0;
        foreach ($snapshotQuery->get() as $snapshot) {
            $snapshot->items()->delete();
            $snapshot->delete();
            $deletedSnapshots++;
        }

        $deletedEstimates = $estimateQuery->delete();

        $this->info("削除完了: 見積 {$deletedEstimates} 件 / スナップショット {$deletedSnapshots} 件");

        return self::SUCCESS;
    }
}
