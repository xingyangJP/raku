# RAKUSHIRU Cloud – 全体仕様（最新版）

**概要**
- 目的: 見積→MF見積作成→請求へ変換、ローカル請求→MF請求作成、請求一覧の取得/表示を実装。
- 認証: MoneyForward OAuth2（Authorization Code）。アクセストークンは都度取得（簡易実装）。
- 前提: 取引先と部門はMFに存在し、パートナー同期でローカルDBへ保存済み。

**環境変数**
- MONEY_FORWARD_CLIENT_ID / MONEY_FORWARD_CLIENT_SECRET: MFアプリのクレデンシャル。
- MONEY_FORWARD_QUOTE_SCOPE: 既定 `mfc/invoice/data.write`。
- MONEY_FORWARD_BILLING_REDIRECT_URI: `http://localhost:8000/callback`。
- MONEY_FORWARD_PARTNER_REDIRECT_URI: `http://localhost:8000/mf/partners/callback`。
- MONEY_FORWARD_ESTIMATE_REDIRECT_URI: `http://localhost:8000/estimates/create-quote/callback`。
- MONEY_FORWARD_CONVERT_REDIRECT_URI: `http://localhost:8000/estimates/convert-to-billing/callback`。
- MONEY_FORWARD_QUOTE_VIEW_REDIRECT_URI: `http://localhost:8000/estimates/view-quote/callback`。
- MONEY_FORWARD_INVOICE_REDIRECT_URI: `http://localhost:8000/invoices/send/callback`。

**MF 開発者ポータルに登録する Redirect URI**
- `http://localhost:8000/callback`
- `http://localhost:8000/estimates/create-quote/callback`
- `http://localhost:8000/estimates/convert-to-billing/callback`
- `http://localhost:8000/estimates/view-quote/callback`
- `http://localhost:8000/mf/partners/callback`
- `http://localhost:8000/invoices/send/callback`

**主要フロー**
- 見積→MF見積作成: `GET /estimates/{estimate}/create-quote` → コールバックでトークン交換→ `POST /quotes`。
- 見積→請求へ変換: `GET /estimates/{estimate}/convert-to-billing` → `POST /quotes/{id}/convert_to_billing`。
- 見積PDF表示: `GET /estimates/{estimate}/view-quote` → コールバックでPDFストリーム。
- ローカル請求→MF請求作成: `GET /invoices/{invoice}/send` → `POST /invoice_template_billings`。
- 請求PDF表示（ローカル）: `GET /invoices/{invoice}/view-pdf` → コールバックでPDFストリーム。
- パートナー同期（取引先+部門）: `GET /mf/partners/start` → 一覧取得→詳細でdepartmentsをpayload保存。

**UI 仕様（抜粋）**
- 見積編集: 下部アクションに「マネーフォワードで見積書発行」「請求へ変換」「PDF表示」。PDFは `mf_quote_id` がある時のみ表示。
- 見積一覧: 行メニューから「PDF表示」（`mf_quote_id` がある時のみ）。旧「プレビュー」は廃止。
- 請求編集: 「MF未生成」→送信→生成後は「PDF」。
- 請求一覧: ローカル請求は `mf_billing_id` 有無で「PDF」/「MF未生成」を表示。

**バックエンド要点**
- 見積作成時: `client_id` と `mf_department_id` をMFのpartner detailで検証し、不整合は自動補修。
- ローカル請求送信時: `department_id` をpartner detailで検証し、自動補修。
- OAuth state: フロー毎に別キーで保存/検証/クリア（例: `mf_quote_oauth_state`, `mf_quote_pdf_state`, `mf_invoice_oauth_state`, `mf_invoice_pdf_state`）。
- エラーハンドリング: 422 などの `error_description` をUIに表示。リクエスト/レスポンスはサーバログに出力。

**主なエンドポイント（サーバ）**
- 見積: `routes/web.php:36`、`app/Http/Controllers/EstimateController.php`。
- 請求（ローカル）: `routes/web.php:41`、`app/Http/Controllers/LocalInvoiceController.php`。
- 請求一覧＋MF取得: `app/Http/Controllers/BillingController.php`、`resources/js/Pages/Billing/Index.jsx`。
- 取引先API: `app/Http/Controllers/ApiController.php:87`（疑似部門IDのフォールバック無し）。

**Seeder / データ**
- 既定では `DevDemoSeeder` を使用（疑似部門IDは投入しない）。
- 最新仕様に合うフィクスチャ駆動Seederは追加可能（`database/seeders/data/*.json` からアップサート）。
- 実行: `php artisan migrate:fresh --seed`。

**運用チェックリスト**
- `.env` のクレデンシャルと各 Redirect URI が正しい。
- MFポータルに全てのリダイレクトURIを登録済み。
- ダッシュボードで「取引先同期」を実行し、部門がpayloadに入っている。
- 見積/請求実行時に 422 が出る場合は department を要確認（MF側で部門作成→同期）。

**補足**
- 認可URL/トークンURL: `https://api.biz.moneyforward.com/authorize` / `https://api.biz.moneyforward.com/token`。
- APIベースURL: `https://invoice.moneyforward.com/api/v3`。
- UIは shadcn/ui + Inertia React。旧「プレビュー」機能は廃止済み。

