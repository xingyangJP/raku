<?php

namespace Tests\Feature;

use App\Models\MaintenanceFeeSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MaintenanceFeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_defaults_to_current_month_when_no_snapshot_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 09:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '本番A', 'maintenance_fee' => 300000, 'status' => 'active', 'support_type' => 'フルサポート'],
                ['customer_name' => '本番B', 'maintenance_fee' => 120000, 'status' => 'active', 'support_type' => '監視'],
            ], 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-fees.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('filters.selected_month', '2026-03')
                ->where('summary.snapshot_month', '2026-03-01')
                ->where('summary.displayed_total_fee', fn ($value) => (float) $value === 420000.0)
                ->where('summary.overall_total_fee', fn ($value) => (float) $value === 420000.0)
                ->where('summary.meta.source', 'api')
                ->where('summary.meta.source_label', 'API')
                ->where('summary.meta.manual_edit_count', 0)
                ->where('api_status.kind', 'ok');
        });

        $this->assertDatabaseHas('maintenance_fee_snapshots', [
            'month' => '2026-03-01 00:00:00',
            'source' => 'api',
        ]);
    }

    public function test_demo_snapshot_is_refreshed_from_api_even_for_past_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 09:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();
        $snapshot = MaintenanceFeeSnapshot::query()->create([
            'month' => '2025-04-01',
            'total_fee' => 745000,
            'total_gross' => 745000,
            'source' => 'dashboard_demo_seed_v1',
        ]);

        $snapshot->items()->createMany([
            ['customer_name' => 'デモA', 'maintenance_fee' => 220000, 'status' => 'active', 'support_type' => 'フルサポート'],
            ['customer_name' => 'デモB', 'maintenance_fee' => 180000, 'status' => 'active', 'support_type' => '運用保守'],
            ['customer_name' => 'デモC', 'maintenance_fee' => 140000, 'status' => 'active', 'support_type' => '監視'],
            ['customer_name' => 'デモD', 'maintenance_fee' => 205000, 'status' => 'active', 'support_type' => 'ヘルプデスク'],
        ]);

        Http::fake([
            '*' => Http::response([
                ['customer_name' => '本番A', 'maintenance_fee' => 300000, 'status' => 'active', 'support_type' => 'フルサポート'],
                ['customer_name' => '本番B', 'maintenance_fee' => 120000, 'status' => '稼働中', 'support_type' => '監視'],
                ['customer_name' => '停止中', 'maintenance_fee' => 999999, 'status' => '休止', 'support_type' => '運用保守'],
                ['customer_name' => 'ゼロ円', 'maintenance_fee' => 0, 'status' => 'active', 'support_type' => '監視'],
            ], 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-fees.index', ['month' => '2025-04']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('summary.displayed_total_fee', fn ($value) => (float) $value === 420000.0)
                ->where('summary.displayed_active_count', 2)
                ->where('summary.displayed_average_fee', fn ($value) => (float) $value === 210000.0)
                ->where('summary.meta.source', 'api')
                ->where('items.0.customer_name', '本番A')
                ->where('items.0.entry_source', 'api')
                ->where('items.1.customer_name', '本番B');
        });

        $snapshot->refresh();
        $this->assertSame('api', $snapshot->source);
        $this->assertSame(2, $snapshot->items()->count());
        $this->assertSame(420000.0, (float) $snapshot->total_fee);
        $this->assertNotNull($snapshot->last_synced_at);
    }

    public function test_support_type_filter_updates_displayed_summary(): void
    {
        $user = User::factory()->create();
        $snapshot = MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-03-01',
            'total_fee' => 420000,
            'total_gross' => 420000,
            'source' => 'mixed',
            'last_synced_at' => '2026-03-22 09:00:00',
        ]);

        $snapshot->items()->createMany([
            ['customer_name' => 'A社', 'maintenance_fee' => 300000, 'status' => 'active', 'support_type' => 'フルサポート', 'entry_source' => 'api'],
            ['customer_name' => 'B社', 'maintenance_fee' => 120000, 'status' => 'active', 'support_type' => '監視', 'entry_source' => 'manual'],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-fees.index', ['month' => '2026-03', 'support_type' => '監視']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('summary.displayed_total_fee', fn ($value) => (float) $value === 120000.0)
                ->where('summary.displayed_active_count', 1)
                ->where('summary.overall_total_fee', fn ($value) => (float) $value === 420000.0)
                ->where('summary.meta.source', 'mixed')
                ->where('summary.meta.manual_edit_count', 1)
                ->where('summary.meta.applied_filters.search', '')
                ->where('summary.meta.applied_filters.support_type', '監視')
                ->where('filters.support_type', '監視')
                ->where('filters.support_type_options.0', 'フルサポート')
                ->where('filters.support_type_options.1', '監視')
                ->where('items.0.customer_name', 'B社')
                ->missing('items.1');
        });
    }

    public function test_api_error_is_exposed_in_response_when_snapshot_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 09:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();

        Http::fake([
            '*' => Http::response(['message' => 'server error'], 500),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-fees.index', ['month' => '2026-03']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('api_status.kind', 'error')
                ->where('summary.snapshot_month', null)
                ->where('summary.displayed_total_fee', fn ($value) => (float) $value === 0.0)
                ->where('items', []);
        });
    }

    public function test_chart_uses_selected_month_as_reference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-22 09:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();

        foreach ([
            '2025-11-01' => 556689,
            '2025-12-01' => 580000,
            '2026-01-01' => 590000,
            '2026-02-01' => 610000,
            '2026-03-01' => 614523,
            '2026-08-01' => 745000,
            '2026-09-01' => 730000,
        ] as $month => $total) {
            MaintenanceFeeSnapshot::query()->create([
                'month' => $month,
                'total_fee' => $total,
                'total_gross' => $total,
                'source' => 'api',
            ]);
        }

        Http::fake(['*' => Http::response([], 200)]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-fees.index', ['month' => '2026-03']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('chart.0.label', '2025/11')
                ->where('chart.1.label', '2025/12')
                ->where('chart.2.label', '2026/01')
                ->where('chart.3.label', '2026/02')
                ->where('chart.4.label', '2026/03')
                ->missing('chart.5');
        });
    }
}
