<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AppVersionComposer
{
    protected $appVersion;

    public function __construct()
    {
        $this->appVersion = self::getAppVersion();
        Log::debug('AppVersionComposer: appVersion set to ' . $this->appVersion);
    }

    public static function getAppVersion(): string
    {
        $commitCount = self::resolveCommitCount();

        if (!is_null($commitCount)) {
            $major = 1;
            $minor = (int) floor($commitCount / 100);
            $patch = $commitCount % 100;

            return "v{$major}.{$minor}.{$patch}";
        }

        return Config::get('app.version', 'v1.0.0');
    }

    // composeメソッドで、計算されたバージョンをビューにバインド
    public function compose(View $view)
    {
        $view->with('appVersion', $this->appVersion);
    }

    private static function resolveCommitCount(): ?int
    {
        if (!function_exists('shell_exec')) {
            Log::warning('AppVersionComposer: shell_exec is disabled. Falling back to configured version.');
            return null;
        }

        $gitDir = base_path('.git');
        if (!is_dir($gitDir)) {
            Log::warning('AppVersionComposer: .git directory not found. Falling back to configured version.');
            return null;
        }

        $gitDirArg = escapeshellarg($gitDir);
        $workTreeArg = escapeshellarg(base_path());
        $command = "git --git-dir={$gitDirArg} --work-tree={$workTreeArg} rev-list --count HEAD";

        try {
            $output = shell_exec($command . ' 2>&1');
        } catch (\Throwable $e) {
            Log::error('AppVersionComposer: failed to execute git command.', [
                'command' => $command,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if ($output === null) {
            Log::warning('AppVersionComposer: git command returned null output. Falling back to configured version.', [
                'command' => $command,
            ]);
            return null;
        }

        $trimmed = trim($output);
        if (!preg_match('/^\d+$/', $trimmed)) {
            Log::warning('AppVersionComposer: unexpected git output, falling back to configured version.', [
                'command' => $command,
                'output' => $trimmed,
            ]);
            return null;
        }

        return (int) $trimmed;
    }
}
