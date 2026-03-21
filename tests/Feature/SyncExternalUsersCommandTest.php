<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncExternalUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_external_users_command_upserts_users(): void
    {
        $existing = User::query()->create([
            'name' => '旧名',
            'email' => 'sync-target@example.com',
            'external_user_id' => null,
            'password' => Hash::make('secret'),
        ]);

        Http::fake([
            '*' => Http::response([
                [
                    'id' => 21,
                    'name' => '更新後ユーザー',
                    'email' => 'sync-target@example.com',
                ],
                [
                    'id' => 22,
                    'name' => '新規ユーザー',
                    'email' => 'new-user@example.com',
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('users:sync-external');

        $this->assertSame(0, $exitCode);

        $existing->refresh();
        $this->assertSame('更新後ユーザー', $existing->name);
        $this->assertSame('21', (string) $existing->external_user_id);

        $created = User::query()->where('email', 'new-user@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame('新規ユーザー', $created->name);
        $this->assertSame('22', (string) $created->external_user_id);
    }
}
