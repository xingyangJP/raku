<?php

namespace Tests\Feature;

use Database\Seeders\ItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_seeder_backfills_category_and_cost_for_existing_products(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'name' => 'コンサル', 'code' => 'A', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => '開発', 'code' => 'B', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => '設計', 'code' => 'C', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => '管理', 'code' => 'D', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'ハードウェア', 'code' => 'E', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'サプライ', 'code' => 'F', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'ライセンス', 'code' => 'G', 'last_item_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('products')->insert([
            'sku' => 'A-001',
            'category_id' => null,
            'seq' => null,
            'name' => '要件定義',
            'unit' => '人日',
            'price' => 40000,
            'quantity' => 1,
            'cost' => 0,
            'tax_category' => 'ten_percent',
            'business_division' => 'fifth_business',
            'is_active' => true,
            'description' => '業務案件の整理支援',
            'attributes' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(ItemSeeder::class);

        $product = DB::table('products')->where('sku', 'A-001')->first(['category_id', 'seq', 'price', 'cost']);
        $this->assertSame(1, $product->category_id);
        $this->assertSame(1, $product->seq);
        $this->assertSame(700000, (int) $product->price);
        $this->assertSame(500000, (int) $product->cost);
        $this->assertSame(1, (int) DB::table('categories')->where('id', 1)->value('last_item_seq'));
    }
}
