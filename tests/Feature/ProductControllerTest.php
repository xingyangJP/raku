<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_reassigns_to_next_available_sku_when_category_sequence_is_stale(): void
    {
        $user = User::factory()->create();

        DB::table('categories')->insert([
            ['id' => 2, 'name' => '開発', 'code' => 'B', 'last_item_seq' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => '設計', 'code' => 'C', 'last_item_seq' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('products')->insert([
            [
                'id' => 12,
                'sku' => 'B-002',
                'category_id' => null,
                'seq' => null,
                'name' => 'テスト',
                'unit' => '人日',
                'price' => 40000,
                'quantity' => 1,
                'cost' => 10000,
                'tax_category' => 'ten_percent',
                'business_division' => 'fifth_business',
                'is_active' => true,
                'description' => '既存品',
                'attributes' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $product = Product::create([
            'sku' => 'C-002',
            'category_id' => 3,
            'seq' => 2,
            'name' => 'テスト設計',
            'unit' => '人日',
            'price' => 40000,
            'quantity' => 1,
            'cost' => 20000,
            'tax_category' => 'ten_percent',
            'business_division' => 'fifth_business',
            'is_active' => true,
            'description' => '仕様設計',
            'attributes' => [],
        ]);

        $response = $this->actingAs($user)->put(route('products.update', $product), [
            'name' => 'テスト設計',
            'category_id' => 2,
            'unit' => '人日',
            'price' => 40000,
            'quantity' => 1,
            'cost' => 20000,
            'tax_category' => 'ten_percent',
            'business_division' => 'fifth_business',
            'is_deduct_withholding_tax' => false,
            'is_active' => true,
            'description' => '仕様設計',
            'attributes' => [],
        ]);

        $response->assertRedirect(route('products.index'));

        $fresh = $product->fresh();
        $this->assertSame('B-003', $fresh->sku);
        $this->assertSame(2, $fresh->category_id);
        $this->assertSame(3, $fresh->seq);
        $this->assertSame(3, (int) DB::table('categories')->where('id', 2)->value('last_item_seq'));
    }
}
