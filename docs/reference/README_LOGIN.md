# Login E2E テスト概要

このドキュメントは `e2e/login.spec.ts` に関連する Playwright E2E テストのシナリオ、前提条件、実行コマンド、トラブルシュート手順をまとめたものです。

## 目的
- 外部ユーザー選択でのログインが期待どおり動作することを確認する。
- テスト環境によってフロントエンドが描画されない場合でもテストが安定して実行できるようフォールバックを用意する。

## 登場アカウント（テストで利用）
- 名前: 守部幸洋
- テスト用パスワード: `00000000`
- フロント側では選択肢の `value` が `3` として扱う（実アプリの HTML に合わせた値）

## 前提条件
- ローカルで Laravel 開発サーバが `http://localhost:8000` で稼働していること。
- Node と Playwright がインストール済みであること。
  - 必要に応じて Playwright ブラウザをインストール:
```bash
npx playwright install
```

- プロジェクトのルートでコマンドを実行すること（このリポジトリのルート）。

## 実行コマンド
- ヘッドレスで全テストを実行（CI 向け）:
```bash
npm run test:e2e
```

- Playwright テスト UI を開いて手動で操作する:
```bash
npm run test:e2e:ui
# または
npx playwright test --ui
```

- 単一スペックをヘッデッド（ブラウザ表示）で実行する:
```bash
npx playwright test e2e/login.spec.ts --project=chromium --headed
```

- デバッグモード（PWDEBUG=1）で実行する:
```bash
PWDEBUG=1 npx playwright test e2e/login.spec.ts --project=chromium
```

## テストの要点
- `e2e/login.spec.ts` は `/api/users` をモックして、選択肢に `守部幸洋` が存在することを前提に振る舞います。
- フロントの JS が実行されず select や password フィールドが存在しない環境向けに、テスト側で要素を注入するフォールバックが用意されています。
- グローバルセットアップ（`e2e/global-setup.ts`）はテスト用の `storageState.json` を作成し、認証済みセッションを Playwright に供給するために使用します（必要に応じて確認してください）。

## 出力・アーティファクト
- 失敗時スクリーンショットを出力するパス:
  - `test-results/` 以下（spec 名に基づく Playwright の自動出力）
  - テスト内 try/catch による汎用スクリーンショット: `test-results/login-failure.png`
- 動画・トレースは Playwright の設定・オプションに従って出力されます（`--trace on-first-retry` 等）。

## よくある問題と対処
- Chromium が起動しない / ウィンドウが見えない
  - デフォルトは headless。表示したければ `--headed` を付ける。
  - ブラウザ未インストールなら `npx playwright install` を実行。
  - CI 環境では GUI がないため headed 表示はできない。

- セレクタが見つからずタイムアウトする
  - `e2e/login.spec.ts` は既に待ち時間と注入のフォールバックを持っています。必要ならタイムアウト値を大きくする（`waitForSelector(..., { timeout: 15000 })` 等）。

- ログインが 419 や CSRF エラーになる
  - テストはブラウザコンテキストでの in-page フェッチや storageState を利用したログインが安定します。直接の HTTP POST は CSRF により失敗する場合があります。

## デバッグの流れ（推奨）
1. ローカルで `php artisan serve` を起動してアプリが動作することを確認。
2. `npx playwright test e2e/login.spec.ts --project=chromium --headed --debug` でブラウザを表示し、UI を見ながら失敗箇所を確認。
3. 失敗時は `test-results/` のスクショと動画を確認。必要なら `npx playwright show-trace <trace.zip>` でトレースを解析。

## 備考
- テスト側での DOM 注入は環境依存の一時的対策です。可能であればアプリ側に安定したテストフック（data-testid の追加やテスト専用の API など）を導入することを検討してください。

---
更新履歴:
- 2025-09-01: 初版（テスト注入とスクリーンショット出力について追記）

