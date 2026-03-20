# 見積編集画面：Money Forward 連携 UI

対象ファイル: `resources/js/Pages/Estimates/Create.jsx`

## ボタン表示条件
| ボタン | 表示条件 | 説明 |
| --- | --- | --- |
| マネーフォワードで見積書発行 | `isEditMode && is_fully_approved && estimate.client_id && !estimate.mf_quote_id` | 承認済みかつ Money Forward 取引先 (`client_id`) が紐付いているときに表示。クリックで確認ダイアログを開く。 |
| PDFダウンロード | `isEditMode && estimate.mf_quote_id` | Money Forward で発行済みの見積 PDF を OAuth 経由で開く。 |
| 自社請求書に変換 | `isEditMode && is_fully_approved` | ローカル請求書 (`local_invoices`) を作成し、編集画面へ遷移。Money Forward 未連携でも利用可。 |
| 請求書を確認 | `isEditMode && estimate.mf_invoice_pdf_url` | Money Forward 側で請求へ変換済みの場合に表示。MFの PDF へ直接リンク。 |

`is_fully_approved` はサーバ側で算出（全承認済みかどうか）し、ボタン群が表示される前提条件になっています。

## 連携フロー詳細
### 見積書発行
1. 「マネーフォワードで見積書発行」をクリック → shadcn Dialog で確認メッセージを表示。
2. 「発行する」を押下すると `router.visit(route('estimates.createQuote.start', { estimate: estimate.id }))` が実行。
3. サーバ側で有効なアクセストークンが無い場合は `/estimates/auth/start` へ遷移して OAuth を実行。
4. 成功すると `mf_quote_id` / `mf_quote_pdf_url` が保存され、ボタンが「PDFダウンロード」に切り替わる。

### 見積 PDF ダウンロード
- `route('estimates.viewQuote.start')` へ遷移し、サーバが Money Forward API `/quotes/{id}.pdf` を呼び出して PDF をストリームで返却。
- トークンが無効な場合は OAuth → 再取得してから PDF を返す。

### 自社請求書に変換
1. `router.post(route('invoices.fromEstimate', { estimate: estimate.id }))` を実行。
2. `LocalInvoiceController@createFromEstimate` が新規 `local_invoices` レコードを生成し、部門 ID などを可能な限り補完。
3. `/invoices/{id}/edit` へリダイレクトしてローカル請求書を編集できる。

### 請求書を確認
- `estimate.mf_invoice_pdf_url` が存在するとき、Money Forward の請求 PDF への外部リンク（新規タブ）が表示される。
- これは `convertMfQuoteToBilling` or `/invoices/send` フローで保存された値を使用する。

## UI Tips
- ボタン群は `CardFooter` 内の右側に集約し、`space-x-2` で横並びに配置。
- 動作前に承認済みであることをユーザーに分かりやすくするため、申請済みステータスが解除されるとボタンが非表示になる。
- プレビュー機能は廃止済み。Money Forward の PDF が唯一の社外向け出力となる。

## テスト観点
1. 承認ステータスの切り替えに応じてボタン表示が変わるか（`draft` → `pending` → `sent`）。
2. Money Forward 連携前後で `estimate.mf_quote_id` / `mf_invoice_pdf_url` の有無によってボタンが切り替わるか。
3. `client_id` が未設定の場合、見積発行ボタンが表示されないこと。
4. ローカル請求書作成後に `/invoices/{id}/edit` へ遷移すること。
