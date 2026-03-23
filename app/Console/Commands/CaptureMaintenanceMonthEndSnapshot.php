<?php

namespace App\Console\Commands;

use App\Models\MaintenanceFeeSnapshot;
use App\Services\MaintenanceFeeSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'maintenance:capture-month-end')]
class CaptureMaintenanceMonthEndSnapshot extends Command
{
    protected $signature = 'maintenance:capture-month-end
        {--month= : 対象月 (YYYY-MM)。未指定時は当月}
        {--force : manual / mixed snapshot も強制再同期する}';

    protected $description = '保守売上の月末 snapshot を API から確定保存する';

    public function __construct(
        private readonly MaintenanceFeeSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $monthInput = (string) ($this->option('month') ?? '');
        $month = $this->resolveTargetMonth($monthInput);

        if (!$month) {
            $this->error('月指定が不正です。YYYY-MM 形式で指定してください。');

            return self::FAILURE;
        }

        $snapshot = MaintenanceFeeSnapshot::query()
            ->with('items')
            ->whereDate('month', $month->copy()->startOfMonth())
            ->first();

        if ($snapshot && !$this->option('force') && $this->syncService->shouldProtectFromAutomatedRefresh($snapshot)) {
            $this->warn(sprintf(
                '%s は手修正を含むため自動更新をスキップしました。必要なら --force を指定してください。',
                $month->format('Y-m')
            ));

            return self::SUCCESS;
        }

        $result = $snapshot
            ? $this->syncService->syncSnapshot($snapshot)
            : $this->syncService->createSnapshotFromApi($month);

        if ($result['error']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        /** @var MaintenanceFeeSnapshot|null $captured */
        $captured = $result['snapshot'];

        $this->info(sprintf(
            '%s の月末 snapshot を保存しました: %d 件 / ¥%s / source=%s',
            $month->format('Y-m'),
            $captured?->items->count() ?? 0,
            number_format((float) ($captured?->total_fee ?? 0)),
            $captured?->source ?? 'unknown'
        ));

        return self::SUCCESS;
    }

    private function resolveTargetMonth(string $monthInput): ?Carbon
    {
        if ($monthInput === '') {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
