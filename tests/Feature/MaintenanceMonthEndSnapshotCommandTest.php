<?php

namespace Tests\Feature;

use App\Models\MaintenanceFeeSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaintenanceMonthEndSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_month_end_command_skips_when_today_is_not_last_day_and_month_is_not_explicitly_specified(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 30)->setTime(23, 55));

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '顧客A', 'maintenance_fee' => 180000, 'status' => 'active', 'support_type' => '運用保守'],
            ], 200),
        ]);

        $this->artisan('maintenance:capture-month-end')
            ->expectsOutput('本日 2026-03-30 は月末日ではないため、snapshot 取得をスキップしました。')
            ->assertSuccessful();

        $this->assertNull(MaintenanceFeeSnapshot::query()->first());
    }

    public function test_month_end_command_creates_snapshot_from_api_when_missing(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(23, 55));

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '顧客A', 'maintenance_fee' => 180000, 'status' => 'active', 'support_type' => '運用保守'],
                ['customer_name' => '顧客B', 'maintenance_fee' => 90000, 'status' => 'active', 'support_type' => '監視'],
            ], 200),
        ]);

        $this->artisan('maintenance:capture-month-end', ['--month' => '2026-03'])
            ->expectsOutput('2026-03 の月末 snapshot を保存しました: 2 件 / ¥270,000 / source=api')
            ->assertSuccessful();

        $snapshot = MaintenanceFeeSnapshot::query()
            ->with('items')
            ->whereDate('month', '2026-03-01')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('api', $snapshot->source);
        $this->assertSame(270000.0, (float) $snapshot->total_fee);
        $this->assertSame(2, $snapshot->items->count());
        $this->assertNotNull($snapshot->last_synced_at);
    }

    public function test_month_end_command_skips_manual_snapshot_without_force(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(23, 55));

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '顧客A', 'maintenance_fee' => 180000, 'status' => 'active', 'support_type' => '運用保守'],
            ], 200),
        ]);

        $snapshot = MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-03-01',
            'total_fee' => 500000,
            'total_gross' => 500000,
            'source' => 'manual',
            'last_synced_at' => now()->subDay(),
        ]);
        $snapshot->items()->create([
            'customer_name' => '手修正顧客',
            'maintenance_fee' => 500000,
            'status' => 'active',
            'support_type' => 'フルサポート',
            'entry_source' => 'manual',
        ]);

        $this->artisan('maintenance:capture-month-end', ['--month' => '2026-03'])
            ->expectsOutput('2026-03 は手修正を含むため自動更新をスキップしました。必要なら --force を指定してください。')
            ->assertSuccessful();

        $snapshot->refresh();

        $this->assertSame('manual', $snapshot->source);
        $this->assertSame(500000.0, (float) $snapshot->total_fee);
        $this->assertSame(1, $snapshot->items()->count());
    }
}
