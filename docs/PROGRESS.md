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
