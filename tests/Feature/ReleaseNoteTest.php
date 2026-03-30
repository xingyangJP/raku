<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReleaseNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_shares_latest_release_note_as_unread_for_user(): void
    {
        $user = User::factory()->create([
            'last_read_release_version' => 'v1.0.16',
        ]);

        $response = $this->actingAs($user)->get(route('help.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Help/Index')
                ->where('releaseNotes.latest.version', 'v1.0.19')
                ->where('releaseNotes.unread', true)
                ->has('releaseNotes.history', 3);
        });
    }

    public function test_release_notes_page_renders_dedicated_component(): void
    {
        $user = User::factory()->create([
            'last_read_release_version' => null,
        ]);

        $response = $this->actingAs($user)->get(route('release-notes.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('ReleaseNotes/Index')
                ->where('releaseNotes.latest.version', 'v1.0.19')
                ->where('releaseNotes.unread', true);
        });
    }

    public function test_mark_latest_release_note_as_read_persists_user_flag(): void
    {
        $user = User::factory()->create([
            'last_read_release_version' => null,
        ]);

        $response = $this->actingAs($user)
            ->from(route('help.index'))
            ->post(route('release-notes.readLatest'));

        $response->assertRedirect(route('help.index'));
        $response->assertSessionHas('success', '最新更新を確認しました。');
        $this->assertSame('v1.0.19', $user->fresh()->last_read_release_version);
    }
}
