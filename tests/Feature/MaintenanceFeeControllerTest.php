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

    public function test_current_month_demo_snapshot_is_refreshed_from_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 09:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();
        $snapshot = MaintenanceFeeSnapshot::query()->create([
            'month' => '2026-03-01',
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
            ->get(route('maintenance-fees.index', ['month' => '2026-03']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('MaintenanceFees/Index')
                ->where('summary.total_fee', fn ($value) => (float) $value === 420000.0)
                ->where('summary.active_count', 2)
                ->where('summary.average_fee', fn ($value) => (float) $value === 210000.0)
                ->where('items.0.customer_name', '本番A')
                ->where('items.1.customer_name', '本番B');
        });

        $snapshot->refresh();
        $this->assertSame('api', $snapshot->source);
        $this->assertSame(2, $snapshot->items()->count());
        $this->assertSame(420000.0, (float) $snapshot->total_fee);
    }

    public function test_chart_uses_selected_month_as_reference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 09:00:00', 'Asia/Tokyo'));

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
