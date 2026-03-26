<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleDriveDocumentService
{
    public function fetchDocument(string $accessToken, ?string $fileId = null, ?string $url = null): array
    {
        $resolvedFileId = $this->extractFileId($fileId, $url);
        if (!$resolvedFileId) {
            throw new \InvalidArgumentException('Google Drive のファイルIDを特定できませんでした。');
        }

        $metadata = $this->request($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$resolvedFileId}", [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => 'true',
            ])
            ->throw()
            ->json();

        $mimeType = (string) ($metadata['mimeType'] ?? '');
        $name = (string) ($metadata['name'] ?? '要件定義書');
        $webViewLink = (string) ($metadata['webViewLink'] ?? $url ?? '');

        if ($mimeType === 'application/vnd.google-apps.document') {
            $content = $this->request($accessToken)
                ->get("https://www.googleapis.com/drive/v3/files/{$resolvedFileId}/export", [
                    'mimeType' => 'text/plain',
                    'supportsAllDrives' => 'true',
                ])
                ->throw()
                ->body();
        } elseif (in_array($mimeType, ['text/plain', 'text/markdown'], true)) {
            $content = $this->request($accessToken)
                ->get("https://www.googleapis.com/drive/v3/files/{$resolvedFileId}", [
                    'alt' => 'media',
                    'supportsAllDrives' => 'true',
                ])
                ->throw()
                ->body();
        } else {
            throw new \RuntimeException('現在は Google ドキュメントまたはテキストファイルのみ対応しています。');
        }

        $normalized = preg_replace("/\r\n?/", "\n", (string) $content);
        $normalized = trim((string) $normalized);
        if ($normalized === '') {
            throw new \RuntimeException('要件定義書の内容を取得できませんでした。');
        }

        return [
            'file_id' => $resolvedFileId,
            'name' => $name,
            'mime_type' => $mimeType,
            'web_view_link' => $webViewLink,
            'content' => mb_substr($normalized, 0, 20000),
        ];
    }

    public function extractFileId(?string $fileId = null, ?string $url = null): ?string
    {
        $candidate = trim((string) $fileId);
        if ($candidate !== '') {
            return $candidate;
        }

        $targetUrl = trim((string) $url);
        if ($targetUrl === '') {
            return null;
        }

        $patterns = [
            '~/document/d/([a-zA-Z0-9_-]+)~',
            '~/file/d/([a-zA-Z0-9_-]+)~',
            '~/spreadsheets/d/([a-zA-Z0-9_-]+)~',
            '~/presentation/d/([a-zA-Z0-9_-]+)~',
            '~[?&]id=([a-zA-Z0-9_-]+)~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $targetUrl, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20);
    }
}
