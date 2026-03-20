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
- `docs/MAINTENANCE_FEE.md` に運用説明セクションを追加。
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

### Step 20: 資金繰り前提の再構築（キャパ80人日・標準人件費22,000円）
- 固定費計算のデフォルト設定を変更:
  - `APP_MONTHLY_CAPACITY_PERSON_DAYS=80`
  - `APP_LABOR_COST_PER_PERSON_DAY=22000`
- 注文書一覧の資金繰り表示文言を改善:
  - `仕入支出` → `注文月支出`
  - `ネット` → `資金収支（入金-支出）` / `資金収支（回収-固定費）`
- 集計前提文言を明確化:
  - 変動仕入は注文日（`issue_date`）月に支出
  - 回収は納期翌月入金で試算
- バージョンを `v1.0.10` に更新。

### Step 21: ダッシュボード工数の納期ルール修正 + UX改善
- 仕様変更:
  - 納期未設定の見積工数は「月次計画工数」に含めない。
  - 納期設定済みの工数のみ、納期月に計画工数として配賦。
- `DashboardController` を改修:
  - `budget_effort` 加算を納期月（`delivery_date`）へ限定。
  - 納期未設定工数を `effort.summary.unscheduled_total` として分離集計。
  - 集計基準に `effort_rule` を追加してルールを明示。
- `Dashboard.jsx` を改修:
  - 集計基準カードに工数ルール説明を追加。
  - 工数カードに `未配賦（納期未設定）` を追加。
  - 月次予実一覧の説明文を工数配賦ルールに合わせて更新。
- バージョンを `v1.0.11` に更新。

### Step 22: Markdownドキュメント再編（README以外をdocsへ集約）
- ルート直下の `README*` 以外の Markdown を `docs/` へ移設。
  - `AI_ESTIMATE.md` → `docs/AI_ESTIMATE.md`
  - `BACKLOG_HOWTO.md` → `docs/BACKLOG_HOWTO.md`
  - `LP.md` → `docs/LP.md`
  - `MAINTENANCE_FEE.md` → `docs/MAINTENANCE_FEE.md`
  - `NEW_FUNCTION.md` → `docs/NEW_FUNCTION.md`
  - `RDD.md` → `docs/RDD.md`
  - `SEEDER.md` → `docs/SEEDER.md`
  - `TEST_LOG.md` → `docs/TEST_LOG.md`
  - `progress.md` → `docs/PROGRESS_LEGACY.md`
- 重複整理:
  - 進捗の正本を `docs/PROGRESS.md` に統一。
  - 旧履歴は `docs/PROGRESS_LEGACY.md` としてアーカイブ。
- `docs/INDEX.md` を新設し、用途別に文書を整理。
- 参照更新:
  - `README.md` にドキュメント索引リンクを追加。
  - `docs/reference/README_Debug.md` の進捗記録先を `docs/PROGRESS.md` に変更。
  - `RequirementChatController` のプロンプト内参照を `docs/AI_ESTIMATE.md` へ更新。
  - `docs/LP.md` / `docs/PROGRESS*.md` の旧パス参照を更新。

### Step 23: ドキュメント配置ポリシー修正（README.mdのみルート残置）
- ルールを `README.md` のみルート残置に再設定。
- `README_*` を含む残存Markdownをすべて `docs/` 配下へ移動:
  - `README_*.md` → `docs/reference/README_*.md`
  - `database/seeders/README_SEERDER.md` → `docs/reference/README_SEERDER.md`
  - `test-results/pdf-display-PDF-display-test/error-context.md` → `docs/test-results/pdf-display-PDF-display-test/error-context.md`
- `docs/INDEX.md` の記載を新ポリシーに合わせて更新。
- `README.md` のドキュメント導線を `docs/reference/` へ更新。

## 2026-03-20

### Step 24: ダッシュボード現状調査と再設計提案
- 現行コードを調査し、`/dashboard` と `/orders` に予実・資金繰り・工数ロジックが分散していることを確認。
- `予算=見積 / 実績=注文書` の概念自体は実装済みだが、UI・グラフ・AI分析・部門別導線が要件未達であることを整理。
- 工数前提のズレを確認:
  - 現在の月間キャパ既定値は `80人日`
  - 要件前提は `10人 x 20人日 = 200人日`
