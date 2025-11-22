<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class MaintenanceFeeSnapshotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cutoff = Carbon::create(2025, 11, 22)->startOfMonth();
        $startDateStr = Estimate::min('issue_date');
        $start = $startDateStr
            ? Carbon::parse($startDateStr)->startOfMonth()
            : $cutoff->copy()->subMonths(12);

        $total = $this->fetchCurrentMaintenanceTotal();

        $month = $start->copy();
        while ($month->lte($cutoff)) {
            $monthKey = $month->toDateString();
            MaintenanceFeeSnapshot::updateOrCreate(
                ['month' => $monthKey],
                [
                    'total_fee' => $total,
                    'total_gross' => $total,
                    'source' => 'seed:2025-11-22',
                ]
            );
            $month->addMonth();
        }
    }

    private function fetchCurrentMaintenanceTotal(): float
    {
        $base = rtrim((string) env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/public/api'), '/');
        $token = (string) env('EXTERNAL_API_TOKEN', '');

        $total = 0.0;
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ])->withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get($base . '/customers');

            if ($response->successful()) {
                $customers = $response->json();
                if (is_array($customers)) {
                    foreach ($customers as $c) {
                        $fee = (float) ($c['maintenance_fee'] ?? 0);
                        if ($fee <= 0) { continue; }
                        $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
                        if ($status !== '' && (mb_stripos($status, '休止') !== false || mb_strtolower($status) === 'inactive')) { continue; }
                        $total += $fee;
                    }
                }
            } else {
                $this->command?->warn('Failed to fetch maintenance customers: HTTP ' . $response->status());
            }
        } catch (\Throwable $e) {
            $this->command?->error('Error fetching maintenance customers: ' . $e->getMessage());
        }

        return $total;
    }
}
