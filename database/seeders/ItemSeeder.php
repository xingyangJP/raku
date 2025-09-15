<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('categories')) {
            $this->command?->warn('Skipping ItemSeeder: categories table not found.');
            return;
        }
        if (!Schema::hasTable('products')) {
            $this->command?->warn('Skipping ItemSeeder: products table not found.');
            return;
        }

        // 明示仕様に基づく投入データ
        $plan = [
            'コンサル' => [
                ['name' => '要件定義', 'price' => 700000, 'cost' => 500000, 'unit' => '人月'],
            ],
            '開発' => [
                ['name' => '開発', 'price' => 600000, 'cost' => 400000, 'unit' => '人月'],
                ['name' => 'テスト', 'price' => 600000, 'cost' => 400000, 'unit' => '人月'],
            ],
            '設計' => [
                ['name' => '設計', 'price' => 700000, 'cost' => 500000, 'unit' => '人月'],
                ['name' => 'テスト設計', 'price' => 700000, 'cost' => 500000, 'unit' => '人月'],
                ['name' => 'UI設計', 'price' => 700000, 'cost' => 500000, 'unit' => '人月'],
            ],
            '管理' => [
                ['name' => 'プロジェクトマネジメント', 'price' => 700000, 'cost' => 500000, 'unit' => '人月'],
            ],
            'ハードウェア' => [
                ['name' => 'ハードウェア', 'price' => 200000, 'cost' => 100000, 'unit' => '台'],
            ],
            'サプライ' => [
                ['name' => '伝票', 'price' => 200000, 'cost' => 100000, 'unit' => '式'],
                ['name' => 'コピー用紙', 'price' => 200000, 'cost' => 100000, 'unit' => '式'],
            ],
            'ライセンス' => [
                ['name' => 'Magic XPA', 'price' => 200000, 'cost' => 100000, 'unit' => '個'],
            ],
        ];

        foreach ($plan as $catName => $items) {
            $category = DB::table('categories')->where('name', $catName)->first(['id', 'code', 'last_item_seq']);
            if (!$category) {
                // If the category doesn’t exist (e.g. different seed ordering), skip gracefully
                $this->command?->warn("ItemSeeder: category '$catName' not found. Skipped.");
                continue;
            }

            foreach ($items as $item) {
                $name = $item['name'];
                $price = $item['price'];
                $cost = $item['cost'];
                $unit = $item['unit'];

                // Idempotent: skip if a product by this name already exists
                $existsByName = DB::table('products')->where('name', $name)->exists();
                if ($existsByName) continue;

                $this->createProductWithIncrementedSku($category->id, $category->code, $name, $price, $cost, $unit);
            }
        }
    }

    private function createProductWithIncrementedSku(int $categoryId, string $categoryCode, string $name, int $price, int $cost, string $unit): void
    {
        $attempts = 0;
        $maxAttempts = 7;
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                DB::transaction(function () use ($categoryId, $categoryCode, $name, $price, $cost, $unit) {
                    // lock this category row and fetch updated seq
                    $row = DB::table('categories')->where('id', $categoryId)->lockForUpdate()->first(['id', 'last_item_seq']);
                    $seq = (int) $row->last_item_seq + 1;
                    $sku = sprintf('%s-%03d', $categoryCode, $seq);

                    // create product (sku is unique)
                    DB::table('products')->insert([
                        'category_id' => $categoryId,
                        'seq' => $seq,
                        'sku' => $sku,
                        'name' => $name,
                        'unit' => $unit,
                        'price' => $price,
                        'cost' => $cost,
                        'tax_category' => 'ten_percent',
                        'is_active' => true,
                        'description' => null,
                        'attributes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // bump seq only after successful insert
                    DB::table('categories')->where('id', $categoryId)->update([
                        'last_item_seq' => $seq,
                        'updated_at' => now(),
                    ]);
                }, 3);
                return; // success
            } catch (\Throwable $e) {
                // likely unique sku conflict; backoff and retry
                usleep(100000 * $attempts);
            }
        }
        $this->command?->warn("ItemSeeder: failed to create product '$name' after retries.");
    }

    // 価格推定関数は明示指定に置き換えたため不要
}
