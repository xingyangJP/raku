## 見積書からMoney Forward見積書発行機能の実装

### バックエンド

- `routes/web.php`
  - Money Forward見積書発行を開始するためのルートを追加 (`estimates.createQuote.start`)
  - Money Forwardからのコールバックを処理するためのルートを追加 (`estimates.createQuote.callback`)
- `app/Http/Controllers/EstimateController.php`
  - `redirectToAuthForQuoteCreation` メソッドを追加し、Money Forwardの認証ページへリダイレクトする処理を実装。
  - `handleQuoteCreationCallback` メソッドを追加し、認証後のコールバックを処理し、見積書発行、DB更新を行うロジックを実装。
- `app/Services/MoneyForwardApiService.php`
  - `createQuoteFromEstimate` メソッドを追加し、Money Forward APIを利用して見積書を作成する処理を実装。

### フロントエンド

- `resources/js/Pages/Estimates/Create.jsx`
  - 「マネーフォワードで見積書発行」ボタンを追加。
    - 見積ステータスが「承認済み」(`sent`)、かつ顧客に `mf_partner_id` が設定されている場合に表示される。
  - 「見積書を確認」ボタンを追加。
    - `mf_quote_pdf_url` が存在する場合に表示され、クリックするとPDFを新しいタブで開く。

### 本番環境でのMoney Forward見積書発行の不具合修正 (2025-09-17)

- **事象:** 本番環境でのみ、Money Forwardへの見積書発行が権限不足 (`403 Forbidden`) で失敗する。
- **原因調査:**
  - ログ分析とデバッグにより、DBに保存されているアクセストークンの権限 (スコープ) に `mfc/invoice/data.write` が含まれていないことを特定。
  - さらに調査を進め、ダッシュボードの「取引先同期」機能が `mfc/invoice/data.read` のみを持つトークンを生成してしまい、その後の見積作成処理を妨げていることが判明。
- **修正内容:**
  - `app/Http/Controllers/DashboardController.php` の認証リダイレクト処理を修正。
  - 要求するスコープに `mfc/invoice/data.write` を追加し、アプリケーション全体で要求するスコープを統一。
  - これにより、どの機能から認証を開始しても、すべてのAPI操作に必要な権限を持つアクセストークンが生成されるようになった。

## 2025-09-18
### 要約
- 請求書一覧でMoney Forward請求をアクセス都度自動同期し、旧売掛画面の機能を統合して一元管理できるようにした。

### 変更点
- `app/Services/MoneyForwardBillingSynchronizer.php`
  - Money Forward請求書を取得してDBへ反映する同期サービスを新規追加。
  - PDFダウンロードも自動化し、`billing.downloadPdf` の取得失敗を防止。
  - Money Forward APIのorder指定が422となるため、ソートはローカルで実施するよう修正。
- `app/Services/MoneyForwardApiService.php`
  - `fetchBillings` を実装し、請求書一覧をAPIから取得可能に。
- `config/services.php`
  - 同期間隔(`MONEY_FORWARD_BILLING_SYNC_THROTTLE_MINUTES`)とページサイズ設定を追加。
- `app/Http/Controllers/BillingController.php`
  - 画面表示前に同期処理を実行し、未認証時はMoney Forward認証ページへ強制遷移する仕様に変更。
  - コールバックでアクセストークンを保存し、同期サービスを用いて請求データを更新。
  - 請求・売掛のいずれも請求月で絞り込むよう、Billings/LocalInvoice取得を月範囲に制限。
- `app/Http/Controllers/SalesController.php`
  - Billing画面へ統合したため削除。
- `resources/js/Pages/Billing/Index.jsx`
  - Money Forward同期状況(最終同期/前回同期時刻)を表示し、請求月レンジ・キーワード等の最小限フィルタだけを残して再構成。
  - 売掛サマリー/一覧・支払ステータス系フィルタを削除し、ナビゲーションからも関連導線を廃止。
  - 請求日フィルタを請求月(年月)ベースに改修し、初期値は前月に自動設定。
- `resources/js/Pages/Sales/Index.jsx`
  - Billingへ統合したため削除。
- `README_UI_Billing.md`, `README_UI_Sales.md`
  - Billing側へ統合仕様を追記し、Salesドキュメントは統合済みである旨を記述。
- `routes/web.php`
  - `/sales` ルートを削除し、Money Forward認証開始ルートのみ残す。
- `resources/js/Layouts/AuthenticatedLayout.jsx`
  - ナビゲーションから「売上管理」を削除。

### 検証
- フロントエンドの自動テストは未実施。`/billing` の再読み込みで同期完了メッセージ表示、請求月フィルタ適用、PDFリンク動作を目視確認予定。

### 次アクション
- ローカルで `/billing` を再表示し、同期ステータス・請求月フィルタ（前月デフォルト）の適用とPDFリンク動作を確認。

### 未解決
- 特になし。
