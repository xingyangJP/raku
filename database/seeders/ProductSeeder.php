<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // MySQL: 親子関係のため、外部キー制約を一時無効化して順序制御
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } catch (\Throwable $e) {}

        // 先に子テーブル(products)を空にしてから親(product_categories)を空にする
        DB::table('products')->truncate();
        DB::table('product_categories')->truncate();
        $cats = [
            ['code' => 'APP', 'name' => 'アプリ開発'],
            ['code' => 'INF', 'name' => 'インフラ'],
            ['code' => 'CNST', 'name' => 'コンサル'],
            ['code' => 'TRN', 'name' => '教育/トレーニング'],
        ];
        foreach ($cats as $c) {
            DB::table('product_categories')->insert(array_merge($c, [
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $catIds = DB::table('product_categories')->pluck('id', 'code');

        // products を再生成
        $products = [
            ['sku' => 'APP-REQ', 'name' => '要件定義', 'category' => 'APP', 'unit' => '式', 'price' => 120000, 'cost' => 60000, 'tax_category' => 'standard'],
            ['sku' => 'APP-BASIC', 'name' => '基本設計', 'category' => 'APP', 'unit' => '式', 'price' => 150000, 'cost' => 80000, 'tax_category' => 'standard'],
            ['sku' => 'APP-DEV', 'name' => '詳細設計/開発', 'category' => 'APP', 'unit' => '人日', 'price' => 80000, 'cost' => 50000, 'tax_category' => 'standard'],
            ['sku' => 'APP-TEST', 'name' => '総合テスト', 'category' => 'APP', 'unit' => '人日', 'price' => 70000, 'cost' => 40000, 'tax_category' => 'standard'],
            ['sku' => 'TRN-ONB', 'name' => '導入支援/教育', 'category' => 'TRN', 'unit' => '時間', 'price' => 9000, 'cost' => 4500, 'tax_category' => 'standard'],
            ['sku' => 'INF-BLD', 'name' => 'インフラ構築', 'category' => 'INF', 'unit' => '式', 'price' => 200000, 'cost' => 100000, 'tax_category' => 'standard'],
            ['sku' => 'CNST-AD', 'name' => 'アドバイザリ', 'category' => 'CNST', 'unit' => '時間', 'price' => 12000, 'cost' => 6000, 'tax_category' => 'standard'],
        ];

        foreach ($products as $p) {
            DB::table('products')->insert([
                'sku' => $p['sku'],
                'name' => $p['name'],
                'category_id' => $catIds[$p['category']] ?? null,
                'unit' => $p['unit'],
                'price' => $p['price'],
                'cost' => $p['cost'],
                'tax_category' => $p['tax_category'],
                'is_active' => true,
                'description' => null,
                'attributes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $e) {}

        // Output Japanese summary
        $catCount = DB::table('product_categories')->count();
        $prodCount = DB::table('products')->count();
        $this->command?->info(sprintf('商品カテゴリ: %d件 / 商品: %d件', $catCount, $prodCount));
    }
}
