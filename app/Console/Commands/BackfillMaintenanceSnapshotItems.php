<?php

namespace App\Console\Commands;

use App\Models\MaintenanceFeeSnapshot;
use App\Models\MaintenanceFeeSnapshotItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'maintenance:backfill-items')]
class BackfillMaintenanceSnapshotItems extends Command
{
    protected $signature = 'maintenance:backfill-items {--month=} {--from=} {--to=} {--force : 既存明細があっても再取り込みする}';

    protected $description = 'maintenance_fee_snapshots に紐づく明細を外部APIから1回取り込む';

    public function handle(): int
    {
        $monthOpt = $this->option('month');
        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');
        $force = (bool) $this->option('force');

        $query = MaintenanceFeeSnapshot::query();
        try {
            if ($monthOpt) {
                $month = Carbon::parse($monthOpt)->startOfMonth();
                $query->whereDate('month', $month);
            } else {
                if ($fromOpt) {
                    $from = Carbon::parse($fromOpt)->startOfMonth();
                    $query->whereDate('month', '>=', $from);
                }
                if ($toOpt) {
                    $to = Carbon::parse($toOpt)->startOfMonth();
                    $query->whereDate('month', '<=', $to);
                }
            }
        } catch (\Throwable $e) {
            $this->error('Invalid month format. Use YYYY-MM or YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $snapshots = $query->orderBy('month')->get();
        if ($snapshots->isEmpty()) {
            $this->warn('対象のスナップショットがありません。');
            return Command::SUCCESS;
        }

        $customers = $this->fetchCustomers();
        if (empty($customers)) {
            $this->warn('外部APIから顧客を取得できませんでした。トークンやURLを確認してください。');
            return Command::FAILURE;
        }

        $imported = 0;
        foreach ($snapshots as $snapshot) {
            if (!$force && $snapshot->items()->exists()) {
                $this->line("{$snapshot->month->toDateString()} は既に明細があるためスキップ");
                continue;
            }
            $rows = $this->buildRows($snapshot->id, $customers);
            MaintenanceFeeSnapshotItem::where('maintenance_fee_snapshot_id', $snapshot->id)->delete();
            if (!empty($rows)) {
                MaintenanceFeeSnapshotItem::insert($rows);
                $sum = array_sum(array_column($rows, 'maintenance_fee'));
                $snapshot->update(['total_fee' => $sum, 'total_gross' => $sum]);
                $imported++;
                $this->info("{$snapshot->month->toDateString()} に " . count($rows) . " 件取り込み");
            } else {
                $this->warn("{$snapshot->month->toDateString()} は取り込み対象がありませんでした。");
            }
        }

        $this->info("完了: {$imported} 件のスナップショットを処理しました。");
        return Command::SUCCESS;
    }

    private function fetchCustomers(): array
    {
        $base = rtrim((string) env('EXTERNAL_API_BASE', ''), '/');
        $token = (string) env('EXTERNAL_API_TOKEN', '');
        if ($base === '') {
            $this->error('EXTERNAL_API_BASE が未設定です。');
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ])->withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get($base . '/customers');

            if (!$response->successful()) {
                $this->error('外部API呼び出しに失敗: HTTP ' . $response->status());
                return [];
            }

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            $this->error('外部API呼び出しエラー: ' . $e->getMessage());
            return [];
        }
    }

    private function buildRows(int $snapshotId, array $customers): array
    {
        $rows = [];
        $now = now();
        foreach ($customers as $c) {
            $fee = (float) ($c['maintenance_fee'] ?? 0);
            $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
            if ($status !== '' && (
                mb_stripos($status, '休止') !== false ||
                mb_strtolower($status) === 'inactive'
            )) {
                continue;
            }
            if ($fee <= 0) {
                continue;
            }
            $rawSupport = $c['support_type'] ?? '';
            $supportStr = is_array($rawSupport) ? implode(' ', $rawSupport) : (string) $rawSupport;
            $rows[] = [
                'maintenance_fee_snapshot_id' => $snapshotId,
                'customer_name' => $c['customer_name'] ?? '',
                'maintenance_fee' => $fee,
                'status' => $status,
                'support_type' => $supportStr,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $rows;
    }
}
