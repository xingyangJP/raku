📄 Money Forward Integration – Estimates & Billing

## Required Environment
| Key | Purpose |
| --- | --- |
| `MONEY_FORWARD_CLIENT_ID` / `MONEY_FORWARD_CLIENT_SECRET` | Money Forward アプリの必須クレデンシャル。 |
| `MONEY_FORWARD_QUOTE_SCOPE` | 見積作成・請求変換・ローカル請求送信時のスコープ。既定値は `mfc/invoice/data.write`。 |
| `MONEY_FORWARD_QUOTE_REDIRECT_URI` | 見積一覧同期のコールバック。未設定時は `route('quotes.auth.callback')` を使用。 |
| `MONEY_FORWARD_INVOICE_REDIRECT_URI` | ローカル請求送信／PDF フォールバックのコールバック。未設定時は `route('invoices.send.callback')`。 |
| `MONEY_FORWARD_BILLING_REDIRECT_URI` | 請求書一覧同期のコールバック。未設定時は `route('money-forward.callback')`。 |
| `MONEY_FORWARD_PARTNER_AUTH_REDIRECT_URI` | 取引先同期のコールバック。 |

> 旧 `MONEY_FORWARD_ESTIMATE_REDIRECT_URI` などは現行コードでは参照していません。フローは `estimates/auth/callback` に統一されています。

## Redirect URIs to Register
```
http://localhost:8000/estimates/auth/callback
http://localhost:8000/quotes/mf/auth/callback
http://localhost:8000/invoices/send/callback
http://localhost:8000/invoices/view-pdf/callback
http://localhost:8000/callback
```
（ローカル開発の例。環境に合わせてホスト名やパスを調整してください。）

## Flows
### 1. 見積 → Money Forward 見積書の作成
1. `/estimates/{estimate}/create-quote` を開くとアクセストークンを確認。
2. 無い場合は `/estimates/auth/start` へ遷移し、OAuth 認可 (`scope = mfc/invoice/data.write`) を実行。
3. コールバック `GET /estimates/auth/callback` でトークンを保存し、`MoneyForwardApiService::createQuoteFromEstimate()` を呼び出す。
4. `/api/v3/quotes` に POST。成功すると `mf_quote_id` と `mf_quote_pdf_url` を保存。

### 2. 見積 → Money Forward 請求へ変換
1. `mf_quote_id` がある見積で `/estimates/{estimate}/convert-to-billing` を実行。
2. トークンがなければ再度 OAuth（`scope = mfc/invoice/data.write`）。  
3. `MoneyForwardApiService::convertQuoteToBilling()` が `/api/v3/quotes/{id}/convert_to_billing` を叩き、`mf_invoice_id` を保存。

### 3. Money Forward 見積 PDF 表示
1. `/estimates/{estimate}/view-quote` でトークンをチェック。存在すれば即時 PDF をストリーム返却。
2. 無い場合は OAuth（`scope = mfc/invoice/data.read`）→ コールバック後に PDF をダウンロードしてレスポンス。

### 4. ローカル請求 → Money Forward 請求書作成
1. `/invoices/{invoice}/send` でトークンを確認し、無ければ OAuth（`scope = mfc/invoice/data.write`）。`state` をランダム生成して CSRF 防止。
2. `MoneyForwardApiService::createInvoiceFromLocal()` が `/api/v3/invoice_template_billings` に POST。
3. 成功時は `mf_billing_id`, `mf_pdf_url` を保存し、連携完了メッセージを表示。

### 5. Money Forward 請求 PDF 表示（フォールバック）
1. `/invoices/{invoice}/view-pdf` で有効なトークンがあればそのまま PDF をストリーム。
2. 無い場合は OAuth（`scope = mfc/invoice/data.read`）。`state` に base64 エンコードした `{'k':'pdf','i':invoice_id}` を保持。
3. コールバック `GET /invoices/view-pdf/callback` で `state` を検証し、PDF をダウンロードして返却。

## API Payload Notes
- **Quote** (`POST /api/v3/quotes`)
  - `items[]` は `name`, `price`, `quantity`, `unit`, `detail`, `excise` を含む。
  - `quote_number` は 30 文字以内にトリム（`MoneyForwardApiService` 内で処理済み）。
  - `quote_date` / `expired_date` は `Y-m-d` 形式。期限が発行日以前の場合は 1 ヶ月後に補正。
- **Convert to Billing** (`POST /api/v3/quotes/{id}/convert_to_billing`)
  - レスポンスの `id` が Money Forward 上の請求 ID。`pdf_url` が含まれる場合は `mf_invoice_pdf_url` に保存（カラムが存在する場合）。
- **Invoice Template Billing** (`POST /api/v3/invoice_template_billings`)
  - `items[]` は `name`, `detail`, `unit`, `price`, `quantity`, `excise` を送信。Money Forward 側の品目 ID は紐付けず、ローカル行をそのまま送る。
  - `department_id` が未設定の場合は送信前にパートナー再同期で補完する必要がある。

## Error Handling & Troubleshooting
| 症状 | 想定原因 | 対処 |
| --- | --- | --- |
| `invalid_grant` / redirect mismatch | Money Forward アプリにコールバック URI が未登録 | アプリポータルで URI を追加し、完全一致させる。 |
| `Validation failed: Partner department not found` | `department_id` が古い | ダッシュボードの「取引先取得」を再実行し、見積／請求の部門を再選択。 |
| 401 / `invalid_token` | トークン失効 | ボタン押下時に自動で OAuth を再実行。ログアウト → 再ログインも検討。 |
| PDF 取得で 404 | Money Forward 上で PDF が削除・未生成 | Money Forward UI で状態を確認し、必要なら再作成。 |

## Testing Tips
- テスト用見積を作成し、承認 → 見積発行 → 請求変換まで一連のボタン操作を手動確認。
- `.env` の `MONEY_FORWARD_QUOTE_SCOPE` を `data.write` に設定し忘れると 403 になるため注意。
- 連携失敗時は `storage/logs/laravel.log` で `payload` とレスポンス本文を確認。`MoneyForwardApiService` は `error_message` とステータスをログ出力する。
