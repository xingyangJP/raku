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