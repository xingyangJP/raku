<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class ExternalUserSyncService
{
    public function sync(?callable $logger = null): array
    {
        $users = $this->fetchUsers();
        $created = 0;
        $updated = 0;
        $bound = 0;

        foreach ($users as $row) {
            $extId = (string) ($row['id'] ?? $row['external_user_id'] ?? '');
            $name = trim((string) ($row['name'] ?? $row['full_name'] ?? ''));
            $email = trim((string) ($row['email'] ?? $row['mail'] ?? ''));

            if ($email === '' && $extId === '') {
                continue;
            }

            $user = null;
            if ($extId !== '') {
                $user = User::query()->where('external_user_id', $extId)->first();
            }

            if (!$user && $email !== '') {
                $user = User::query()->where('email', $email)->first();
            }

            if ($user) {
                $dirty = false;
                if ($name !== '' && $user->name !== $name) {
                    $user->name = $name;
                    $dirty = true;
                }
                if ($email !== '' && $user->email !== $email) {
                    $user->email = $email;
                    $dirty = true;
                }
                if ($extId !== '' && (string) $user->external_user_id !== $extId) {
                    $user->external_user_id = $extId;
                    $dirty = true;
                    $bound++;
                }
                if ($dirty) {
                    $user->save();
                    $updated++;
                }
                continue;
            }

            User::query()->create([
                'name' => $name !== '' ? $name : 'ユーザー',
                'email' => $email !== '' ? $email : sprintf('external-user-%s@example.local', $extId),
                'external_user_id' => $extId !== '' ? $extId : null,
                'password' => Hash::make('00000000'),
            ]);
            $created++;
            if ($extId !== '') {
                $bound++;
            }
        }

        $summary = [
            'fetched' => $users->count(),
            'created' => $created,
            'updated' => $updated,
            'bound' => $bound,
            'total' => User::query()->count(),
        ];

        if ($logger) {
            $logger(sprintf(
                '外部ユーザー同期: 取得%d件 / 作成%d件 / 更新%d件 / external_user_id紐付け%d件 / 合計%d件',
                $summary['fetched'],
                $summary['created'],
                $summary['updated'],
                $summary['bound'],
                $summary['total'],
            ));
        }

        return $summary;
    }

    private function fetchUsers(): Collection
    {
        $candidates = collect([
            rtrim((string) env('EXTERNAL_API_BASE', ''), '/'),
            'https://api.xerographix.co.jp/api',
            'https://api.xerographix.co.jp/public/api',
        ])->filter()->unique()->values();

        $token = (string) env('EXTERNAL_API_TOKEN', '');
        $headers = array_filter([
            'Accept' => 'application/json',
            'Authorization' => $token !== '' ? 'Bearer ' . $token : null,
        ]);

        foreach ($candidates as $base) {
            $response = Http::withHeaders($headers)
                ->withOptions([
                    'verify' => env('SSL_VERIFY', true),
                ])
                ->timeout(20)
                ->get($base . '/users');

            if (!$response->successful()) {
                continue;
            }

            $payload = $response->json();
            if (is_array($payload)) {
                return collect($payload);
            }
        }

        return collect();
    }
}
