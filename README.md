# RAKUSHIRU Cloud – Overview

RAKUSHIRU Cloud は、社内の見積・請求ワークフローを Money Forward クラウド請求書と連携させる Laravel 12 + Inertia(React) アプリケーションです。  
ローカルでの承認フロー、商品／分類マスタ、Money Forward 側の各 API との同期機能をひとつの UI に集約しています。

## Tech Stack
- PHP 8.2 / Laravel 12
- Inertia.js + React + shadcn/ui + Tailwind CSS
- MySQL（デフォルト）
- Playwright (hyvor/laravel-playwright) による E2E テスト

## Core Modules
### Dashboard
- 承認待ち見積の「やることリスト」を表示し、ログインユーザーが次の承認者であればその場で詳細シートを開けます。
- 売上サマリなどのダミーカードを表示（数値は将来の指標表示用プレースホルダ）。
- 画面表示時に Money Forward の取引先同期を自動実行。未認証なら OAuth にリダイレクトし、結果はダッシュボード内に専用メッセージで表示されます。手動で再実行したい場合の「取引先取得」ボタンも残しています。

### Estimates & Quotes
- `/quotes` で見積の一覧・絞り込み・一括操作を提供。`MoneyForwardQuoteSynchronizer` によりページ表示時に Money Forward の見積を同期します。
- `/estimates/{id}/edit` では shadcn コンポーネントを用いた編集 UI を提供。行追加や社内ビュー切替、承認ワークフロー管理をサポートします。
- Money Forward 連携ボタン:
  - 「マネーフォワードで見積書発行」: `/estimates/{estimate}/create-quote` → `MoneyForwardApiService::createQuoteFromEstimate`
  - 「請求へ変換」: `/estimates/{estimate}/convert-to-billing` → `convertQuoteToBilling`
  - 「MF見積PDF表示」: `/estimates/{estimate}/view-quote`
- 見積番号は `Estimate::generateReadableEstimateNumber()` で採番。承認ステータスは `draft` / `pending` / `sent`。

### Billing & Local Invoices
- `/billing` は `MoneyForwardBillingSynchronizer` で Money Forward 側の請求書を取得し、ローカル請求書（`local_invoices`）とマージして表示します。
- フィルタ（請求月レンジ、取引先、ステータス等）、同期ステータスバナー、Money Forward へのリンク／PDF 表示を提供。
- ローカル請求書編集 `/invoices/{id}/edit` では部門補完や明細整形を行い、`/invoices/{invoice}/send` から Money Forward へテンプレート請求書を作成可能です。
- Money Forward PDF のダウンロードはトークンが有効な場合は即時ストリーミング、失効時は OAuth を再実行します。

### Product Master & Categories
- `/products` は商品マスタ管理画面。分類(`categories`)はダイアログで CRUD、分類コードはサーバ側で A, B, … と自動採番します。
- 商品コードは `<分類コード>-<3桁連番>` で自動生成。分類変更時もトランザクションでシーケンスを更新します。
- Money Forward との品目同期:
  - `/products` 表示時にローカルの商品マスタを Money Forward 側へ自動同期（作成・更新・削除を差分処理）。未認証時は OAuth に遷移し、復帰後に同期が継続されます。
  - 画面右上の「MFへ同期」ボタンで同じ処理を手動実行可能。行単位の同期ボタンは廃止しました。

### Partner Sync
- ダッシュボードの「取引先取得」で `/mf/partners/auth/start` が呼ばれ、OAuth 後に `MoneyForwardApiService::fetchAllPartners` と `fetchPartnerDetail` を用いて部門情報まで含めて `partners` テーブルに保存します。
- 編集画面では `/api/partners/{partner}/departments` 経由で部門候補を取得。