- 日報API集計の下地コードはあるが、現ダッシュボードでは未使用であることを確認。
- 新規提案書 `docs/DASHBOARD_RENEWAL_PROPOSAL.md` を作成し、以下を整理:
  - 現状の問題点
  - 要件との差分
  - 新ダッシュボードの情報設計
  - 工数/資金繰り/AI分析の強化案
  - 共通集計サービス化の提案
  - 段階的実装フェーズ

### Step 25: 工数基準人数を設定画面から変更可能に
- `company_settings` に `operational_staff_count` を追加するマイグレーションを作成。
- `CompanySetting` モデルを追加し、人数設定から月間キャパ人日を算出する共通ロジックを実装。
- `/admin` を実運用の設定画面に変更し、稼働人数を保存できるようにした。
- サイドメニューに「設定」を追加。
- ダッシュボードと注文書一覧の工数キャパ計算を、`.env` 固定値ではなく `company_settings.operational_staff_count` ベースに変更。

### Step 26: 個人別空き状況に必要な前提を提案書へ追記
- 現状の見積データだけでは「誰が空いているか」は出せないことを明記。
- 個人別可視化には、見積明細ごとの複数担当者アサインが必要であることを整理。
- 工数ロジックは `人数倍` ではなく `按分` が正しいことを提案書へ追記。
- 例:
  - `1人月` を3人に割り当てた場合は `3倍` ではなく `3人で分配`
- 将来設計として、担当者アサインは見積ヘッダ単位ではなく明細単位で持つ方針を追記。

### Step 27: ダッシュボード新UIの初版実装
- `ManagementMetricsService` を追加し、ダッシュボード指標を共通サービスで集計する構造へ変更。
- 集計を `総合 / 開発 / 仕入れ販売 / 保守` の4セクションで返すように改修。
  - 開発/販売: 見積明細の `business_division` ベース
  - 保守: `maintenance_fee_snapshots` ベース
- `DashboardController` は新サービス経由で指標を取得するように変更。
- `Dashboard.jsx` を刷新し、以下を実装:
  - 4タブ切替
  - 売上/粗利/仕入/工数のサマリーカード
  - 売上・粗利推移グラフ
  - 資金繰り推移グラフ
  - 工数推移グラフ
  - ルールベースの経営分析コメント
  - 月次予実テーブル
- 確認:
  - `php -l app/Services/ManagementMetricsService.php`
  - `php -l app/Http/Controllers/DashboardController.php`
  - `php -l app/Http/Controllers/AdminController.php`
  - `php -l routes/web.php`
  - `npm run build`
- 補足:
  - Playwright によるブラウザ確認は、既存 Chrome セッション競合により未実施。

### Step 28: セクション別ランキングとアラート追加
- `ManagementMetricsService` にセクション別ランキング集計を追加。
  - 上位顧客
  - 上位担当者（保守はサポート種別）
- ダッシュボードへセクション別アラートを追加。
  - 売上予算未達
  - 粗利悪化
  - 工数過負荷 / 工数余力あり
  - ネットCFマイナス
- `Dashboard.jsx` にランキングテーブルとアラート表示を組み込み、各タブで判断材料を増やした。
- 確認:
  - `php -l app/Services/ManagementMetricsService.php`
  - `npm run build`

### Step 29: ダッシュボード確認用の2025年・2026年ダミーデータを追加
- `DashboardDemoSeeder` を追加し、2025年1月〜2026年12月の確認用ダミーデータを毎月投入できるようにした。
- ダミーデータの対象:
  - 開発案件（受注済み / 見込）
  - 仕入れ販売案件（受注済み / 見込）
  - 保守スナップショット
- `company_settings.operational_staff_count` もシーダー内で `10人` に補正し、月間キャパを `200人日 / 1600時間` 前提で確認できるようにした。
- 当月はあえて `売上予算未達` と `工数過負荷` が見える値を入れ、アラート表示もローカルで確認しやすくした。
- 実行:
  - `php artisan db:seed --class=DashboardDemoSeeder`
- 確認:
  - `php -l database/seeders/DashboardDemoSeeder.php`
  - `php artisan db:seed --class=DashboardDemoSeeder`
  - `php artisan tinker --execute='...'` で当月の売上・工数・アラートが非ゼロであることを確認

### Step 30: ダッシュボードのグラフ未表示を調査
- `Dashboard.jsx` には `recharts` による売上/粗利・資金繰り・工数グラフ実装が入っていることを確認。
- 次に、実ブラウザで描画エラーかレイアウト問題かを切り分ける。

