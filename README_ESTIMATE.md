# Estimates Module Specification

## Purpose
- 社内で作成した見積の承認ワークフローを管理し、Money Forward クラウド請求書と双方向に連携する。
- 見積→請求への変換や PDF 取得など、外部サービス操作をワンクリックで実行できる UI を提供する。

## Data Model (table: `estimates`)
| Column | Description |
| --- | --- |
| `estimate_number` | 可読な連番。`Estimate::generateReadableEstimateNumber()` で `EST(-D)-{staff}-{client}-{yymmdd}-{seq}` を採番。編集不可。 |
| `status` | `draft` / `pending` / `sent` を使用（UI では ドラフト / 承認待ち / 承認済み）。 |
| `items` | JSON カラム。各行に `name`, `qty`, `unit`, `price`, `cost`, `tax_category`, `description`, `delivery_date` などを保持。 |
| `approval_flow` | JSON 配列。`[{ id, name, status, approved_at }]` の形で承認ステップを記録。 |
| `client_id`, `mf_department_id` | Money Forward の取引先 / 部門 ID。ダッシュボード同期で補完される。 |
| `mf_quote_id`, `mf_quote_pdf_url`, `mf_invoice_id` | Money Forward 側で生成された ID / PDF URL を保存。 |

## Status & Workflow
1. **Draft** (`draft`): 新規作成直後または申請取消後。編集・削除が可能。
2. **Pending** (`pending`): 承認申請後。承認フローの先頭に未承認者を設定し、「申請取消」が可能。
3. **Sent** (`sent`): 全ての承認者が承認した状態。Money Forward 連携ボタンが有効化される（ステータスに依存する UI は `Create.jsx` に実装）。

承認フロー編集はモーダルダイアログで行い、並べ替え・追加・削除を含めてローカルステートを更新。承認操作は `EstimateController@approve`（`PUT /estimates/{estimate}/approval`）で反映される。

## UI Flows
### List (`/quotes`)
- `MoneyForwardQuoteSynchronizer::syncIfStale()` がページ表示ごとに Money Forward API `/quotes` を呼び出し、ローカル DB を更新。
- フロントエンドは shadcn/UI コンポーネントを利用し、タイトル・発行月・取引先・ステータスでフィルタリング。
- 行のアクションから編集、複製、プレビュー、削除、承認ダイアログなどに遷移。

### Create/Edit (`/estimates/create`, `/estimates/{id}/edit`)
- `resources/js/Pages/Estimates/Create.jsx` が新規／編集の両方を担当。
- 顧客コンボボックスは `/api/customers`、担当者は `/api/users` を非同期取得。
- 部門コンボボックスは `/api/partners/{partner}/departments` を呼び出し、部門が1件のみの場合は自動選択。
- 行明細は Recharts を用いた社内ビュー（粗利率など）と社外ビューを切り替え可能。
- 承認申請で `approval_flow` を送信、申請直後はローカルフラグで UI を即時切り替え (`approvalLocal` state)。

## Money Forward Actions
| ボタン | ルート | コントローラ | 備考 |
| --- | --- | --- | --- |
| マネーフォワードで見積書発行 | `GET /estimates/{estimate}/create-quote` | `EstimateController@createMfQuote` | アクセストークンが無い場合は `/estimates/auth/start` にフォールバック。 |
| MF見積PDFを表示 | `GET /estimates/{estimate}/view-quote` | `EstimateController@viewMfQuotePdf` | 有効なトークンが無い場合は OAuth → PDF ストリーミング。 |
| 請求へ変換 | `GET /estimates/{estimate}/convert-to-billing` | `EstimateController@convertMfQuoteToBilling` | Money Forward API `/quotes/{id}/convert_to_billing` を呼び出す。 |

### Quote Synchronizer
- `MoneyForwardQuoteSynchronizer` は `/quotes` 表示時に OAuth トークンをチェックし、無効なら `/quotes/mf/auth/start` へ遷移。
- 同期はページングしつつ、`estimate_number` が一致するレコードを更新。それ以外は `mf_quote_id` ベースで紐付け。
- `mf_quote_pdf_url` や `partner_name` などの情報をローカル `estimates` に反映。

## Validation & Edge Cases
- `due_date` が `issue_date` 以前の場合は UI 側で自動的に 30 日先へ補正。
- Money Forward へ送信する際、`department_id` と `partner_id` は必須。欠けている場合はエラーメッセージを表示し、ダッシュボードから再同期する。
- `quote_number` は Money Forward 側の制約に合わせ 30 文字以内にトリム（`MoneyForwardApiService::createQuoteFromEstimate`）。
- PDF 取得時にトークンが失効している場合は自動で OAuth を再実行。

## Recommended Test Scenarios
1. 新規作成 → 承認申請 → 申請取消 → 再申請 → 承認完了。
2. Money Forward へ見積書を発行し、`mf_quote_id` が保存されることを確認。
3. Money Forward 側で部門が削除された場合に同期がエラーになることを確認し、部門再同期で復旧できること。
4. 見積一覧でフィルタと一括承認の UI が期待通り動作すること。
