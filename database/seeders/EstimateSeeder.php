<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estimate;
use GuzzleHttp\Client;

class EstimateSeeder extends Seeder
{
    public function run(): void
    {
        $base = rtrim(env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/api'), '/');
        $token = env('EXTERNAL_API_TOKEN');

        $client = new Client([
            'base_uri' => $base . '/',
            'timeout' => 15,
            'http_errors' => false,
            'verify' => env('SSL_VERIFY', true),
        ]);

        $headers = [
            'Accept' => 'application/json',
        ];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // 外部APIから担当者/顧客を取得
        $usersResp = $client->get('users', ['headers' => $headers]);
        $users = json_decode((string) $usersResp->getBody(), true);
        if (!is_array($users) || empty($users)) {
            throw new \RuntimeException('外部APIからユーザー一覧を取得できませんでした。EXTERNAL_API_* を確認してください。');
        }

        $customersResp = $client->get('customers', ['headers' => $headers]);
        $customers = json_decode((string) $customersResp->getBody(), true);
        if (!is_array($customers) || empty($customers)) {
            throw new \RuntimeException('外部APIから顧客一覧を取得できませんでした。EXTERNAL_API_* を確認してください。');
        }

        $pickUsers = collect($users)->values();
        $pickCustomers = collect($customers)->values();
        // Faker は本番デプロイで --no-dev の場合に存在しないため、存在チェックしてフォールバック
        $faker = null;
        if (class_exists('Faker\\Factory')) {
            $faker = \Faker\Factory::create('ja_JP');
        }

        // 5種の明細テンプレート
        $catalog = [
            ['name' => '要件定義',   'unit' => '式',   'price' => 120000, 'cost' => 60000],
            ['name' => '基本設計',   'unit' => '式',   'price' => 150000, 'cost' => 80000],
            ['name' => '詳細設計/開発', 'unit' => '人日', 'price' => 80000,  'cost' => 50000],
            ['name' => '総合テスト', 'unit' => '人日', 'price' => 70000,  'cost' => 40000],
            ['name' => '導入支援/教育', 'unit' => '時間', 'price' => 9000,   'cost' => 4500],
        ];

        $created = 0;
        for ($i = 0; $i < 50; $i++) {
            $user = $pickUsers[$i % max(1, $pickUsers->count())];
            $customer = $pickCustomers[$i % max(1, $pickCustomers->count())];

            $staffId = $user['id'] ?? null;
            $staffName = $user['name'] ?? ($user['full_name'] ?? '担当者');
            $clientId = (string) ($customer['id'] ?? $customer['client_id'] ?? '0');
            $customerName = $customer['customer_name'] ?? ($customer['name'] ?? '顧客');

            $issue = now()->copy()->subDays(random_int(0, 30))->startOfDay();
            $due = $issue->copy()->addDays(random_int(14, 45));

            // 番号: EST-{staff}-{client}-{yyddmm}-{seq}
            $date = $issue->format('ydm');
            $prefix = "EST-{$staffId}-{$clientId}-{$date}-";
            $latest = Estimate::where('estimate_number', 'like', $prefix.'%')
                ->orderBy('estimate_number', 'desc')
                ->first();
            $seq = 1;
            if ($latest) {
                $tail = substr($latest->estimate_number, strlen($prefix));
                $num = (int) $tail;
                $seq = $num + 1;
            }
            $number = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            // 明細5件を生成
            $items = [];
            foreach ($catalog as $tpl) {
                $qty = $tpl['unit'] === '人日' ? rand(3, 12) : ($tpl['unit'] === '時間' ? rand(8, 20) : rand(1, 3));
                $price = (int) round($tpl['price'] * (rand(80, 120) / 100));
                $cost  = (int) round(min($price - 1000, $tpl['cost'] * (rand(80, 120) / 100)));
                $items[] = [
                    'id' => now()->valueOf() + rand(1, 999),
                    'product_id' => null,
                    'name' => $tpl['name'],
                    'description' => $faker ? $faker->realText(rand(40, 80)) : '自動生成の説明文（' . $tpl['name'] . '）',
                    'qty' => $qty,
                    'unit' => $tpl['unit'],
                    'price' => $price,
                    'cost' => $cost,
                    'tax_category' => 'standard',
                ];
            }

            $subtotal = collect($items)->reduce(fn($c,$it) => $c + ($it['qty'] * $it['price']), 0);
            $tax = (int) round($subtotal * 0.1);
            $total = $subtotal + $tax;

            Estimate::create([
                'customer_name' => $customerName,
                'client_id' => $clientId,
                'title' => ($faker ? $faker->randomElement(['基幹システムリニューアル','ECサイト機能追加','営業支援ツール改修','在庫管理システム保守','クラウド移行支援']) : '見積プロジェクト') . ' ' . ($i + 1),
                'issue_date' => $issue,
                'due_date' => $due,
                'status' => 'draft',
                'total_amount' => $total,
                'tax_amount' => $tax,
                'notes' => $faker ? $faker->realText(rand(60, 120)) : '自動生成の備考です。',
                'items' => $items,
                'estimate_number' => $number,
                'staff_id' => $staffId,
                'staff_name' => $staffName,
            ]);
            $created++;
        }
        $this->command?->info(sprintf('見積: %d件作成', $created));
    }
}
