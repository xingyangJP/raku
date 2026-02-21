# PROGRESS

## 2026-02-16

### Step 1: 要件確定
- 予算 = 見積、実績 = 注文確定。
- 売上/粗利/仕入の発生月は納期ベース（納期未設定時は期限日、さらに未設定時は見積日で補完）。
- 追加要件として工数キャパ、工数充足率、生産性（粗利/工数）を追加する方針を確定。

### Step 2: 影響範囲確認
- 変更対象:
  - `app/Http/Controllers/DashboardController.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `config/app.php`
  - `.env.example`
  - `README_Dashboard.md`
- 画面対象: `/dashboard`
- 機能対象: 経営者向け予実管理、工数管理、売上ランキング集計基準。

### Step 3: バックエンド集計改修
- `DashboardController` を納期ベース予実集計に変更。
- 月次データ（当月〜11か月先）で売上・粗利・仕入・工数を予算/実績で返却。
- 工数集計で `first_business` を除外する判定を追加。
- 当月/先月の比較値に加えて、工数キャパ・充足率・空き工数・生産性を返却。
- 売上ランキングを請求書ベースから「注文確定見積の納期ベース」へ変更。

### Step 4: フロント改修
- `Dashboard.jsx` を経営者向けレイアウトへ更新。
- KPIカードを「売上/粗利/仕入」の予算・実績・差異表示へ変更。
- 工数KPI（稼働率、空き工数、生産性、件数）を追加。
- 月次予実テーブルを追加し、売上/粗利/仕入/工数の差異を表示。
- 集計基準カードに「予算・実績・発生月の定義」を明示。

### Step 5: 設定・ドキュメント更新
- `config/app.php`:
  - `version` fallback を `v1.0.1` へ更新。
  - `monthly_capacity_person_days` を追加。
- `.env.example`:
  - `APP_VERSION`
  - `APP_MONTHLY_CAPACITY_PERSON_DAYS`
- `README_Dashboard.md` を新仕様へ全面更新。

### Step 6: ブランチ運用修正
- 指示に従い `dev` ブランチへ切替えて作業継続。

### Step 7: 日報実績工数・キャッシュフロー強化
- 日報API（`/api/daily-reports`）から実績工数を取得し、月次の実績工数へ反映。
- プロジェクト別実績工数トップ5と紐付率（未紐付工数含む）を追加。
- キャッシュフロー指標を追加（支払予定=仕入、回収予定、回収実績、ネットCF）。
- 日報トークン未設定時は実績工数を見積工数でフォールバックする仕様を追加。

### Step 8: 見積と日報の厳密紐付け
- `estimates` に `xero_project_id` / `xero_project_name` を追加するマイグレーションを作成。
- 見積作成/更新/下書き保存でプロジェクトID/名称を保存可能に変更。
- 見積画面に XERO PM プロジェクト検索コンボボックスを追加（`/api/projects`）。
- 日報実績工数集計を `xero_project_id` 優先で突合するロジックへ変更し、未紐付工数を分離表示。

### Step 9: 既存見積の xero_project_id 一括補完バッチ
- Artisan コマンド `estimates:backfill-project-id` を追加。
- 対象: `xero_project_id` 未設定の見積（デフォルトで受注確定済みのみ）。
- 突合ルール:
  - `xero_project_name` 完全一致（顧客一致を優先）
  - 見積件名（`title`）完全一致 + 顧客一致
  - 納期がプロジェクト期間内の場合は加点
- 出力: `AUTO_LINKED / REVIEW_REQUIRED / UNMATCHED` のCSVを `storage/app/reports/` へ保存。
- `--apply` 指定時のみDB更新、未指定はDRY-RUN。

### Step 10: 受注確定時のプロジェクト紐付け必須化
- 見積作成時点では `xero_project_id` を任意のまま維持。
- 受注確定時のみ `xero_project_id` を必須チェック。
  - `EstimateController@updateOrderConfirmation`
  - `EstimateController@store`（`is_order_confirmed=true`）
  - `EstimateController@update`（`is_order_confirmed=true`）
- 仕様上の運用制約（受注後にプロジェクト作成）に合わせた必須化ポイントへ変更。

### Step 11: 工数・生産性の単位整合修正
- `DashboardController` の生産性計算を `%計算` から `粗利 / 人日` に修正。
- 見積工数の人日換算を追加。
  - `人月` → `20人日`
  - `人時/時間` → `8時間=1人日`
- 日報工数（時間）を人日換算して月次実績へ反映。
- フロント表示キーを `matched_person_days / unmatched_person_days / person_days` に更新。
- 設定追加:
  - `APP_PERSON_DAYS_PER_PERSON_MONTH`
  - `APP_PERSON_HOURS_PER_PERSON_DAY`

### Step 12: セクション分割UI設定書の作成
- 要望に基づき、ダッシュボードを `総合 / 開発 / 仕入れ販売 / 保守` の4セクションで再設計。
- 開発・保守セクションに工数予実（予算/実績/稼働率/空き工数/生産性）を明記。
- UI設定書を新規作成:
  - `docs/DASHBOARD_UI_SETTING.md`

### Step 13: 日報未連携前提への設計変更
- 工数関連指標を「実績」から「計画工数（見積ベース）」へ変更。
- ダッシュボードから日報実績工数カードを外し、仕入内訳カード（物品/工数原価）へ置換。
- 仕入計算を `物品仕入 + 工数原価` へ変更。
  - 明細原価あり: 明細原価を使用
  - 明細原価なし: `APP_LABOR_COST_PER_PERSON_DAY` で補完
- バージョンを `v1.0.4` に更新。

### Step 14: 保守売上管理「当月を再同期」追加
- `MaintenanceFeeController` に当月強制再同期アクションを追加。
  - 既存当月スナップショットがあっても `customers` API から明細を再取得して置換。
- ルート追加:
  - `POST /maintenance-fees/resync-current` (`maintenance-fees.resyncCurrent`)
- `MaintenanceFees/Index.jsx` に「当月を再同期」ボタンを追加し、成功メッセージを表示。
- バージョンを `v1.0.5` に更新。

### Step 15: 見積のプロジェクト選択UI撤去
- 見積作成/編集画面から XERO PM プロジェクト選択コンポーネントを削除。
- 見積フォーム送信から `xero_project_id` / `xero_project_name` の入力連携を削除。
- 受注確定時の `xero_project_id` 必須バリデーションを削除。
- 未使用となった `GET /api/projects` と `ApiController@getProjects` を削除。
- バージョンを `v1.0.6` に更新。

### Step 16: 保守売上管理の運用説明追記
- `MAINTENANCE_FEE.md` に運用説明セクションを追加。
- 指定キーワードを反映:
  - `変更前`
  - `変更後`
  - `変更忘れの場合は`
  - `編集モードで`

### Step 17: サイドメニューの請求・売掛管理を非表示化
- `AuthenticatedLayout` のサイドメニュー定義から「請求・売掛管理」を削除。
- ルート自体（`/billing`）は残し、直接URLアクセスは維持。
- バージョンを `v1.0.7` に更新。

### Step 18: 注文書一覧画面の新規追加（検索UX強化）
- 新規画面 `Orders/Index` を追加し、受注確定済み見積を注文書一覧として表示。
- 新規ルートを追加:
  - `GET /orders` (`orders.index`)
- サイドメニューに「注文書一覧」を追加。
- 検索UXを強化:
  - キーワード（見積番号/件名/顧客）・顧客・担当・納期月範囲・ソート
  - Enterキー検索
  - クイック絞り込み（今月/直近3ヶ月）
  - アクティブフィルタチップ（ワンクリック解除）
- 集計カードを追加:
  - 受注件数、受注総額、粗利総額、平均受注単価、当月納期件数/金額、計画工数（人日）
- 注文書データは `EstimateController@ordersIndex` で納期ベース（`delivery_date` 優先、未設定時 `due_date`/`issue_date`）で集計。
- バージョンを `v1.0.8` に更新。
- 動作確認:
  - `php -l app/Http/Controllers/EstimateController.php`
  - `php -l routes/web.php`
  - `npm run build`

### Step 19: 注文書一覧へ資金繰りダッシュボード追加（ハード/人件費分離）
- 注文書一覧に資金繰りセクションを追加し、次の2系統で分離表示:
  - ハードウェア（変動仕入）
  - 人件費（固定費）
- バックエンド集計を拡張:
  - 明細単位で `工数系（人日/人月/人時/時間/h/hr）` と `非工数系` を判定。
  - ハードウェア: 仕入支出（受注月想定）/回収入金（納期翌月想定）/ネット/納期月売上/納期月粗利を月次化。
  - 人件費: 固定人件費（月額=`APP_MONTHLY_CAPACITY_PERSON_DAYS * APP_LABOR_COST_PER_PERSON_DAY`）を基準に、回収入金・ネット・計画工数・稼働率を月次化。
- フロントを拡張:
  - 各セクションにKPIカードと月次テーブルを追加。
  - 集計前提（試算ロジック）を画面上に明示。
- バージョンを `v1.0.9` に更新。
