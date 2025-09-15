<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 外部APIからのユーザー同期（メールで突合、なければ作成）
        $this->call(UserSeeder::class);
        // 既存のUsersTableSeederとCustomersTableSeederがあれば先に実行
        if (class_exists('Database\Seeders\UsersTableSeeder')) {
            $this->call(UsersTableSeeder::class);
        }
        if (class_exists('Database\Seeders\CustomersTableSeeder')) {
            $this->call(CustomersTableSeeder::class);
        }

        // 開発環境のみパスワードをリセット（外部シード後に最終上書き）
        if (App::environment('local')) {
            $this->call(DevUserPasswordSeeder::class);
        }

        // 商品管理や請求書のシーダは無効化（システム安定化のため）
        // $this->call(ProductSeeder::class);

        // 分類と品目のみシード
        $this->call(CategorySeeder::class);
        $this->call(ItemSeeder::class);

        // ユーザーが存在しない場合は見積書と請求書の投入をスキップ
        if (User::count() === 0) {
            $this->command->info('Skipping QuoteSeeder and InvoiceSeeder: No users found in the database.');
            return;
        }

        // 見積書・請求書シードは実施しない
        // $this->call(QuoteSeeder::class);
        // $this->call(InvoiceSeeder::class);
    }
}