## Money Forward OAuth Flows
| ユースケース | 開始ルート | コールバック | スコープ |
| --- | --- | --- | --- |
| 請求書一覧同期 | `GET /mf/billings/auth/start` | `GET /callback` | `mfc/invoice/data.read` |
| 見積一覧同期 | `GET /quotes/mf/auth/start` | `GET /quotes/mf/auth/callback` | `mfc/invoice/data.read` |
| 見積→MF操作（発行/変換/PDF） | `GET /estimates/auth/start` | `GET /estimates/auth/callback` | 認証時のアクションに応じて `data.read` / `data.write` |
| ローカル請求→MF発行 | `GET /invoices/{invoice}/send` | `GET /invoices/send/callback` | `mfc/invoice/data.write` |
| ローカル請求PDF取得（フォールバック） | `GET /invoices/{invoice}/view-pdf` | `GET /invoices/view-pdf/callback` | `mfc/invoice/data.read` |
| パートナー同期 | `GET /mf/partners/auth/start` | `GET /mf/partners/auth/callback` | `mfc/invoice/data.read mfc/invoice/data.write` |
| 商品同期 | `GET /products/auth/start` | `GET /products/auth/callback` | `mfc/invoice/data.read mfc/invoice/data.write` |

## Required Environment Variables
- `MONEY_FORWARD_CLIENT_ID` / `MONEY_FORWARD_CLIENT_SECRET`: Money Forward アプリのクレデンシャル。
- `MONEY_FORWARD_QUOTE_SCOPE` (既定 `mfc/invoice/data.write`): 見積→請求など書き込みフローで使用。
- `MONEY_FORWARD_BILLING_REDIRECT_URI` (省略時 `route('money-forward.callback')`): 請求同期用コールバック URL。
- `MONEY_FORWARD_INVOICE_REDIRECT_URI` (省略時 `route('invoices.send.callback')`): ローカル請求送信／PDF フォールバックで使用。
- `MONEY_FORWARD_QUOTE_REDIRECT_URI` (省略時 `route('quotes.auth.callback')`): 見積一覧同期で使用。
- `MONEY_FORWARD_PARTNER_AUTH_REDIRECT_URI` (省略時 `route('partners.auth.callback')`): パートナー同期用。
- `MONEY_FORWARD_BILLING_SYNC_THROTTLE_MINUTES`, `MONEY_FORWARD_BILLING_SYNC_PAGE_SIZE`: 請求同期の頻度とページサイズ。
- `MONEY_FORWARD_QUOTE_SYNC_THROTTLE_MINUTES`, `MONEY_FORWARD_QUOTE_SYNC_PAGE_SIZE`: 見積同期の頻度とページサイズ。

> 旧 `*_REDIRECT_URI` 系の環境変数（例: `MONEY_FORWARD_ESTIMATE_REDIRECT_URI`）は現行コードでは使用していません。必要であれば将来的な互換用として残置できますが、設定しなくても動作します。

## Redirect URIs to Register in Money Forward
アプリポータルに以下を登録してください（ローカル開発時の例）:
- `http://localhost:8000/callback`
- `http://localhost:8000/estimates/auth/callback`
- `http://localhost:8000/quotes/mf/auth/callback`
- `http://localhost:8000/invoices/send/callback`
- `http://localhost:8000/invoices/view-pdf/callback`
- `http://localhost:8000/mf/partners/auth/callback`
- `http://localhost:8000/products/auth/callback`

必要に応じて本番 URL に置き換えてください。

## Data Highlights
- `estimates`: 見積本体。`items` は JSON カラムで UI から編集可。`approval_flow` に承認者配列を保持。
- `local_invoices`: ローカル請求。Money Forward 連携済みの場合 `mf_billing_id` / `mf_pdf_url` を保持。
- `billings`: Money Forward から同期した請求を保存するローカルキャッシュ。明細は `billing_items` に保持。
- `categories` / `products`: 商品マスタ。分類ごとの `last_item_seq` と商品自動採番に注意。
- `mf_tokens`: ユーザーごとのアクセストークンとリフレッシュトークンを保存。期限切れ時は自動更新を試行。

## Development Notes
- 初期セットアップ: `composer install && npm install && php artisan migrate --seed`
- 開発サーバ: `composer dev` (Laravel サーバ、キュー、ログ、Vite を並列実行)
- Playwright テスト: `npm run test:e2e`
- Money Forward API エラーは `storage/logs/laravel.log` と `MoneyForwardApiService` のログ出力を参照。
