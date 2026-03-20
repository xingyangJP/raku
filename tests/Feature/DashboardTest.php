<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\LocalInvoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_is_displayed_when_partner_auto_sync_is_skipped(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->has('dashboardMetrics')
                ->has('partnerSyncMeta')
                ->has('salesRanking');
        });
    }

    public function test_dashboard_partner_sync_times_are_rendered_in_japan_time(): void
    {
        $user = User::factory()->create();

        Cache::forever('dashboard:partner-sync-meta', [
            'last_synced_at' => '2026-03-20T06:17:00+00:00',
            'cooldown_until' => '2026-03-20T09:17:00+00:00',
            'count' => 120,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('partnerSyncMeta.last_synced_at_label', '2026年3月20日 15:17')
                ->where('partnerSyncMeta.next_auto_sync_available_at_label', '18:17以降');
        });
    }

    public function test_dashboard_can_switch_target_year_and_month(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2025, 'month' => 3]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.filters.selected_year', 2025)
                ->where('dashboardMetrics.filters.selected_month', 3)
                ->where('dashboardMetrics.periods.current.label', '2025年3月');
        });
    }

    public function test_dashboard_includes_people_workload_summary_from_item_assignees(): void
    {
        $user = User::factory()->create();

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'delivery_date' => '2026-03-25',
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 8,
                    'unit' => '人日',
                    'price' => 80000,
                    'cost' => 30000,
                    'business_division' => 'fifth_business',
                    'assignees' => [
                        ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 75],
                        ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 25],
                    ],
                ],
                [
                    'name' => '実装',
                    'qty' => 4,
                    'unit' => '人日',
                    'price' => 90000,
                    'cost' => 35000,
                    'business_division' => 'fifth_business',
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 3]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.sections.overall.people.summary.tracked_people_count', 2)
                ->where('dashboardMetrics.sections.overall.people.summary.unassigned_person_days', 4)
                ->where('dashboardMetrics.sections.overall.people.rows.0.name', '担当者A')
                ->where('dashboardMetrics.sections.overall.people.rows.0.planned_person_days', fn ($value) => (float) $value === 6.0)
                ->where('dashboardMetrics.sections.overall.people.rows.0.utilization_rate', fn ($value) => (float) $value === 30.0)
                ->where('dashboardMetrics.sections.overall.people.rows.1.name', '担当者B')
                ->where('dashboardMetrics.sections.overall.people.rows.1.planned_person_days', fn ($value) => (float) $value === 2.0);
        });
    }

    public function test_dashboard_uses_confirmed_order_delivery_month_for_collection_forecast(): void
    {
        $user = User::factory()->create();

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'delivery_date' => '2026-04-20',
            'items' => [
                [
                    'name' => '受注案件',
                    'qty' => 1,
                    'unit' => '式',
                    'price' => 500000,
                    'cost' => 200000,
                    'business_division' => 'fifth_business',
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 5]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.periods.current.label', '2026年5月')
                ->where('dashboardMetrics.sections.overall.cash_flow.current.collection_inflow_actual', fn ($value) => (float) $value === 500000.0)
                ->where('dashboardMetrics.basis.cash_rule', '受注確定案件の回収予測は注文書納期の翌月入金、未受注案件は見積期限日を仮の回収予定として試算');
        });
    }

    public function test_dashboard_includes_business_division_report_from_invoice_data(): void
    {
        $user = User::factory()->create();

        Product::query()->create([
            'sku' => 'P-001',
            'name' => '事業区分テスト商品',
            'unit' => '式',
            'price' => 100000,
            'cost' => 40000,
            'tax_category' => 'standard',
            'business_division' => 'first_business',
            'is_active' => true,
        ]);

        LocalInvoice::query()->create([
            'customer_name' => 'テスト顧客',
            'title' => '請求テスト',
            'billing_number' => 'INV-TEST-001',
            'billing_date' => '2026-05-15',
            'due_date' => '2026-06-30',
            'items' => [
                [
                    'name' => '事業区分テスト商品',
                    'product_code' => 'P-001',
                    'qty' => 2,
                    'price' => 100000,
                    'cost' => 40000,
                    'description' => 'テスト明細',
                ],
            ],
            'total_amount' => 200000,
            'tax_amount' => 20000,
            'status' => 'sent',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 5]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('businessDivisionReport.period.year', 2026)
                ->where('businessDivisionReport.period.focus_month', '2026-05')
                ->where('businessDivisionReport.division_totals.first_business', fn ($value) => (float) $value === 200000.0)
                ->where('businessDivisionReport.grand_total', fn ($value) => (float) $value === 200000.0)
                ->where('businessDivisionReport.detail_rows.0.customer_name', 'テスト顧客')
                ->where('businessDivisionReport.detail_rows.0.division_key', 'first_business');
        });
    }

    public function test_business_divisions_page_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('businessDivisions.summary'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', '事業区分分析はダッシュボードへ統合しました。');
    }
}
