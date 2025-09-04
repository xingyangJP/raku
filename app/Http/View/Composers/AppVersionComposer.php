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
        try {
            // Gitコマンドを実行して総コミット数を取得
            // `git rev-list --all --count` は、リポジトリ内のすべてのコミットの総数を返します。
            $commitCount = (int) shell_exec('git rev-list --all --count');

            if ($commitCount > 0) {
                // 仕様に基づき、コミット数をセマンティックバージョニング形式に変換
                // v{major}.{minor}.{patch}
                // majorは1で固定
                $major = 1;
                // minorはコミット数を100で割った商（小数点以下切り捨て）
                $minor = floor($commitCount / 100);
                // patchはコミット数を100で割った余り
                $patch = $commitCount % 100;

                return "v{$major}.{$minor}.{$patch}";
            } else {
                // Gitコマンドが成功したがコミットが0の場合、またはGitコマンドが失敗した場合のフォールバック
                // config/app.php の 'version' 設定値を使用。未設定の場合は 'v1.0.0' をデフォルトとする。
                return Config::get('app.version', 'v1.0.0');
            }
        } catch (\Exception $e) {
            // 例外（shell_execの失敗など）が発生した場合のエラーハンドリング
            Log::error('Gitコミット数の取得に失敗しました: ' . $e->getMessage());
            // 例外発生時もフォールバックバージョンを使用
            return Config::get('app.version', 'v1.0.0');
        }
    }

    // composeメソッドで、計算されたバージョンをビューにバインド
    public function compose(View $view)
    {
        $view->with('appVersion', $this->appVersion);
    }
}
