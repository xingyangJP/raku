<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Estimate;
use App\Models\User;
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

        // products テーブルから品目を取得（UIのセレクトと一致させる）
        $productRows = DB::table('products')->orderBy('id')->get();
        // フォールバックのカタログ
        $catalog = [
            ['id'=>1,'name'=>'要件定義','unit'=>'式','price'=>120000,'cost'=>60000,'tax_category'=>'standard'],
            ['id'=>2,'name'=>'基本設計','unit'=>'式','price'=>150000,'cost'=>80000,'tax_category'=>'standard'],
            ['id'=>3,'name'=>'詳細設計/開発','unit'=>'人日','price'=>80000,'cost'=>50000,'tax_category'=>'standard'],
            ['id'=>4,'name'=>'総合テスト','unit'=>'人日','price'=>70000,'cost'=>40000,'tax_category'=>'standard'],
            ['id'=>5,'name'=>'導入支援/教育','unit'=>'時間','price'=>9000,'cost'=>4500,'tax_category'=>'standard'],
        ];

        $created = 0;
        $createdIds = [];
        $totalToCreate = 50;
        for ($i = 0; $i < $totalToCreate; $i++) {
            $user = $pickUsers[$i % max(1, $pickUsers->count())];
            $customer = $pickCustomers[$i % max(1, $pickCustomers->count())];

            $staffId = $user['id'] ?? null;
            $staffName = $user['name'] ?? ($user['full_name'] ?? '担当者');
            $clientId = (string) ($customer['id'] ?? $customer['client_id'] ?? '0');
            $customerName = $customer['customer_name'] ?? ($customer['name'] ?? '顧客');

            $issue = now()->copy()->subDays(random_int(0, 30))->startOfDay();
            $due = $issue->copy()->addDays(random_int(14, 45));

            $number = Estimate::generateReadableEstimateNumber(
                $staffId,
                $clientId,
                true // Create all as drafts first
            );

            // 明細（3〜7件）を生成。productsがあればそこから、なければフォールバックから。
            $items = [];
            $source = $productRows->count() ? $productRows->toArray() : $catalog;
            $count = rand(3, 7);
            for ($k = 0; $k < $count; $k++) {
                $p = $source[$k % count($source)];
                $pid = is_object($p) ? $p->id : $p['id'];
                $pname = is_object($p) ? $p->name : $p['name'];
                $punit = is_object($p) ? $p->unit : $p['unit'];
                $pprice = (int) (is_object($p) ? $p->price : $p['price']);
                $pcost = (int) (is_object($p) ? $p->cost : $p['cost']);
                $ptax = is_object($p) ? ($p->tax_category ?? 'standard') : ($p['tax_category'] ?? 'standard');
                $psku = is_object($p) ? ($p->sku ?? null) : ($p['sku'] ?? null);
                $qty = $punit === '人日' ? rand(3, 12) : ($punit === '時間' ? rand(8, 20) : rand(1, 3));
                $items[] = [
                    'id' => now()->valueOf() + rand(1, 999),
                    'product_id' => $pid,
                    'name' => $pname,
                    'description' => $faker ? $faker->realText(rand(40, 80)) : '自動生成の説明文（' . $pname . '）',
                    'qty' => $qty,
                    'unit' => $punit,
                    'price' => $pprice,
                    'cost' => $pcost,
                    'tax_category' => $ptax,
                    'sku' => $psku,
                ];
            }

            $subtotal = collect($items)->reduce(fn($c,$it) => $c + ($it['qty'] * $it['price']), 0);
            $tax = (int) round($subtotal * 0.1);
            $total = $subtotal + $tax;

            $seqTitle = str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $e = Estimate::create([
                'customer_name' => $customerName,
                'client_id' => $clientId,
                'title' => '開発プロジェクト-' . $seqTitle,
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
            $createdIds[] = $e->id;
        }

        // 承認フロー付きのサンプルを20件作成（4パターン×各5件）
        $allUsers = User::orderBy('id')->take(6)->get();
        $patterns = ['allApproved','twoApproved','oneApproved','noneApproved'];
        $perPattern = 5; // 合計20件

        if ($allUsers->count() >= 3 && count($createdIds) >= ($perPattern * count($patterns))) {
            $mkStep = function($u, $approvedAt = null) {
                $id = $u->external_user_id ?: $u->id; // 外部ID優先
                return [
                    'id' => is_numeric($id) ? (int) $id : (string) $id,
                    'name' => $u->name,
                    'approved_at' => $approvedAt,
                    'status' => $approvedAt ? 'approved' : 'pending',
                ];
            };

            $targetIds = array_slice($createdIds, 0, $perPattern * count($patterns));
            $chunks = array_chunk($targetIds, $perPattern);

            foreach ($patterns as $pi => $pat) {
                $ids = $chunks[$pi] ?? [];
                foreach ($ids as $estId) {
                    $est = Estimate::find($estId);
                    if (!$est) continue;

                    // Regenerate number from draft to final, as these are not drafts anymore
                    $est->estimate_number = Estimate::generateReadableEstimateNumber(
                        $est->staff_id,
                        $est->client_id,
                        false
                    );
                    if ($pat === 'allApproved') {
                        $est->approval_flow = [
                            $mkStep($allUsers[0], now()->subDays(3)->toDateTimeString()),
                            $mkStep($allUsers[1], now()->subDays(2)->toDateTimeString()),
                            $mkStep($allUsers[2], now()->subDay()->toDateTimeString()),
                        ];
                        $est->status = 'sent';
                        $est->approval_started = false;
                    } elseif ($pat === 'twoApproved') {
                        $est->approval_flow = [
                            $mkStep($allUsers[0], now()->subDays(3)->toDateTimeString()),
                            $mkStep($allUsers[1], now()->subDay()->toDateTimeString()),
                            $mkStep($allUsers[2], null),
                        ];
                        $est->status = 'pending';
                        $est->approval_started = true;
                    } elseif ($pat === 'oneApproved') {
                        $est->approval_flow = [
                            $mkStep($allUsers[0], now()->subDay()->toDateTimeString()),
                            $mkStep($allUsers[1], null),
                            $mkStep($allUsers[2], null),
                        ];
                        $est->status = 'pending';
                        $est->approval_started = true;
                    } else { // noneApproved
                        $est->approval_flow = [
                            $mkStep($allUsers[0], null),
                            $mkStep($allUsers[1], null),
                            $mkStep($allUsers[2], null),
                        ];
                        $est->status = 'pending';
                        $est->approval_started = true;
                    }
                    $est->save();
                }
            }
        }

        $this->command?->info(sprintf('見積: %d件作成（承認フローサンプル20件、残りドラフト）', $created));
    }
}
