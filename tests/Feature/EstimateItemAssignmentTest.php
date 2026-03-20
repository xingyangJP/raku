<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
