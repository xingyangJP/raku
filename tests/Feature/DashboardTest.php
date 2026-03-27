<?php

namespace Tests\Feature;

use App\Models\DashboardAiAnalysis;
use App\Models\Estimate;
use App\Models\LocalInvoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
        $user = User::factory()->create([
            'name' => 'Codex UI Check',
            'email' => 'codex-ui-check@example.com',
        ]);
        $assigneeA = User::factory()->create([
            'name' => '担当者A',
            'work_capacity_person_days' => 10,
        ]);
        $assigneeB = User::factory()->create([
            'name' => '担当者B',
            'work_capacity_person_days' => 5,
        ]);
        User::factory()->create([
            'name' => '担当者C',
            'work_capacity_person_days' => 8,
        ]);

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
                        ['user_id' => (string) $assigneeA->id, 'user_name' => '担当者A', 'share_percent' => 75],
                        ['user_id' => (string) $assigneeB->id, 'user_name' => '担当者B', 'share_percent' => 25],
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
                ->where('dashboardMetrics.sections.overall.people.summary.tracked_people_count', 3)
                ->where('dashboardMetrics.sections.overall.people.summary.capacity_person_days', fn ($value) => (float) $value === 23.0)
                ->where('dashboardMetrics.sections.overall.people.summary.available_people_count', 3)
                ->where('dashboardMetrics.sections.overall.people.summary.high_load_count', 0)
                ->where('dashboardMetrics.sections.overall.people.summary.unassigned_person_days', 4)
                ->where('dashboardMetrics.sections.overall.people.rows.0.name', '担当者A')
                ->where('dashboardMetrics.sections.overall.people.rows.0.planned_person_days', fn ($value) => (float) $value === 6.0)
                ->where('dashboardMetrics.sections.overall.people.rows.0.capacity_person_days', fn ($value) => (float) $value === 10.0)
                ->where('dashboardMetrics.sections.overall.people.rows.0.utilization_rate', fn ($value) => (float) $value === 60.0)
                ->where('dashboardMetrics.sections.overall.people.rows.1.name', '担当者B')
                ->where('dashboardMetrics.sections.overall.people.rows.1.planned_person_days', fn ($value) => (float) $value === 2.0)
                ->where('dashboardMetrics.sections.overall.people.rows.1.capacity_person_days', fn ($value) => (float) $value === 5.0)
                ->where('dashboardMetrics.sections.overall.people.rows.1.utilization_rate', fn ($value) => (float) $value === 40.0)
                ->where('dashboardMetrics.sections.overall.people.rows.2.name', '担当者C')
                ->where('dashboardMetrics.sections.overall.people.rows.2.planned_person_days', fn ($value) => (float) $value === 0.0)
                ->where('dashboardMetrics.sections.overall.people.rows.2.capacity_person_days', fn ($value) => (float) $value === 8.0)
                ->where('dashboardMetrics.sections.overall.people.rows.2.available_person_days', fn ($value) => (float) $value === 8.0);
        });
    }

    public function test_dashboard_builds_section_specific_analysis(): void
    {
        $user = User::factory()->create([
            'work_capacity_person_days' => 10,
        ]);

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'delivery_date' => '2026-03-25',
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 12,
                    'unit' => '人日',
                    'price' => 1200000,
                    'cost' => 500000,
                    'business_division' => 'fifth_business',
                ],
                [
                    'name' => 'ハード販売',
                    'qty' => 1,
                    'unit' => '式',
                    'price' => 300000,
                    'cost' => 210000,
                    'business_division' => 'first_business',
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
                ->where('dashboardMetrics.sections.overall.analysis.0.title', '売上差異')
                ->where('dashboardMetrics.sections.development.analysis.0.title', '売上差異')
                ->where('dashboardMetrics.sections.development.analysis.2.title', '開発稼働')
                ->where('dashboardMetrics.sections.sales.analysis.1.title', '販売粗利')
                ->where('dashboardMetrics.sections.sales.analysis.2.title', '販売回収')
                ->where('dashboardMetrics.sections.maintenance.analysis.0.title', '保守継続');
        });
    }

    public function test_dashboard_counts_unassigned_people_effort_when_delivery_date_is_missing(): void
    {
        $user = User::factory()->create([
            'name' => 'Codex UI Check',
            'email' => 'codex-ui-check@example.com',
        ]);

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => false,
            'issue_date' => '2026-02-20',
            'due_date' => '2026-03-12',
            'delivery_date' => null,
            'items' => [
                [
                    'name' => '開発',
                    'qty' => 2,
                    'unit' => '人日',
                    'price' => 80000,
                    'cost' => 30000,
                    'business_division' => 'fifth_business',
                ],
                [
                    'name' => 'テスト',
                    'qty' => 0.5,
                    'unit' => '人日',
                    'price' => 30000,
                    'cost' => 10000,
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
                ->where('dashboardMetrics.sections.overall.people.summary.unassigned_person_days', fn ($value) => (float) $value === 2.5);
        });
    }

    public function test_dashboard_evenly_allocates_effort_across_start_and_delivery_months(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create([
            'name' => '担当者A',
            'work_capacity_person_days' => 12,
        ]);

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'start_date' => '2026-04-01',
            'delivery_date' => '2026-06-25',
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 9,
                    'unit' => '人日',
                    'price' => 900000,
                    'cost' => 300000,
                    'business_division' => 'fifth_business',
                    'assignees' => [
                        ['user_id' => (string) $assignee->id, 'user_name' => '担当者A', 'share_percent' => 100],
                    ],
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
                ->where('dashboardMetrics.sections.overall.effort.current.planned', fn ($value) => (float) $value === 3.0)
                ->where('dashboardMetrics.sections.overall.people.summary.unassigned_person_days', fn ($value) => (float) $value === 0.0)
                ->where('dashboardMetrics.sections.overall.people.rows.0.name', '担当者A')
                ->where('dashboardMetrics.sections.overall.people.rows.0.planned_person_days', fn ($value) => (float) $value === 3.0)
                ->where('dashboardMetrics.basis.effort_rule', '着手日と納期がある案件は開始月から納品月まで均等配賦、着手日未設定は納期月へ一括配賦、納期未設定は未配賦として別管理');
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


    public function test_dashboard_generates_and_persists_overall_ai_analysis_once_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-03-21 09:00:00', 'Asia/Tokyo'));

        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.base_url', 'https://api.openai.com');
        Config::set('services.openai.model', 'gpt-4o-mini');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"overview":{"headline":"3月の経営総評","summary":"売上は未達だが保守収益が下支えしており、粗利率と回収時期の管理が優先です。","focus_points":["保守収益が売上を下支え","粗利率の低い案件が混在"],"actions":["低粗利案件の見直し","回収予定の前倒し確認"]},"items":[{"title":"AI要約","body":"全社売上は未達だが、保守収益が下支えしています。","tone":"neutral"}]}'
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $firstResponse = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 3]));

        $firstResponse->assertOk();
        $firstResponse->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.sections.overall.analysis.0.title', 'AI要約')
                ->where('dashboardMetrics.analysis_meta.source', 'ai')
                ->where('dashboardMetrics.sections.overall.analysis_meta.source', 'ai')
                ->where('dashboardMetrics.analysis_overview.headline', '3月の経営総評')
                ->where('dashboardMetrics.analysis_overview.actions.0', '低粗利案件の見直し');
        });

        $this->assertTrue(
            DashboardAiAnalysis::query()
                ->whereDate('analysis_date', '2026-03-21')
                ->where('target_year', 2026)
                ->where('target_month', 3)
                ->where('section_key', 'overall')
                ->where('status', 'completed')
                ->where('model', 'gpt-4o-mini')
                ->exists()
        );

        Http::fake([
            '*' => function () {
                throw new \RuntimeException('OpenAI should not be called on the second dashboard access in the same day.');
            },
        ]);

        $secondResponse = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 3]));

        $secondResponse->assertOk();
        $secondResponse->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.sections.overall.analysis.0.title', 'AI要約')
                ->where('dashboardMetrics.analysis_meta.source', 'ai');
        });

        $this->assertSame(1, DashboardAiAnalysis::query()->count());
    }

    public function test_dashboard_returns_saved_ai_overview_for_selected_period(): void
    {
        $this->travelTo(Carbon::parse('2026-03-21 10:00:00', 'Asia/Tokyo'));

        $user = User::factory()->create();

        DashboardAiAnalysis::query()->create([
            'analysis_date' => '2026-03-21',
            'target_year' => 2026,
            'target_month' => 3,
            'section_key' => 'overall',
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'analysis_items' => [
                ['title' => '3月要約', 'body' => '3月の分析です。', 'tone' => 'neutral'],
            ],
            'analysis_overview' => [
                'headline' => '3月の経営総評',
                'summary' => '3月は保守が下支えしつつ、案件粗利のばらつきが大きい状態です。',
                'focus_points' => ['保守売上が安定', '開発案件の粗利差が大きい'],
                'actions' => ['粗利の低い案件を洗い出す'],
            ],
            'generated_at' => '2026-03-21 10:00:00',
        ]);

        DashboardAiAnalysis::query()->create([
            'analysis_date' => '2026-03-21',
            'target_year' => 2026,
            'target_month' => 4,
            'section_key' => 'overall',
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'analysis_items' => [
                ['title' => '4月要約', 'body' => '4月の分析です。', 'tone' => 'positive'],
            ],
            'analysis_overview' => [
                'headline' => '4月の経営総評',
                'summary' => '4月は売上回復が見える一方で、回収予定の遅れが資金繰りの焦点です。',
                'focus_points' => ['売上は回復基調', '回収予定に遅れ'],
                'actions' => ['未回収案件の督促を前倒しする'],
            ],
            'generated_at' => '2026-03-21 10:05:00',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 4]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.analysis_meta.source', 'ai')
                ->where('dashboardMetrics.analysis_overview.headline', '4月の経営総評')
                ->where('dashboardMetrics.analysis_overview.summary', '4月は売上回復が見える一方で、回収予定の遅れが資金繰りの焦点です。')
                ->where('dashboardMetrics.analysis_overview.focus_points.0', '売上は回復基調')
                ->where('dashboardMetrics.analysis_overview.actions.0', '未回収案件の督促を前倒しする')
                ->where('dashboardMetrics.sections.overall.analysis.0.title', '4月要約');
        });
    }

    public function test_dashboard_falls_back_to_rule_analysis_when_openai_is_not_configured(): void
    {
        $this->travelTo(Carbon::parse('2026-03-21 09:00:00', 'Asia/Tokyo'));

        Config::set('services.openai.key', '');

        $user = User::factory()->create([
            'work_capacity_person_days' => 10,
        ]);

        Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'delivery_date' => '2026-03-25',
            'items' => [[
                'name' => '設計',
                'qty' => 12,
                'unit' => '人日',
                'price' => 1200000,
                'cost' => 500000,
                'business_division' => 'fifth_business',
            ]],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2026, 'month' => 3]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.analysis_meta.source', 'rule')
                ->where('dashboardMetrics.analysis_meta.status', 'skipped')
                ->where('dashboardMetrics.sections.overall.analysis.0.title', '売上差異');
        });

        $this->assertTrue(
            DashboardAiAnalysis::query()
                ->whereDate('analysis_date', '2026-03-21')
                ->where('target_year', 2026)
                ->where('target_month', 3)
                ->where('section_key', 'overall')
                ->where('status', 'skipped')
                ->exists()
        );
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
