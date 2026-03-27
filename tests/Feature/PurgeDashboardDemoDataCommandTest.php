<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeDashboardDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_demo_data_without_deleting_on_dry_run(): void
    {
        User::factory()->create();

        Estimate::factory()->create([
            'estimate_number' => 'DEMO-DASH-202603-DEV-C',
        ]);
        MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-03-01',
            'total_fee' => 755000,
            'total_gross' => 755000,
            'source' => 'dashboard_demo_seed_v1',
        ]);

        $this->artisan('dashboard:purge-demo-data', ['--dry-run' => true])
            ->expectsOutput('対象見積: 1 件')
            ->expectsOutput('対象スナップショット: 1 件')
            ->expectsOutput('dry-run のため削除は実行していません。')
            ->assertSuccessful();

        $this->assertDatabaseCount('estimates', 1);
        $this->assertDatabaseCount('maintenance_fee_snapshots', 1);
    }

    public function test_it_deletes_only_dashboard_demo_seed_data(): void
    {
        User::factory()->create();

        Estimate::factory()->create([
            'estimate_number' => 'DEMO-DASH-202603-DEV-C',
        ]);
        Estimate::factory()->create([
            'estimate_number' => 'EST-REAL-001',
        ]);

        $demoSnapshot = MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-03-01',
            'total_fee' => 755000,
            'total_gross' => 755000,
            'source' => 'dashboard_demo_seed_v1',
        ]);
        $demoSnapshot->items()->create([
            'customer_name' => 'Demo Customer',
            'maintenance_fee' => 755000,
            'status' => 'active',
            'support_type' => 'フルサポート',
        ]);

        MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-04-01',
            'total_fee' => 614523,
            'total_gross' => 614523,
            'source' => 'api',
        ]);

        $this->artisan('dashboard:purge-demo-data')
            ->expectsOutput('対象見積: 1 件')
            ->expectsOutput('対象スナップショット: 1 件')
            ->expectsOutput('削除完了: 見積 1 件 / スナップショット 1 件')
            ->assertSuccessful();

        $this->assertDatabaseMissing('estimates', [
            'estimate_number' => 'DEMO-DASH-202603-DEV-C',
        ]);
        $this->assertDatabaseHas('estimates', [
            'estimate_number' => 'EST-REAL-001',
        ]);
        $this->assertDatabaseMissing('maintenance_fee_snapshots', [
            'source' => 'dashboard_demo_seed_v1',
        ]);
        $this->assertDatabaseHas('maintenance_fee_snapshots', [
            'source' => 'api',
        ]);
    }
}
