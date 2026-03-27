<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Services\ExternalUserSyncService;

class UserSeeder extends Seeder
{
    public function __construct(private readonly ExternalUserSyncService $syncService)
    {
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存ユーザがいる場合は一切変更しない
        if (User::count() > 0) {
            $this->command?->info('UserSeeder: 既存ユーザがあるため何も変更しません。');
            return;
        }

        $summary = $this->syncService->sync(fn (string $message) => $this->command?->info($message));

        if ($summary['fetched'] === 0) {
            $this->command?->warn('外部ユーザー同期は取得0件でした。既存データのみ維持します。');
        }
    }
}
