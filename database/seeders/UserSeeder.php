<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use GuzzleHttp\Client;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $created = 0; $updated = 0; $reset = 0; $bound = 0;

        // 可能であれば外部APIからユーザー一覧を取得し、emailで突合→作成/更新
        $synced = false;
        try {
            $base = rtrim(env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/api'), '/');
            $token = env('EXTERNAL_API_TOKEN');
            $client = new Client([
                'base_uri' => $base . '/',
                'timeout' => 15,
                'http_errors' => false,
                'verify' => env('SSL_VERIFY', true),
            ]);
            $headers = ['Accept' => 'application/json'];
            if ($token) { $headers['Authorization'] = 'Bearer ' . $token; }
            $resp = $client->get('users', ['headers' => $headers]);
            $arr = json_decode((string) $resp->getBody(), true);
            if (is_array($arr)) {
                foreach ($arr as $row) {
                    $extId = (string) ($row['id'] ?? $row['external_user_id'] ?? '');
                    $name  = (string) ($row['name'] ?? $row['full_name'] ?? '');
                    $email = (string) ($row['email'] ?? $row['mail'] ?? '');
                    if (!$email) { continue; }
                    $u = User::where('email', $email)->first();
                    if ($u) {
                        $u->name = $name ?: ($u->name ?: 'ユーザー');
                        if ($extId) { $u->external_user_id = $extId; $bound++; }
                        $u->password = Hash::make('00000000');
                        $u->save();
                        $updated++; $reset++;
                    } else {
                        User::create([
                            'name' => $name ?: 'ユーザー',
                            'email' => $email,
                            'external_user_id' => $extId ?: null,
                            'password' => Hash::make('00000000'),
                        ]);
                        $created++; $reset++;
                        if ($extId) { $bound++; }
                    }
                }
                $synced = true;
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        // 外部同期が失敗/無効の場合、既存ユーザー全員のパスワードを00000000にリセット
        if (!$synced) {
            $all = User::all();
            foreach ($all as $u) {
                $u->password = Hash::make('00000000');
                $u->save();
                $reset++;
                $updated++;
            }
        }

        $total = User::count();
        $this->command?->info(sprintf(
            'ユーザー: 作成%d件／更新%d件／初期PW設定%d件（合計%d件、外部ID紐付け%d件%s）',
            $created, $updated, $reset, $total, $bound, $synced ? '・外部同期あり' : '・外部同期なし'
        ));
    }
}
