# Billing Sync & Presentation

## 概要
- Money Forward の請求書（`/api/v3/billings`）をローカル DB (`billings` テーブル) に同期し、ローカル請求 (`local_invoices`) と合わせて一覧表示する。
- Money Forward 側の PDF をキャッシュし、再閲覧時にストレージから配信する。

## 同期の仕組み
1. `/billing` にアクセスすると `MoneyForwardBillingSynchronizer::syncIfStale()` が呼ばれ、一定時間以内に実行済みであればスキップ。
2. 古い場合は `sync()` がトリガされ、`MoneyForwardApiService::getValidAccessToken()` でアクセストークンを取得。
3. `fetchBillings()` が `/api/v3/billings` をページングしながら取得。`order` パラメータは API で拒否されるためローカルでソート。
4. `upsertBilling()` が `billings` テーブルへ upsert。明細は `billing_items`（リレーション）に保存。
5. `downloadPdf()` が `pdf_url` を使って PDF を `storage/app/public/billings/{id}.pdf` に保存。`Route::get('/billing/{billing}/pdf')` から配布可能。
6. 同期結果（件数・時刻）はキャッシュし、UI にステータスバナーとして表示。

### Throttling
- `.env` の `MONEY_FORWARD_BILLING_SYNC_THROTTLE_MINUTES`（デフォルト 10 分）を超えるまでは自動同期をスキップ。
- 同期中はキャッシュキー `mf_billings_sync_lock` で排他制御し、重複実行を防止。

## UI 表示ロジック
- Money Forward から取得した請求とローカル請求をマージし、`source` フラグで区別。
- フィルタフォーム：
  - タイトル（部分一致）
  - 請求月 From/To (`<input type="month">`)
  - 取引先名（部分一致）
  - ステータス（支払状況を `resolvePaymentStatus()` で正規化）
- Money Forward 請求の番号をクリックすると `https://invoice.moneyforward.com/billings/{id}/edit` を別タブで開く。
- ローカル請求は `mf_billing_id` の有無に応じて「MF未生成」や PDF リンクを表示。
- 同期ステータス：
  - `synced`: 緑枠で最終同期日時を表示。
  - `skipped`: グレー枠で前回同期日時を表示。
  - `unauthorized`: トークン失効。`/mf/billings/auth/start` にリダイレクトする。

## コールバック設定
| ルート | 用途 | スコープ |
| --- | --- | --- |
| `GET /mf/billings/auth/start` → Money Forward OAuth | 請求書一覧同期のためのトークン取得 | `mfc/invoice/data.read` |
| `GET /callback` (`route('money-forward.callback')`) | `MONEY_FORWARD_BILLING_REDIRECT_URI` のデフォルト |  |

`MONEY_FORWARD_BILLING_REDIRECT_URI` をカスタムドメインに合わせて設定することで、別ホストでも利用可能。

## ローカル請求との連携
- `LocalInvoiceController` で作成された請求は `source: 'local'` としてマージ表示。
- Money Forward 送信済み (`mf_billing_id` 有り) のローカル請求は ✅ アイコンと MF 編集ページへのリンクを表示。
- ローカル請求の PDF は `/invoices/{invoice}/view-pdf` で取得。アクセストークンが有効であれば即座にストリーム、無効なら OAuth 後のフォールバックでダウンロード。

## エラー対処
| 状況 | 対策 |
| --- | --- |
| 同期時に 401/403 | トークン失効。ダッシュボードから再認証する。 |
| 422 `Partner department not found` | ローカル請求送信前に部門 ID が最新か確認し、ダッシュボードで再同期。 |
| PDF ダウンロードで 404 | Money Forward の PDF が削除されている可能性。MF UI から再生成する。 |

## テストチェックリスト
- `/billing` にアクセス → 未認証の場合は OAuth リダイレクトされるか。
- 同期後に `billings` テーブルへデータが格納され、PDF が `storage/app/public/billings` に保存されるか。
- フィルタ（請求月、ステータス、取引先）が期待どおりに動作するか。
- ローカル請求の MF 送信後、一覧に ✅ アイコンや MF へのリンクが表示されるか。
