# Dashboard Screen Spec

## Purpose
- 経営者向けに、予算（見積）と実績（注文確定）を納期ベースで比較し、売上・粗利・仕入・工数・生産性を月次で把握する。
- 既存の承認タスク導線と Money Forward 取引先同期導線は維持する。

## Accounting Basis
- 予算: `estimates`（`mf_deleted_at` が null、`status != rejected`）。
- 実績: `estimates.is_order_confirmed = true`。
- 発生月: `delivery_date` を優先。未設定時は `due_date`、さらに未設定時は `issue_date` を使用。
- 粗利: `total_amount - items(cost * qty)`。
- 仕入: `items(cost * qty)`。
- 工数: `items.qty` を人日換算して集計（`人日`=そのまま、`人月`=20人日換算、`人時/時間`=8時間=1人日換算）。
- 工数表示: 日報未連携前提のため、計画工数（見積ベース）のみ表示。
- 仕入: 物品仕入 + 工数原価で算出（工数原価は明細原価を優先、未設定時は人日単価設定で補完）。
- キャッシュフロー:
  - 支払予定（仕入）: 見積日（`issue_date`）月に計上（未設定時は期限日/納期で補完）。
  - 回収予定: 期限日（`due_date`）月に計上（未設定時は納期翌月）。
  - 回収実績: Money Forward 請求（`billings.payment_status` が入金済系）の期限日月で計上。

## Visible Sections
- **集計基準カード**: 予算/実績/発生月の基準を明示。
- **KPIカード（当月）**:
  - 売上（予算・実績・差異）
  - 粗利（予算・実績・差異）
  - 仕入（予算・実績・差異）
- **工数KPI（当月）**:
  - 計画工数稼働率
  - 空き工数（計画）
  - 計画生産性（粗利/人日）
  - 案件件数（予算・実績）
- **仕入内訳カード**: 物品仕入と工数原価の内訳を表示。
- **月次予実テーブル**: 12か月（当月〜11か月先）の売上/粗利/仕入/工数を予算実績で比較。
- **月次キャッシュフロー**: 支払予定・回収予定・回収実績・ネットCFを月次で表示。
- **売上ランキング**: 当月の注文確定案件を得意先別に集計（納期ベース）したトップ5。
- **やることリスト**: 承認フローに基づくタスク。
- **取引先取得**: Money Forward 取引先の手動再同期。

## API and Controller Notes
- `DashboardController@buildDashboardMetrics`
  - 月次配列を生成し、予算/実績を同時集計。
  - 工数集計のために `products` を参照し、`first_business` を除外（計画工数）。
  - 仕入を「物品仕入」「工数原価」に分解集計。
  - 返却プロップ:
    - `basis`
    - `capacity`
    - `budget.current|previous`
    - `actual.current|previous`
    - `effort.current|previous`
    - `forecast.months`
- `DashboardController@buildSalesRanking`
  - `estimates` を納期ベースで集計し、`is_order_confirmed = true` を対象にランキング作成。

## Environment Variables
| Key | Purpose | Default |
| --- | --- | --- |
| `APP_MONTHLY_CAPACITY_PERSON_DAYS` | 当月の工数キャパ（人日） | `160` |
| `APP_PERSON_DAYS_PER_PERSON_MONTH` | 人月→人日の換算係数 | `20` |
| `APP_PERSON_HOURS_PER_PERSON_DAY` | 時間→人日の換算係数 | `8` |
| `APP_LABOR_COST_PER_PERSON_DAY` | 工数原価（人日単価、明細原価未設定時の補完値） | `0` |
| `APP_VERSION` | 画面表示用バージョン（fallback） | `v1.0.7` |
| `XERO_PM_API_BASE` | 日報APIのベースURL | `https://api.xerographix.co.jp/api` |
| `XERO_PM_API_TOKEN` | 日報APIのBearerトークン | empty |

## Estimate Linkage
- 見積画面での XERO PM プロジェクト選択UIは廃止。
- 受注確定時の `xero_project_id` 必須チェックも廃止。
- 理由: 現運用ではプロジェクトAPI連携が安定しておらず、見積入力フローを阻害するため。
- 既存見積の補完には `php artisan estimates:backfill-project-id` を使用する（`--apply` なしはDRY-RUN）。

## Existing Partner Sync Flow
1. ダッシュボード表示時、`attemptAutoPartnerSync` を実行。
2. トークン有効時は即同期、無効時は OAuth へリダイレクト。
3. 取得結果を `partners` テーブルに upsert。
4. 結果は `partnerSyncFlash` でダッシュボードに表示。

## Troubleshooting Checklist
- 工数が0になる場合:
  - 明細 `unit` が人日/人月以外で、かつ商品マスタ紐付けがない可能性。
  - `products.business_division = first_business` の明細は工数対象外。
- 売上ランキングが空の場合:
  - 当月に `is_order_confirmed = true` かつ納期が当月の案件が存在しない。
- 取引先同期エラー時:
  - `mfc/invoice/data.read mfc/invoice/data.write` スコープを確認。
