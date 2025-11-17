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
        if (\DB::table('products')->count() > 0) {
            $this->command?->info('ItemSeeder: 既存の商品データがあるため投入をスキップしました。');
            return;
        }

        // 明示仕様に基づく投入データ
    $plan = [
    'コンサル' => [
        [
            'sku' => 'A-001',
            'name' => '要件定義',
            'price' => 700000,
            'cost' => 500000,
            'unit' => '人月',
            'description' => '業務要件の整理支援',
        ],
    ],
    '開発' => [
        [
            'sku' => 'B-001',
            'name' => '開発',
            'price' => 600000,
            'cost' => 400000,
            'unit' => '人月',
            'description' => '実装と動作確認作業',
        ],
        [
            'sku' => 'B-002',
            'name' => 'テスト',
            'price' => 600000,
            'cost' => 400000,
            'unit' => '人月',
            'description' => '実装と動作確認作業',
        ],
    ],
    '設計' => [
        [
            'sku' => 'C-001',
            'name' => '設計',
            'price' => 700000,
            'cost' => 500000,
            'unit' => '人月',
            'description' => '仕様設計と画面設計',
        ],
        [
            'sku' => 'C-002',
            'name' => 'テスト設計',
            'price' => 700000,
            'cost' => 500000,
            'unit' => '人月',
            'description' => '仕様設計と画面設計',
        ],
        [
            'sku' => 'C-003',
            'name' => 'UI設計',
            'price' => 700000,
            'cost' => 500000,
            'unit' => '人月',
            'description' => '仕様設計と画面設計',
        ],
    ],
    '管理' => [
        [
            'sku' => 'D-001',
            'name' => 'プロジェクトマネジメント',
            'price' => 700000,
            'cost' => 500000,
            'unit' => '人月',
            'description' => '進行管理と品質保証',
        ],
    ],
    'ハードウェア' => [
        [
            'sku' => 'E-001',
            'name' => 'ハードウェア',
            'price' => 200000,
            'cost' => 100000,
            'unit' => '台',
            'description' => '機器提供と設置支援',
        ],
    ],
    'サプライ' => [
        [
            'sku' => 'F-001',
            'name' => 'サプライ',
            'price' => 200000,
            'cost' => 100000,
            'unit' => '式',
            'description' => '事務消耗品の供給',
        ],
    ],
    'ライセンス' => [
        [
            'sku' => 'G-001',
            'name' => 'Magic XPA',
            'price' => 60000,
            'cost' => 50000,
            'unit' => '個',
            'description' => '開発環境用ライセンス',
        ],
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
                $description = $item['description'] ?? null;
                $businessDivision = match ($name) {
                    'Magic XPA', 'ハードウェア', 'サプライ' => 'first_business',
                    default => config('business_divisions.default', 'fifth_business'),
                };

                // Idempotent: skip if a product by this name already exists
                $existsByName = DB::table('products')->where('name', $name)->exists();
                if ($existsByName) continue;

                $this->createProductWithIncrementedSku($category->id, $category->code, $name, $price, $cost, $unit, $description, $businessDivision);
            }
        }
    }

    private function createProductWithIncrementedSku(int $categoryId, string $categoryCode, string $name, int $price, int $cost, string $unit, ?string $description, ?string $businessDivision = null): void
    {
        $attempts = 0;
        $maxAttempts = 7;
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                DB::transaction(function () use ($categoryId, $categoryCode, $name, $price, $cost, $unit, $description, $businessDivision) {
                    // lock this category row and fetch updated seq
                    $row = DB::table('categories')->where('id', $categoryId)->lockForUpdate()->first(['id', 'last_item_seq']);
                    $seq = (int) $row->last_item_seq + 1;
                    $sku = sprintf('%s-%03d', $categoryCode, $seq);

                    // create product (sku is unique)
                    $payload = [
                        'sku' => $sku,
                        'name' => $name,
                        'unit' => $unit,
                        'price' => $price,
                        'cost' => $cost,
                        'tax_category' => 'ten_percent',
                        'is_active' => true,
                        'description' => $description,
                        'attributes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'business_division')) {
                        $payload['business_division'] = $businessDivision ?? config('business_divisions.default', 'fifth_business');
                    }
                    // Optional columns when schema supports
                    if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'category_id')) {
                        $payload['category_id'] = $categoryId;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'seq')) {
                        $payload['seq'] = $seq;
                    }
                    DB::table('products')->insert($payload);

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
