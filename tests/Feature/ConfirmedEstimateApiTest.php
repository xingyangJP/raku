<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ConfirmedEstimateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_estimates_api_requires_bearer_token(): void
    {
        Config::set('services.external_integration.token', 'secret-token');

        $this->getJson('/api/v1/confirmed-estimates')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->getJson('/api/v1/confirmed-estimates')
            ->assertUnauthorized();
    }

    public function test_confirmed_estimates_api_returns_only_confirmed_estimates_with_metrics(): void
    {
        Config::set('services.external_integration.token', 'secret-token');

        Product::query()->create([
            'name' => '機器販売',
            'sku' => 'HW-001',
            'unit' => '台',
            'price' => 9000,
            'cost' => 4000,
            'business_division' => 'first_business',
            'is_active' => true,
        ]);

        $confirmed = Estimate::factory()->create([
            'estimate_number' => 'EST-API-001',
            'customer_name' => '外部連携テスト株式会社',
            'title' => '受注済みAPI案件',
            'status' => 'sent',
            'is_order_confirmed' => true,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-20',
            'delivery_date' => '2026-07-31',
            'total_amount' => 136600,
            'tax_amount' => 11600,
            'items' => [
                [
                    'name' => '開発',
                    'qty' => 2,
                    'unit' => '人月',
                    'price' => 50000,
                    'business_division' => 'fifth_business',
                ],
                [
                    'name' => '支援',
                    'qty' => 16,
                    'unit' => '時間',
                    'price' => 1000,
                    'business_division' => 'fifth_business',
                ],
                [
                    'code' => 'HW-001',
                    'name' => '機器販売',
                    'qty' => 1,
                    'unit' => '台',
                    'price' => 9000,
                ],
            ],
        ]);

        Estimate::factory()->create([
            'estimate_number' => 'EST-API-DRAFT',
            'status' => 'sent',
            'is_order_confirmed' => false,
        ]);
        Estimate::factory()->create([
            'estimate_number' => 'EST-API-DELETED',
            'status' => 'sent',
            'is_order_confirmed' => true,
            'mf_deleted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer secret-token')
            ->getJson('/api/v1/confirmed-estimates');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $confirmed->id)
            ->assertJsonPath('data.0.estimate_number', 'EST-API-001')
            ->assertJsonPath('data.0.subtotal_excluding_tax', 125000)
            ->assertJsonPath('data.0.sales_subtotal_excluding_tax', 125000)
            ->assertJsonPath('data.0.development_subtotal_excluding_tax', 116000)
            ->assertJsonPath('data.0.first_business_subtotal_excluding_tax', 9000)
            ->assertJsonPath('data.0.tax_amount', 11600)
            ->assertJsonPath('data.0.total_amount', 136600)
            ->assertJsonPath('data.0.effort_person_days', 42);
    }

    public function test_confirmed_estimate_detail_includes_safe_item_metrics(): void
    {
        Config::set('services.external_integration.token', 'secret-token');

        $confirmed = Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => true,
            'total_amount' => 33000,
            'tax_amount' => 3000,
            'items' => [
                [
                    'name' => '設計',
                    'qty' => 3,
                    'unit' => '人日',
                    'price' => 10000,
                    'cost' => 5000,
                    'business_division' => 'fifth_business',
                ],
            ],
            'internal_memo' => '外部には返さない',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer secret-token')
            ->getJson("/api/v1/confirmed-estimates/{$confirmed->id}");

        $response->assertOk()
            ->assertJsonPath('data.subtotal_excluding_tax', 30000)
            ->assertJsonPath('data.sales_subtotal_excluding_tax', 30000)
            ->assertJsonPath('data.development_subtotal_excluding_tax', 30000)
            ->assertJsonPath('data.first_business_subtotal_excluding_tax', 0)
            ->assertJsonPath('data.effort_person_days', 3)
            ->assertJsonPath('data.items.0.name', '設計')
            ->assertJsonPath('data.items.0.line_subtotal_excluding_tax', 30000)
            ->assertJsonPath('data.items.0.effort_person_days', 3)
            ->assertJsonMissingPath('data.internal_memo')
            ->assertJsonMissingPath('data.items.0.cost');
    }

    public function test_confirmed_estimate_detail_rejects_unconfirmed_estimate(): void
    {
        Config::set('services.external_integration.token', 'secret-token');

        $estimate = Estimate::factory()->create([
            'status' => 'sent',
            'is_order_confirmed' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->getJson("/api/v1/confirmed-estimates/{$estimate->id}")
            ->assertNotFound();
    }
}
