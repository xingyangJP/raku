<?php

namespace Tests\Feature;

use App\Models\MaintenanceFeeSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshMaintenanceSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_only_refresh_updates_demo_and_stale_api_snapshots(): void
    {
        User::factory()->create();

        $staleApi = MaintenanceFeeSnapshot::query()->create([
            'month' => '2025-04-01',
            'total_fee' => 725000,
            'total_gross' => 725000,
            'source' => 'api',
            'last_synced_at' => null,
        ]);
        $staleApi->items()->create([
            'customer_name' => '旧API',
            'maintenance_fee' => 725000,
            'status' => 'active',
            'support_type' => '運用保守',
            'entry_source' => 'api',
        ]);

        $demo = MaintenanceFeeSnapshot::query()->create([
            'month' => '2025-05-01',
            'total_fee' => 690000,
            'total_gross' => 690000,
            'source' => 'dashboard_demo_seed_v1',
            'last_synced_at' => null,
        ]);
        $demo->items()->create([
            'customer_name' => '旧DEMO',
            'maintenance_fee' => 690000,
            'status' => 'active',
            'support_type' => '監視',
            'entry_source' => 'api',
        ]);

        $freshApi = MaintenanceFeeSnapshot::query()->create([
            'month' => '2025-06-01',
            'total_fee' => 614523,
            'total_gross' => 614523,
            'source' => 'api',
            'last_synced_at' => now(),
        ]);
        $freshApi->items()->create([
            'customer_name' => '最新API',
            'maintenance_fee' => 614523,
            'status' => 'active',
            'support_type' => 'フルサポート',
            'entry_source' => 'api',
        ]);

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '本番A', 'maintenance_fee' => 300000, 'status' => 'active', 'support_type' => 'フルサポート'],
                ['customer_name' => '本番B', 'maintenance_fee' => 120000, 'status' => 'active', 'support_type' => '監視'],
            ], 200),
        ]);

        $this->artisan('maintenance:refresh-snapshots', ['--legacy-only' => true])
            ->expectsOutput('対象 snapshot: 2 件')
            ->assertSuccessful();

        $staleApi->refresh();
        $demo->refresh();
        $freshApi->refresh();

        $this->assertSame(2, $staleApi->items()->count());
        $this->assertSame(420000.0, (float) $staleApi->total_fee);
        $this->assertNotNull($staleApi->last_synced_at);

        $this->assertSame('api', $demo->source);
        $this->assertSame(2, $demo->items()->count());
        $this->assertNotNull($demo->last_synced_at);

        $this->assertSame(1, $freshApi->items()->count());
        $this->assertSame(614523.0, (float) $freshApi->total_fee);
    }
}
