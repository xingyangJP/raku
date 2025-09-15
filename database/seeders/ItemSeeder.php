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

        $plan = [
            'コンサル'   => ['要件定義'],
            '開発'     => ['開発', 'テスト'],
            '設計'     => ['設計', 'テスト設計', 'UI設計'],
            '管理'     => ['プロジェクトマネジメント'],
            'ハードウェア' => ['ディスプレイ', 'パソコン本体', 'プリンタ'],
            'サプライ'   => ['伝票', 'コピー用紙'],
            'ライセンス' => ['Magic XPA'],
        ];

        foreach ($plan as $catName => $items) {
            $category = DB::table('categories')->where('name', $catName)->first(['id', 'code', 'last_item_seq']);
            if (!$category) {
                // If the category doesn’t exist (e.g. different seed ordering), skip gracefully
                $this->command?->warn("ItemSeeder: category '$catName' not found. Skipped.");
                continue;
            }

            foreach ($items as $name) {
                // Idempotent: skip if a product by this name already exists
                $existsByName = DB::table('products')->where('name', $name)->exists();
                if ($existsByName) continue;

                $this->createProductWithIncrementedSku($category->id, $category->code, $name);
            }
        }
    }

    private function createProductWithIncrementedSku(int $categoryId, string $categoryCode, string $name): void
    {
        $attempts = 0;
        $maxAttempts = 7;
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                DB::transaction(function () use ($categoryId, $categoryCode, $name) {
                    // lock this category row and fetch updated seq
                    $row = DB::table('categories')->where('id', $categoryId)->lockForUpdate()->first(['id', 'last_item_seq']);
                    $seq = (int) $row->last_item_seq + 1;
                    $sku = sprintf('%s-%03d', $categoryCode, $seq);

                    // create product (sku is unique)
                    DB::table('products')->insert([
                        'sku' => $sku,
                        'name' => $name,
                        'unit' => '式',
                        'price' => $this->suggestPrice($categoryCode, $name),
                        'cost' => $this->suggestCost($categoryCode, $name),
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

    private function suggestPrice(string $categoryCode, string $name): int
    {
        // rough heuristics by name; rounded to 100 yen
        $p = 10000; // default for services
        if (str_contains($name, '要件') || str_contains($name, '設計')) $p = 12000;
        if ($name === '開発') $p = 10000;
        if ($name === 'テスト' || str_contains($name, 'テスト')) $p = 8000;
        if ($name === 'プロジェクトマネジメント') $p = 13000;
        if ($name === 'ディスプレイ') $p = 50000;
        if ($name === 'パソコン本体') $p = 150000;
        if ($name === 'プリンタ') $p = 30000;
        if ($name === '伝票') $p = 1000;
        if ($name === 'コピー用紙') $p = 500;
        if ($name === 'Magic XPA') $p = 60000;
        return (int) (round($p / 100) * 100);
    }

    private function suggestCost(string $categoryCode, string $name): int
    {
        $price = $this->suggestPrice($categoryCode, $name);
        // 70% of price, rounded to 100
        $cost = (int) round($price * 0.7 / 100) * 100;
        return max(0, $cost);
    }
}