### Step 31: グラフを上段へ再配置し、空状態も明示
- グラフ未実装ではなく、タブの下に埋もれて初見で見えにくい構成だったため、全社推移グラフをタブより上へ移動した。
- 追加した上段グラフ:
  - 全社 売上・粗利推移
  - 全社 資金繰り・工数推移
- 各セクション内のグラフも、データなし時に空白ではなくメッセージを出すように `EmptyChartState` を追加。
- 軸ラベルも月だけに短縮し、視認性を改善。
- 確認:
  - `npm run build`
- 補足:
  - Playwright の自動ブラウザ確認は、既存 Chrome セッション競合で今回も未実施。

### Step 32: 前年比分析を追加
- `ManagementMetricsService` に前年比データを追加。
  - 当月 vs 前年同月
  - 年初来累計(YTD) vs 前年同期間
  - 前年同月比較チャート用の月次データ
- `periods` に `previous_year_current` / `current_year` / `previous_year` を追加し、UIが比較期間を明示できるようにした。
- ダッシュボードUIに以下を追加。
  - 売上 / 粗利 / 粗利率 / 工数の前年比カード
  - 当月比較とYTD比較のサマリカード
  - 前年同月比較チャート
- ルールベース分析にも `前年比` コメントを追加し、売上だけ伸びて粗利が弱いケースも判別できるようにした。
- 確認:
  - `php -l app/Services/ManagementMetricsService.php`
  - `php artisan tinker --execute='...'`
  - `npm run build`
- 補足:
  - Playwright の実ブラウザ確認は既存 Chrome セッション競合で未実施。

### Step 33: グラフ描画をSVGへ切替
- 画面上でグラフ枠だけが出て本体が見えないため、`recharts` 依存を外し、`Dashboard.jsx` 内でSVGベースの自前コンボチャートへ切り替えた。
- 対象:
  - 全社 売上・粗利推移
  - 全社 資金繰り・工数推移
  - セクション別 売上・粗利推移
  - 前年同月比較チャート
  - 資金繰り推移
  - 工数推移
- 確認:
  - `npm run build`

### Step 34: 凡例と軸ラベルを改善
- グラフ凡例に色だけでなく、棒系列と線系列の見本を追加した。
- X軸ラベルを `1月` 形式に変更し、単なる数字表示をやめた。
- これにより、売上予算/実績と粗利予算/実績の対応関係を初見でも判断しやすくした。
- 確認:
  - `npm run build`

### Step 35: 凡例を明示化
- 凡例を `色見本 + 棒/線 + ラベル` の表示へ変更した。
- 線系列も見本サイズを広げ、予算/実績の意味が凡例だけで伝わるようにした。
- 確認:
  - `npm run build`

### Step 36: 凡例の見本をSVGへ変更
- スクリーンショット上で線系列の色見本が視認できなかったため、凡例の見本をCSS描画からSVG描画へ変更した。
- 棒は色付き四角、線は色付き線+点で固定表示するようにした。
- 確認:
  - `npm run build`

### Step 37: 取引先自動同期に3時間クールダウン追加
- `DashboardController` で取引先自動同期のメタ情報を cache に保持するようにした。
- ダッシュボード表示時は、最終同期から3時間以内なら自動同期をスキップするように変更した。
- 手動の `取引先取得` ボタンはクールダウン対象外のまま維持。
- 画面上に以下を表示するようにした。
  - 最終取引先同期
  - 次回自動同期可能: HH:MM以降
  - クールダウン中バッジ
- 確認:
  - `php -l app/Http/Controllers/DashboardController.php`
  - `php artisan tinker --execute=...` で `next_auto_sync_available_at_label` が `HH:MM以降` になることを確認
  - `npm run build`

### Step 38: 取引先取得ボタンをカード外へ移動
- `取引先取得` ボタンを `経営ダッシュボード` カード内から外し、カード直上の右寄せ位置へ移動した。
- 目的は、経営サマリーカード自体の情報領域と操作ボタンを分離して、視認性を上げること。
- 確認:
  - `npm run build`

### Step 39: 上部バッジ群をカード外へ移動
- スタッフ数・月間キャパ・取引先自動同期クールダウン表示を `経営ダッシュボード` カード内から外へ移動した。
- 上部を `状態表示 + 操作ボタン` の帯としてまとめ、カード内は説明テキストに集中させた。
- 確認:
  - `npm run build`

### Step 40: 上部説明エリアを再設計
- `経営ダッシュボード` カード上部の説明文を、単なる文章列から `同期ステータス / 集計定義 / 集計ルールと補足` の3ブロック構成へ変更した。
- 同期時刻は小カード化し、定義はチップ化、補足ルールは注意カード化して読みやすさを改善した。
- 確認:
  - `npm run build`

