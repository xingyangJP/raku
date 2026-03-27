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
                $sku = $item['sku'];
                $price = $item['price'];
                $cost = $item['cost'];
                $unit = $item['unit'];
                $description = $item['description'] ?? null;
                $businessDivision = match ($name) {
                    'Magic XPA', 'ハードウェア', 'サプライ' => 'first_business',
                    default => config('business_divisions.default', 'fifth_business'),
                };

                $this->upsertSeedProduct(
                    (int) $category->id,
                    (string) $category->code,
                    $sku,
                    $name,
                    $price,
                    $cost,
                    $unit,
                    $description,
                    $businessDivision
                );
            }
        }
    }

    private function upsertSeedProduct(
        int $categoryId,
        string $categoryCode,
        string $sku,
        string $name,
        int $price,
        int $cost,
        string $unit,
        ?string $description,
        ?string $businessDivision = null
    ): void
    {
        DB::transaction(function () use ($categoryId, $categoryCode, $sku, $name, $price, $cost, $unit, $description, $businessDivision) {
            $category = DB::table('categories')->where('id', $categoryId)->lockForUpdate()->first(['id', 'last_item_seq']);
            if (!$category) {
                return;
            }

            $seq = $this->extractSeqFromSku($sku, $categoryCode);
            $existing = DB::table('products')
                ->where('sku', $sku)
                ->orWhere('name', $name)
                ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [$sku])
                ->first(['id', 'sku', 'name', 'category_id', 'seq', 'price', 'cost', 'unit', 'description', 'business_division', 'tax_category']);

            $payload = [
                'name' => $name,
                'unit' => $existing && !empty($existing->unit) ? $existing->unit : $unit,
                'price' => $this->resolveSeedPrice($existing?->price ?? null, $existing?->cost ?? null, $price, $cost),
                'cost' => $this->resolveSeedCost($existing?->cost ?? null, $cost),
                'tax_category' => $existing && !empty($existing->tax_category) ? $existing->tax_category : 'ten_percent',
                'is_active' => true,
                'description' => $existing && !empty($existing->description) ? $existing->description : $description,
                'attributes' => null,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('products', 'business_division')) {
                $payload['business_division'] = !empty($existing?->business_division)
                    ? $existing->business_division
                    : ($businessDivision ?? config('business_divisions.default', 'fifth_business'));
            }
            if (Schema::hasColumn('products', 'category_id')) {
                $payload['category_id'] = $categoryId;
            }
            if (Schema::hasColumn('products', 'seq')) {
                $payload['seq'] = $seq;
            }

            if ($existing) {
                DB::table('products')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('products')->insert([
                    ...$payload,
                    'sku' => $sku,
                    'created_at' => now(),
                ]);
            }

            DB::table('categories')->where('id', $categoryId)->update([
                'last_item_seq' => max((int) $category->last_item_seq, $seq),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function extractSeqFromSku(string $sku, string $categoryCode): int
    {
        if (preg_match('/^' . preg_quote($categoryCode, '/') . '-(\d+)$/', $sku, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function resolveSeedCost(mixed $currentCost, int $seedCost): int
    {
        if (is_numeric($currentCost) && (float) $currentCost > 0) {
            return (int) $currentCost;
        }

        return $seedCost;
    }

    private function resolveSeedPrice(mixed $currentPrice, mixed $currentCost, int $seedPrice, int $seedCost): int
    {
        if (!is_numeric($currentPrice) || (float) $currentPrice <= 0) {
            return $seedPrice;
        }

        $normalizedPrice = (int) $currentPrice;
        $normalizedCost = $this->resolveSeedCost($currentCost, $seedCost);

        if ($normalizedPrice < $normalizedCost) {
            return max($seedPrice, $normalizedCost);
        }

        return $normalizedPrice;
    }

    // 価格推定関数は明示指定に置き換えたため不要
}
