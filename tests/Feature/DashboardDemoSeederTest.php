<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_demo_seeder_rebuilds_non_maintenance_estimates_for_2025_and_2026_may_only(): void
    {
        $this->seedReferenceData();

        $this->seed(DashboardDemoSeeder::class);

        $demoEstimates = Estimate::query()
            ->where('estimate_number', 'like', 'DEMO-DASH-%')
            ->orderBy('estimate_number')
            ->get();

        $this->assertCount(119, $demoEstimates);
        $this->assertDatabaseHas('estimates', ['estimate_number' => 'DEMO-DASH-202501-DEV-C']);
        $this->assertDatabaseHas('estimates', ['estimate_number' => 'DEMO-DASH-202605-DRF']);
        $this->assertDatabaseMissing('estimates', ['estimate_number' => 'DEMO-DASH-202606-DEV-C']);

        $this->assertSame(['draft', 'lost', 'pending', 'sent'], $demoEstimates->pluck('status')->unique()->sort()->values()->all());
        $this->assertSame(34, $demoEstimates->where('is_order_confirmed', true)->count());
        $this->assertSame(17, $demoEstimates->where('status', 'draft')->count());
        $this->assertSame(17, $demoEstimates->where('status', 'lost')->count());
        $this->assertSame(17, $demoEstimates->filter(fn (Estimate $estimate) => $estimate->follow_up_due_date !== null)->count());
        $this->assertSame(17, $demoEstimates->filter(fn (Estimate $estimate) => $estimate->lost_at !== null)->count());
        $this->assertTrue($demoEstimates->every(fn (Estimate $estimate) => str_contains((string) $estimate->internal_memo, 'dashboard_demo_estimate_seed_v2')));
        $this->assertSame(0, MaintenanceFeeSnapshot::query()->count());
    }

    private function seedReferenceData(): void
    {
        User::factory()->count(8)->sequence(
            ['name' => '営業 太郎', 'email' => 'sales-taro@example.com'],
            ['name' => '営業 花子', 'email' => 'sales-hanako@example.com'],
            ['name' => '開発 一郎', 'email' => 'dev-ichiro@example.com'],
            ['name' => '開発 次郎', 'email' => 'dev-jiro@example.com'],
            ['name' => '開発 三郎', 'email' => 'dev-saburo@example.com'],
            ['name' => '営業 次郎', 'email' => 'sales-jiro@example.com'],
            ['name' => 'PM 四郎', 'email' => 'pm-shiro@example.com'],
            ['name' => 'サポート 五郎', 'email' => 'support-goro@example.com'],
        )->create();

        foreach (range(1, 48) as $index) {
            Partner::query()->create([
                'mf_partner_id' => sprintf('PARTNER-%03d', $index),
                'code' => sprintf('P%03d', $index),
                'name' => sprintf('デモ顧客%02d株式会社', $index),
            ]);
        }

        $products = [
            ['sku' => 'A-001', 'name' => '要件定義', 'unit' => '人日', 'price' => 80000, 'cost' => 32000, 'business_division' => 'fifth_business'],
            ['sku' => 'B-001', 'name' => '開発', 'unit' => '人日', 'price' => 85000, 'cost' => 34000, 'business_division' => 'fifth_business'],
            ['sku' => 'E-001', 'name' => 'ハードウェア', 'unit' => '式', 'price' => 320000, 'cost' => 210000, 'business_division' => 'first_business'],
            ['sku' => 'F-001', 'name' => 'サプライ', 'unit' => '式', 'price' => 120000, 'cost' => 70000, 'business_division' => 'first_business'],
        ];

        foreach ($products as $product) {
            Product::query()->create([
                'sku' => $product['sku'],
                'name' => $product['name'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'cost' => $product['cost'],
                'tax_category' => 'taxable',
                'business_division' => $product['business_division'],
                'is_active' => true,
            ]);
        }
    }
}
