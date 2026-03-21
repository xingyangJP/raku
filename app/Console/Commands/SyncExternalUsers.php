<?php

namespace App\Console\Commands;

use App\Services\ExternalUserSyncService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'users:sync-external')]
class SyncExternalUsers extends Command
{
    protected $signature = 'users:sync-external';

    protected $description = '外部 API のスタッフ一覧を local users へ upsert 同期する';

    public function handle(ExternalUserSyncService $service): int
    {
        $summary = $service->sync(fn (string $message) => $this->info($message));

        if ($summary['fetched'] === 0) {
            $this->error('外部ユーザー一覧を取得できませんでした。EXTERNAL_API_BASE / EXTERNAL_API_TOKEN を確認してください。');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
