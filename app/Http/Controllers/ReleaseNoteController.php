<?php

namespace App\Http\Controllers;

use App\Services\ReleaseNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReleaseNoteController extends Controller
{
    public function markLatestAsRead(Request $request, ReleaseNoteService $releaseNotes): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $latest = $releaseNotes->latest();
        if ($latest && !empty($latest['version'])) {
            $user->forceFill([
                'last_read_release_version' => (string) $latest['version'],
            ])->save();
        }

        return back()->with('success', '最新更新を確認しました。');
    }
}
