<?php

namespace App\Services;

use App\Models\User;

class ReleaseNoteService
{
    public function entries(): array
    {
        return array_values(config('release_notes.entries', []));
    }

    public function latest(?string $currentVersion = null): ?array
    {
        $entries = $this->entries();
        if ($entries === []) {
            return null;
        }

        if ($currentVersion) {
            foreach ($entries as $entry) {
                if (($entry['version'] ?? null) === $currentVersion) {
                    return $entry;
                }
            }
        }

        return $entries[0] ?? null;
    }

    public function isUnread(?User $user, ?array $latest): bool
    {
        if (!$user || !$latest || empty($latest['version'])) {
            return false;
        }

        return (string) ($user->last_read_release_version ?? '') !== (string) $latest['version'];
    }

    public function buildSharedPayload(?User $user, string $currentVersion): array
    {
        $latest = $this->latest($currentVersion);

        return [
            'current_version' => $currentVersion,
            'latest' => $latest,
            'history' => $this->entries(),
            'unread' => $this->isUnread($user, $latest),
        ];
    }
}
