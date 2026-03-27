<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EstimateAiDocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_document_can_generate_ai_draft_and_requirement_summary(): void
    {
        config([
            'services.openai.key' => 'test-openai-key',
            'services.openai.base_url' => 'https://api.openai.com',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();
        $design = Product::create([
            'sku' => 'A-001',
            'name' => '要件定義',
            'unit' => '人日',
            'price' => 80000,
            'cost' => 30000,
            'tax_category' => 'standard',
            'business_division' => 'fifth_business',
            'is_active' => true,
            'description' => '要件整理',
        ]);
        $development = Product::create([
            'sku' => 'B-001',
            'name' => '開発',
            'unit' => '人日',
            'price' => 100000,
            'cost' => 45000,
            'tax_category' => 'standard',
            'business_division' => 'fifth_business',
            'is_active' => true,
            'description' => 'アプリ開発',
        ]);
        $test = Product::create([
            'sku' => 'T-001',
            'name' => '総合テスト',
            'unit' => '人日',
            'price' => 70000,
            'cost' => 25000,
            'tax_category' => 'standard',
            'business_division' => 'fifth_business',
            'is_active' => true,
            'description' => '総合テスト',
        ]);

        Http::fake([
            'https://www.googleapis.com/drive/v3/files/doc-123*' => Http::sequence()
                ->push([
                    'id' => 'doc-123',
                    'name' => '顧客ポータル要件定義',
                    'mimeType' => 'application/vnd.google-apps.document',
                    'webViewLink' => 'https://docs.google.com/document/d/doc-123/edit',
                ], 200)
                ->push("顧客ポータルを刷新する。管理画面と会員画面がある。API連携と監査ログが必要。", 200),
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'requirement_summary' => '顧客ポータル刷新案件。管理画面と会員画面を含み、既存API連携と監査ログ対応が必要です。',
                                'functional_requirements' => ['管理画面の会員管理', '会員向けポータル画面', '既存API連携'],
                                'non_functional_requirements' => ['監査ログの保持', 'レスポンス性能要件'],
                                'unresolved_requirements' => ['既存APIの認証方式確認'],
                                'notes_prompt' => '検収基準、納期前提、API連携条件、変更管理、保守保証の条件を備考へ反映したい。',
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'items' => [
                                    ['product_id' => $design->id, 'summary' => '要件定義｜顧客ポータル刷新', 'person_days' => 5],
                                    ['product_id' => $development->id, 'summary' => '開発｜管理画面/会員画面', 'person_days' => 18],
                                    ['product_id' => $test->id, 'summary' => '総合テスト', 'person_days' => 4],
                                ],
                                'notes' => "【検収基準】主要画面とAPI連携の受入確認をもって検収とします。\n\n【変更管理】追加要件は別途協議のうえ見積変更します。",
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('estimates.ai.generateDraftFromDoc'), [
            'google_docs_url' => 'https://docs.google.com/document/d/doc-123/edit',
            'google_file_id' => 'doc-123',
            'google_access_token' => 'google-token',
            'pm_required' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('document.file_id', 'doc-123')
            ->assertJsonPath('document.name', '顧客ポータル要件定義')
            ->assertJsonPath('requirement_summary', '顧客ポータル刷新案件。管理画面と会員画面を含み、既存API連携と監査ログ対応が必要です。')
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.1.product_id', $development->id)
            ->assertJsonPath('notes_prompt', '検収基準、納期前提、API連携条件、変更管理、保守保証の条件を備考へ反映したい。');
    }

    public function test_generate_notes_uses_unsaved_form_context(): void
    {
        config([
            'services.openai.key' => 'test-openai-key',
            'services.openai.base_url' => 'https://api.openai.com',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();
        $capturedContent = null;

        Http::fake(function (HttpRequest $request) use (&$capturedContent) {
            if ($request->url() === 'https://api.openai.com/v1/chat/completions') {
                $capturedContent = data_get($request->data(), 'messages.1.content');

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => "【検収基準】画面確認とAPI連携確認をもって検収とします。\n\n【前提条件】要件定義書と提供素材の確定後に着手します。",
                        ],
                    ]],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->postJson(route('estimates.generateNotes'), [
            'prompt' => '検収基準と前提条件を明確にしたい',
            'customer_name' => '株式会社テスト',
            'title' => '顧客ポータル刷新',
            'issue_date' => '2026-03-26',
            'google_docs_url' => 'https://docs.google.com/document/d/doc-123/edit',
            'requirement_summary' => '顧客ポータル刷新案件。管理画面と会員画面を開発する。',
            'structured_requirements' => [
                'functional' => ['管理画面の会員管理', '会員向けポータル画面'],
                'non_functional' => ['監査ログの保持'],
                'unresolved' => ['SSO方式の最終決定待ち'],
            ],
            'items' => [
                [
                    'name' => '開発',
                    'description' => '管理画面/会員画面',
                    'qty' => 18,
                    'unit' => '人日',
                    'price' => 100000,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('notes', "【検収基準】画面確認とAPI連携確認をもって検収とします。\n\n【前提条件】要件定義書と提供素材の確定後に着手します。");

        $this->assertIsString($capturedContent);
        $this->assertStringContainsString('顧客: 株式会社テスト', $capturedContent);
        $this->assertStringContainsString('要件概要: 顧客ポータル刷新案件。管理画面と会員画面を開発する。', $capturedContent);
        $this->assertStringContainsString('要件定義書: https://docs.google.com/document/d/doc-123/edit', $capturedContent);
        $this->assertStringContainsString('主要項目: 開発 / 18人日 / 単価100,000円', $capturedContent);
    }

    public function test_google_document_returns_quota_error_message_when_openai_is_rate_limited(): void
    {
        config([
            'services.openai.key' => 'test-openai-key',
            'services.openai.base_url' => 'https://api.openai.com',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();

        Http::fake([
            'https://www.googleapis.com/drive/v3/files/doc-123*' => Http::sequence()
                ->push([
                    'id' => 'doc-123',
                    'name' => '顧客ポータル要件定義',
                    'mimeType' => 'application/vnd.google-apps.document',
                    'webViewLink' => 'https://docs.google.com/document/d/doc-123/edit',
                ], 200)
                ->push('要件本文', 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'quota exceeded',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        $response = $this->actingAs($user)->postJson(route('estimates.ai.generateDraftFromDoc'), [
            'google_docs_url' => 'https://docs.google.com/document/d/doc-123/edit',
            'google_file_id' => 'doc-123',
            'google_access_token' => 'google-token',
            'pm_required' => false,
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'OpenAI API の利用上限に達しているため、AI解析を実行できません。時間を置くか、API利用枠をご確認ください。');
    }
}