### Step 41: 白画面の原因を特定して修正
- `/dashboard` の白画面は `resources/js/Pages/Dashboard.jsx` 内で `formatProductivity` を未定義のまま呼んでいたことが主因だった。
- `formatProductivity()` を復活させ、実行時 `ReferenceError` で React 全体が落ちる状態を解消した。
- 品質確認用のバックグラウンドエージェントでも、同じ未定義参照が最有力原因だと確認した。
- 確認:
  - `npm run build`
  - `php artisan test tests/Feature/DashboardTest.php`

### Step 42: 取引先同期時刻を日本時間表示へ修正
- `config/app.php` に業務表示用の `sales_timezone` を追加し、既定値を `Asia/Tokyo` にした。
- `DashboardController` の取引先同期メタ表示は、保存済みISO時刻を表示時だけ日本時間へ変換するように修正した。
- これにより、`最終取引先同期` と `次回自動同期可能` は日本時間の `HH:MM` で表示される。
- 確認:
  - `php -l app/Http/Controllers/DashboardController.php`
  - `php artisan tinker --execute=...`

### Step 43: 同期ステータスを経営ダッシュボード枠外へ移動
- `同期ステータス` は経営指標ではないため、`経営ダッシュボード` カード内から除外した。
- 上部の状態帯に `最終取引先同期` と `次回自動同期可能` の2カードとして独立配置し、経営ビュー本体は集計定義とルール説明に集中させた。
- 確認:
  - `npm run build`
  - Playwright で `/dashboard` の実画面描画を確認

### Step 44: ダッシュボードに年/月フィルタを追加
- `ManagementMetricsService` を拡張し、選択した年/月を基準に `current / previous / previous_year_current / YTD` を組み立てられるようにした。
- `DashboardController` はクエリ文字列 `year` `month` を受け取り、集計サービスへ渡すように変更した。
- 画面上部に `表示対象` の年/月セレクタを追加し、選択時に `/dashboard?year=YYYY&month=M` へ遷移してKPI・前年比・グラフ・ランキングを切り替えるようにした。
- 確認:
  - `npm run build`
  - `php artisan test tests/Feature/DashboardTest.php`
  - Playwright で `/dashboard` の実画面描画を確認

### Step 45: グラフの最新値表示と前年比サマリーを追加
- 各グラフの凡例直下に `最新 n月` の値チップを追加し、最新月の系列値をグラフを読まずに確認できるようにした。
- 各セクション上部に `前年トレンド` サマリーを追加し、単月前年差と年初来前年差を4指標で一目表示するようにした。
- 追加後に工数系列の単位が通貨表示へ崩れたため、系列ごとにフォーマッタを持てるように補正した。
- 確認:
  - `npm run build`
  - `php artisan test tests/Feature/DashboardTest.php`
  - Playwright で `/dashboard?year=2025&month=3` の実画面描画を確認

### Step 46: 見積明細ごとの複数担当者按分の土台を追加
- `items` JSON の中へ `assignees` を持てるようにし、明細単位で複数担当者と按分率を保存できる基盤を追加した。
- バックエンドでは `EstimateItemAssignmentNormalizer` を新設し、保存時に担当者の空行除去と合計100%への正規化を共通化した。`store` `update` `saveDraft` の3経路を同じ処理へ揃えた。
- 外部ユーザーIDは数値でも文字列でも受けられるようにし、`/api/users` の返り値差異で保存が落ちないようにした。
- 見積入力画面では、内部表示時のみ各明細の下に `担当者按分` エリアを追加し、担当者追加、割合入力、均等按分を行えるようにした。
- 保存テストとして、通常保存・更新・下書き保存の3経路で按分データが正規化されることを `EstimateItemAssignmentTest` で追加確認した。
- ログ確認で `loadProducts()` の `orderBy('name')` がカテゴリ結合時に曖昧になる既存不具合を検知したため、`orderBy('products.name')` へ補正した。
- ローカル確認中に `company_settings.operational_staff_count` 不足でログイン時に 500 になったため、未適用 migration を既存DBでも通るよう安全化してから適用した。
- 確認:
  - `npm run build`
  - `php artisan test tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DashboardTest.php`
  - `php -l app/Services/EstimateItemAssignmentNormalizer.php`
  - `php -l app/Http/Controllers/EstimateController.php`
