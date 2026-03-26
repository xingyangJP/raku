<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EstimateItemAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_normalizes_item_assignees_with_string_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('estimates.store'), $this->payload([
            'items' => [
                [
                    'name' => 'ダッシュボード開発',
                    'description' => '明細単位の按分',
                    'qty' => 20,
                    'unit' => '人日',
                    'price' => 100000,
                    'cost' => 40000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => 'u1', 'user_name' => '担当者A'],
                        ['user_id' => 'u2', 'user_name' => '担当者B'],
                    ],
                ],
            ],
        ]));

        $response->assertRedirect();

        $estimate = Estimate::latest('id')->firstOrFail();
        $this->assertEquals([
            ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 50.0],
            ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 50.0],
        ], $estimate->items[0]['assignees']);
    }

    public function test_update_normalizes_manual_shares_and_edit_returns_them(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create([
            'items' => [
                [
                    'name' => '旧明細',
                    'description' => '担当者なしの旧データ',
                    'qty' => 5,
                    'unit' => '人日',
                    'price' => 50000,
                    'cost' => 20000,
                    'tax_category' => 'standard',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->post(route('estimates.update', $estimate), $this->payload([
            'estimate_number' => $estimate->estimate_number,
            'items' => [
                [
                    'name' => 'API改修',
                    'description' => '按分率の正規化',
                    'qty' => 12,
                    'unit' => '人日',
                    'price' => 120000,
                    'cost' => 50000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 70],
                        ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 20],
                    ],
                ],
            ],
        ]));

        $response->assertRedirect();

        $estimate->refresh();
        $this->assertEquals([
            ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 77.8],
            ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 22.2],
        ], $estimate->items[0]['assignees']);
    }

    public function test_save_draft_normalizes_item_assignees(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('estimates.saveDraft'), $this->payload([
            'items' => [
                [
                    'name' => 'ドラフト案件',
                    'description' => '下書き保存',
                    'qty' => 10,
                    'unit' => '人日',
                    'price' => 100000,
                    'cost' => 40000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 10],
                        ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 10],
                        ['user_id' => 'u3', 'user_name' => '担当者C', 'share_percent' => 30],
                    ],
                ],
            ],
        ]));

        $response->assertRedirect();

        $estimate = Estimate::latest('id')->firstOrFail();
        $this->assertEquals([
            ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 20.0],
            ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 20.0],
            ['user_id' => 'u3', 'user_name' => '担当者C', 'share_percent' => 60.0],
        ], $estimate->items[0]['assignees']);
    }

    public function test_save_draft_updates_existing_draft_items_without_clearing_lines(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create([
            'status' => 'draft',
            'staff_name' => '営業担当',
            'items' => [
                [
                    'product_id' => 1,
                    'code' => 'OLD-001',
                    'name' => '旧明細',
                    'description' => '旧説明',
                    'qty' => 2,
                    'unit' => '人日',
                    'price' => 50000,
                    'cost' => 20000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => 'old', 'user_name' => '旧担当', 'share_percent' => 100.0],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->post(route('estimates.saveDraft'), $this->payload([
            'id' => $estimate->id,
            'estimate_number' => $estimate->estimate_number,
            'staff_name' => '営業担当',
            'items' => [
                [
                    'product_id' => 12,
                    'code' => 'B-001',
                    'name' => '開発',
                    'description' => '会員ポータル開発',
                    'qty' => 18,
                    'unit' => '人日',
                    'price' => 100000,
                    'cost' => 45000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 70],
                        ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 30],
                    ],
                ],
            ],
        ]));

        $response->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertCount(1, $estimate->items);
        $this->assertSame('開発', $estimate->items[0]['name']);
        $this->assertSame('会員ポータル開発', $estimate->items[0]['description']);
        $this->assertSame(18, $estimate->items[0]['qty']);
        $this->assertEquals([
            ['user_id' => 'u1', 'user_name' => '担当者A', 'share_percent' => 70.0],
            ['user_id' => 'u2', 'user_name' => '担当者B', 'share_percent' => 30.0],
        ], $estimate->items[0]['assignees']);
    }

    public function test_create_page_includes_workload_simulation_context(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create([
            'name' => '担当者A',
            'work_capacity_person_days' => 12,
        ]);

        Estimate::factory()->create([
            'status' => 'pending',
            'delivery_date' => '2026-04-25',
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 5,
                    'unit' => '人日',
                    'price' => 50000,
                    'cost' => 20000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => (string) $assignee->id, 'user_name' => '担当者A', 'share_percent' => 100],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('estimates.create'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($assignee): void {
            $page->component('Estimates/Create')
                ->where('workloadSimulation.capacity_per_person_days', fn ($value) => (float) $value === 20.0)
                ->where('workloadSimulation.months', function ($months) use ($assignee) {
                    return collect($months)->contains(function ($month) use ($assignee) {
                        return ($month['month_key'] ?? null) === '2026-04'
                            && collect($month['rows'] ?? [])->contains(function ($row) use ($assignee) {
                                return (string) ($row['user_id'] ?? '') === (string) $assignee->id
                                    && (float) ($row['capacity_person_days'] ?? 0) === 12.0
                                    && (float) ($row['planned_person_days'] ?? 0) === 5.0;
                            });
                    });
                });
        });
    }

    public function test_edit_page_workload_simulation_excludes_current_estimate(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create([
            'name' => '担当者A',
            'work_capacity_person_days' => 12,
        ]);

        Estimate::factory()->create([
            'status' => 'pending',
            'delivery_date' => '2026-04-25',
            'items' => [
                [
                    'name' => '既存案件',
                    'qty' => 5,
                    'unit' => '人日',
                    'price' => 50000,
                    'cost' => 20000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => (string) $assignee->id, 'user_name' => '担当者A', 'share_percent' => 100],
                    ],
                ],
            ],
        ]);

        $currentEstimate = Estimate::factory()->create([
            'status' => 'draft',
            'delivery_date' => '2026-04-28',
            'items' => [
                [
                    'name' => '編集中案件',
                    'qty' => 3,
                    'unit' => '人日',
                    'price' => 30000,
                    'cost' => 10000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => (string) $assignee->id, 'user_name' => '担当者A', 'share_percent' => 100],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('estimates.edit', $currentEstimate));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($assignee): void {
            $page->component('Estimates/Create')
                ->where('workloadSimulation.months', function ($months) use ($assignee) {
                    return collect($months)->contains(function ($month) use ($assignee) {
                        return ($month['month_key'] ?? null) === '2026-04'
                            && collect($month['rows'] ?? [])->contains(function ($row) use ($assignee) {
                                return (string) ($row['user_id'] ?? '') === (string) $assignee->id
                                    && (float) ($row['capacity_person_days'] ?? 0) === 12.0
                                    && (float) ($row['planned_person_days'] ?? 0) === 5.0;
                            });
                    });
                });
        });
    }

    public function test_create_page_workload_simulation_evenly_allocates_cross_month_effort(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create([
            'name' => '担当者A',
            'work_capacity_person_days' => 12,
        ]);

        Estimate::factory()->create([
            'status' => 'sent',
            'start_date' => '2026-04-01',
            'delivery_date' => '2026-06-30',
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 9,
                    'unit' => '人日',
                    'price' => 50000,
                    'cost' => 20000,
                    'tax_category' => 'standard',
                    'assignees' => [
                        ['user_id' => (string) $assignee->id, 'user_name' => '担当者A', 'share_percent' => 100],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('estimates.create'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($assignee): void {
            $page->component('Estimates/Create')
                ->where('workloadSimulation.basis.label', '着手日〜納期の均等配賦ベース')
                ->where('workloadSimulation.months', function ($months) use ($assignee) {
                    $targetMonths = collect($months)->filter(fn ($month) => in_array($month['month_key'] ?? null, ['2026-04', '2026-05', '2026-06'], true));

                    return $targetMonths->count() === 3
                        && $targetMonths->every(function ($month) use ($assignee) {
                            return collect($month['rows'] ?? [])->contains(function ($row) use ($assignee) {
                                return (string) ($row['user_id'] ?? '') === (string) $assignee->id
                                    && (float) ($row['planned_person_days'] ?? 0) === 3.0;
                            });
                        });
                });
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => '株式会社テスト',
            'client_contact_name' => '山田太郎',
            'client_contact_title' => '部長',
            'client_id' => 'CLIENT-ASSIGN',
            'mf_department_id' => 'DEPT-001',
            'title' => '担当者按分テスト見積',
            'issue_date' => '2026-03-20',
            'due_date' => '2026-04-20',
            'delivery_date' => '2026-04-30',
            'total_amount' => 110000,
            'tax_amount' => 10000,
            'notes' => 'テスト備考',
            'internal_memo' => 'テストメモ',
            'google_docs_url' => 'https://docs.google.com/document/d/example',
            'delivery_location' => '熊本',
            'items' => [
                [
                    'name' => '初期構築',
                    'description' => 'テスト',
                    'qty' => 1,
                    'unit' => '式',
                    'price' => 100000,
                    'cost' => 50000,
                    'tax_category' => 'standard',
                ],
            ],
            'estimate_number' => null,
            'staff_id' => 1001,
            'staff_name' => '営業担当',
            'approval_flow' => [],
            'status' => 'draft',
            'is_order_confirmed' => false,
            'requirement_summary' => '要件概要',
            'structured_requirements' => null,
        ], $overrides);
    }
}
