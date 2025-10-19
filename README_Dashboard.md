# Dashboard Screen Spec

## Purpose
- 承認待ち見積のタスクを素早く確認し、必要に応じて詳細を開いて承認できるようにする。
- Money Forward とローカル DB の同期状況を自動把握し、パートナー情報の差分同期を行う。

## Visible Sections
- **Summary Cards**: 当月の見積サマリ・粗利サマリ（請求書へ変換済みの見積ベース）・売上サマリ（請求書ベース）を表示。各カードには先月分の比較値をサブカードとして表示する。
- **やることリスト**: `Estimate` の `approval_flow` を元に、未承認の見積を申請日降順で表示。
  - ログインユーザーが現行承認者なら「確認して承認」ボタンが出現し、`EstimateDetailSheet` を開いて承認可能。
  - 他者が現行承認者の場合は「{担当者名}さんの承認待ち」バッジを表示。
- **売上ランキング**: 当月のローカル請求書を集計し、得意先別売上トップ5を表示。
- **取引先自動同期**: 画面表示時に Money Forward の取引先 API を呼び出し、最新情報をローカル `partners` テーブルに反映。未認証の場合は OAuth に遷移し、復帰後に同期を継続する。
- **取引先取得ボタン**: 手動で再同期したい場合のトリガ。成功／失敗はダッシュボード内の専用メッセージ領域に表示される。

## Money Forward Partner Sync Flow
1. ダッシュボード表示時、`DashboardController@index` が自動的に `attemptAutoPartnerSync` を呼び出す。
2. 有効なアクセストークンがある場合は即座に `performPartnerSync` を実行。結果はセッションに保存し、ダッシュボード内メッセージとして表示。
3. トークンが無い／スコープ不足の場合は `/mf/partners/auth/start` に遷移し、OAuth 認可画面へ。復帰後に同期が再開される。
4. 「取引先取得」ボタンを押下した場合は従来通り `DashboardController@syncPartners` → `doPartnerSync` が起動し、手動再実行が可能。
5. 取得結果を `partners` テーブルへ upsert。`payload` に Money Forward 側の departments/offices 情報を丸ごと保持し、UI での部門選択に使用。
6. 成功／失敗メッセージは通常のフラッシュではなく、ダッシュボード専用のセッションキーで保持し画面内に表示する。これにより他画面への遷移後にメッセージが残留しない。

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
- **エラーハンドリング**: 同期結果は `partnerSyncFlash` プロップとして渡し、ダッシュボード内のメッセージ領域で表示。OAuth 失敗時は `error` を通知し、成功時は件数付きメッセージ。
- **Data Fetching**: ダッシュボードはサーバサイドで `Estimate` をロードし、`approval_flow` の JSON を解析して現在の承認ステップを判定。

## Troubleshooting Checklist
- Money Forward 側で部門を持たない partner は UI で部門選択が空になるため、MF ポータルから部門を追加後に再同期する。
- 403/422 が発生する場合は scope が不足しているか、コールバック URI が未登録の可能性あり。
- 長時間アクセスがない場合はアクセストークンが失効する。再度ダッシュボードでボタンを押下し、OAuth を再実行する。
