# Dashboard Screen Spec

## Purpose
- 承認待ち見積のタスクを素早く確認し、必要に応じて詳細を開いて承認できるようにする。
- Money Forward とローカル DB の同期状況を把握し、パートナー情報の同期をトリガできるようにする。

## Visible Sections
- **Summary Cards**: 売上・仕入などの KPI カード（現状はダミー値を表示）。今後のメトリクス拡張を想定。
- **やることリスト**: `Estimate` の `approval_flow` を元に、未承認の見積を申請日降順で表示。
  - ログインユーザーが現行承認者なら「確認して承認」ボタンが出現し、`EstimateDetailSheet` を開いて承認可能。
  - 他者が現行承認者の場合は「{担当者名}さんの承認待ち」バッジを表示。
- **取引先取得ボタン**: Money Forward API から partners + departments を同期するトリガ。

## Money Forward Partner Sync Flow
1. ユーザーが「取引先取得」ボタンを押下すると `DashboardController@syncPartners` が起動。
2. 有効なアクセストークンがない場合は `/mf/partners/auth/start` に遷移し、OAuth 認可画面へ。
3. コールバック `GET /mf/partners/auth/callback` でアクセストークンを保存し、`MoneyForwardApiService::fetchAllPartners` と `fetchPartnerDetail` を呼び出す。
4. 取得結果を `partners` テーブルへ upsert。`payload` に Money Forward 側の departments/offices 情報を丸ごと保持し、UI での部門選択に使用。
5. 成功メッセージと同期件数をフラッシュメッセージで表示。

### OAuth Settings
| 項目 | 値 |
| --- | --- |
| Start Route | `route('partners.auth.start')` (`/mf/partners/auth/start`) |
| Callback Route | `route('partners.auth.callback')` (`/mf/partners/auth/callback`) |
| Default Scope | `mfc/invoice/data.read mfc/invoice/data.write` |
| ENV Override | `MONEY_FORWARD_PARTNER_AUTH_REDIRECT_URI`（未設定時は上記ルートが使用される） |

## UI Behaviour Details
- **フィルタトグル**: やることリストには `ToggleGroup` を使用し「全て」「自分のみ」を切り替え。
- **詳細表示**: `EstimateDetailSheet` コンポーネントをダッシュボードでも再利用。承認ボタンは現行承認者のみ表示。
- **エラーハンドリング**: OAuth 失敗や API エラー時はフラッシュメッセージに `error` をセット。成功時は `success`。
- **Data Fetching**: ダッシュボードはサーバサイドで `Estimate` をロードし、`approval_flow` の JSON を解析して現在の承認ステップを判定。

## Troubleshooting Checklist
- Money Forward 側で部門を持たない partner は UI で部門選択が空になるため、MF ポータルから部門を追加後に再同期する。
- 403/422 が発生する場合は scope が不足しているか、コールバック URI が未登録の可能性あり。
- 長時間アクセスがない場合はアクセストークンが失効する。再度ダッシュボードでボタンを押下し、OAuth を再実行する。
