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

### Step 47: ダッシュボードへ担当者別の空き状況を追加
- `ManagementMetricsService` で、見積明細の `assignees` を使った担当者別予定工数の月次集計を追加した。対象月はダッシュボードの年/月フィルタに連動し、明細工数を担当割合で按分して `総合 / 開発 / 仕入れ販売` へ配賦する。
- 担当者別集計は `予定工数 / 残余人日 / 稼働率 / 案件数` を返し、担当者未設定の明細は `未割当工数` として別計上するようにした。全員一覧に加えて、`空きが多い順` と `高稼働順` の上位も同時に返す。
- `Dashboard.jsx` では、各セクションに `担当者別の空き状況` と `担当者別稼働一覧` を追加し、追跡対象人数、未割当工数、高稼働人数、空き人数をカードで見せるようにした。
- ローカル確認用の `DashboardDemoSeeder` にも担当者按分入りの開発明細を追加し、シード後に担当者別工数が画面で確認できるようにした。
- 回帰防止として `DashboardTest` に担当者按分から `planned_person_days` と `unassigned_person_days` が返るケースを追加した。
- 確認:
  - `php -l app/Services/ManagementMetricsService.php`
  - `php -l database/seeders/DashboardDemoSeeder.php`
  - `php -l tests/Feature/DashboardTest.php`
  - `php artisan test tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`
  - `npm run build`
  - `php artisan db:seed --class=DashboardDemoSeeder`

### Step 48: 見積明細入力をカードレイアウトへ再設計
- 見積作成画面の明細入力を横長テーブルからカード型レイアウトへ切り替え、品目・詳細・数量・単位・単価・税区分・原価を折り返して表示できるようにした。
- 各明細の上部に `明細 n` ヘッダと金額サマリ、移動・削除ボタンをまとめ、担当者按分も同じカード内に収めて横スクロールを減らした。
- 大画面では補助的な列見出しを残しつつ、実入力はグリッドで自動折返しする形にして、左右切れが起きにくい構成へ変更した。
- 確認:
  - `npm run build`
  - Playwright で `/estimates/create` を開き、品目選択とレイアウト崩れを確認

### Step 49: 見積の基本計算ロジックを切り出して自動テスト化
- 明細金額・原価金額・粗利・粗利率・小計・税額・合計の計算を `resources/js/lib/estimateCalculations.js` へ切り出し、見積作成画面がその共通関数を使うようにした。
- `tests/js/estimateCalculations.test.js` を追加し、単明細計算、標準/軽減/非課税の混在税計算、見積全体サマリを `node --test` で固定した。
- フロント計算の回帰と、既存の保存・担当者按分・ダッシュボード連携の回帰を分けて確認できるよう、`package.json` に `test:js` を追加した。
- 確認:
  - `npm run test:js`
  - `php artisan test tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DashboardTest.php`
  - `npm run build`

### Step 50: 見積管理画面(/quotes)の役割確認とブラッシュアップ方針整理
- `/quotes` は現在、検索・一覧・詳細導線に加えて、予算/実績/粗利/工数のKPIカードまで持っており、`/dashboard` の経営サマリと役割が重複していることを確認した。
- 一方で、見積管理画面として本来重要な「承認待ち優先表示」「自分の案件」「期限超過」「MF未発行」「一括操作」などの実務導線は弱く、一覧操作画面としての役割が不足している。
- 改善方針は、`/dashboard` を経営判断用、`/quotes` を営業・見積運用の作業台に分離し、`/quotes` では作業優先度・アクション・一覧可読性を強化する方向で整理した。
- 提案軸:
  - 上部KPIは経営値から作業値へ寄せる（承認待ち件数、今月発行予定、MF未同期/未発行、期限超過、自分担当件数）。
  - フィルタは保存ビュー化し、「承認待ち」「今月発行」「自分の案件」「要フォロー」をワンクリック化する。
  - 一覧はデスクトップ表＋モバイルカードの2系統にし、主要操作をケバブメニュー依存から脱却する。
  - 明細詳細は右シート継続でよいが、次アクション（編集、MF発行、PDF、請求変換、承認状況確認）を上部固定に寄せる。

### Step 51: 見積管理画面(/quotes)の Phase 1 ブラッシュアップを開始
- `/quotes` の上部を経営KPIから作業KPIへ寄せ、承認待ち / MF未発行 / 自分担当 / 期限超過 を優先表示する方向へ変更した。
- 保存ビューを追加し、`全件 / 承認待ち / 自分の案件 / MF未発行 / 期限超過 / 要フォロー` をワンクリックで切り替えられるようにした。
- `QuoteOperationsSummaryService` を追加し、受注ベースで今月・来月の稼働率、残余人日、受注件数を返すようにして、`/quotes` に判断用の最小限工数情報だけを載せた。
- `/quotes` 上部には「見積運用ワークスペース」ヘッダと `経営ダッシュボードを見る` 導線を追加し、分析は `/dashboard`、運用は `/quotes` という役割を画面上でも分かるようにした。
- 確認:
  - `php -l app/Services/QuoteOperationsSummaryService.php`
  - `php -l app/Http/Controllers/EstimateController.php`
  - `npm run build`
  - Playwright で `/quotes` 表示確認

### Step 52: 見積一覧へ工数注意と期限列を追加
- `/quotes` 一覧に `工数注意` 列と `期限` 列を追加し、今月/来月の受注ベース逼迫状況と見積期限の超過を行単位で把握できるようにした。
- `期限超過` の基準は `due_date` のみへ変更し、これまで混在していた `delivery_date(納期)` は判定から外した。見積期限切れと納期遅れは意味が違うため、営業判断の軸を見積期限に寄せた。
- 主要操作は `詳細 / 編集 / PDF` を行内へ前出しし、複製・削除などは従来どおりその他メニューに残した。
- 確認:
  - `npm run build`
  - Playwright で `/quotes` を開き、保存ビュー・工数注意列・期限列・主要操作表示を確認

### Step 53: 失注登録を /quotes 詳細シートから行えるようにした
- 見積へ `lost_at / lost_reason / lost_note` を追加する migration を作成し、`status=lost` を正式に扱えるようにした。
- `/quotes` の詳細シート上部に `失注にする` ボタンと失注登録ダイアログを追加し、理由・失注日・メモを入力して即時登録できるようにした。
- `期限超過` と `要フォロー` の判定から `lost` を除外し、失注済み案件が永続的に期限超過へ残る状態を解消した。
- 一覧フィルタと保存ビューにも `失注` を追加し、営業追跡対象と失注済みを分けて管理できるようにした。
- 確認:
  - `php artisan migrate --force`
  - `php artisan test tests/Feature/EstimateLostStatusTest.php tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DashboardTest.php`
  - `npm run build`
  - Playwright で `/quotes` 詳細シートから失注登録し、一覧の表示変化を確認

### Step 54: 期限超過案件にアクセス時フォローモーダルを追加
- `/quotes` アクセス時に、失注でも受注済みでもなく、追跡期限を過ぎた案件がある場合は1件だけ判断モーダルを出すようにした。
- `まだ追う` を選んだ場合は `follow_up_due_date` を更新し、見積期限 `due_date` とは別に営業追跡期限で管理するようにした。
- モーダル表示済みの案件は `overdue_prompted_at` を記録し、同日に毎回出続けないようにした。翌日以降にまだ期限超過なら再度対象になる。
- 追跡期限の判定は `follow_up_due_date ?? due_date` に切り替え、一覧の `期限` 列・`期限超過`・`要フォロー` も同じ基準で動くように揃えた。
- 確認:
  - `php artisan migrate --force`
  - `php artisan test tests/Feature/EstimateLostStatusTest.php tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DashboardTest.php`
  - `npm run build`
  - Playwright で `/quotes` アクセス時にモーダル表示、失注登録、追跡期限保存を確認

### Step 55: 資金繰りで使える入金予定日の根拠を調査
- `billings` / `local_invoices` / `estimates` と、`DashboardController` / `ManagementMetricsService` の資金繰り集計ロジックを確認した。
- 現状、ダッシュボードの入金実績は `billings.due_date` と `payment_status` を使っており、厳密な `actual_paid_at` や `planned_payment_date` は持っていないことを確認した。
- `estimates` 側にも `入金予定日` 専用カラムはなく、資金繰りの見込み入金は `estimate.due_date` を回収日代用として扱っていることを確認した。
- `local_invoices` と `billings` には `billing_date / due_date / sales_date / payment_status` はあるが、将来の入金予定を別軸で持つ設計にはなっていない。
- 結論として、現状の「資金繰り」は `due_date` ベースの近似であり、正確な資金繰りへ上げるには `請求予定日 / 入金予定日 / 支払予定日` の専用管理が追加で必要。

### Step 56: ダッシュボード資金繰りを受注確定案件の納期翌月入金へ補正
- `ManagementMetricsService` の回収予定日ロジックを修正し、`is_order_confirmed=true` の案件は `delivery_date ?? due_date ?? issue_date` を基準に翌月入金として集計するようにした。
- 未受注案件は従来どおり `due_date` を仮の回収予定として扱い、見込み資金繰りを壊さないようにした。
- ダッシュボード上部の集計ルールにも `cash_rule` を追加し、受注済みと未受注で回収根拠が違うことを画面上で説明できるようにした。
- `DashboardTest` に、受注確定案件の `delivery_date=2026-04-20` が `2026年5月` の回収見込みへ入ることを固定するテストを追加した。

### Step 57: /business-divisions の不具合と画面責務の重複を調査
- `/business-divisions` は壊れた画面というより、集計元が `billings` / `local_invoices` に限定されており、`quotes` / `orders` / `dashboard` が使っている `estimates` 系の受注・見積データと分断されていることを確認した。
- そのため、請求データが無い、または同期が薄い環境では `/business-divisions` が実質空画面になりやすく、「動いていない」ように見える構造になっている。
- `/dashboard` は経営分析、`/quotes` は見積運用、`/orders` は受注一覧として役割が見え始めている一方で、`/business-divisions` は請求実績の事業区分集計に寄り過ぎており、同じ事業区分を別軸で複数画面に散らしているのが根本問題。
- 方針としては、`/business-divisions` を独立した主画面として育てるより、請求実績ベースの「事業区分実績レポート」に役割を限定するか、`/dashboard` へ吸収して `/quotes` `/orders` と二重集計しない形へ寄せるべきと整理した。

### Step 58: 事業区分集計をダッシュボードへ統合
- `BusinessDivisionAnalysisService` をダッシュボードへ接続し、Money Forward請求と自社請求の `billing_date` ベースで年次の事業区分分析データを返すようにした。
- `/dashboard` に `事業区分分析` セクションを追加し、事業区分別の構成比、月別推移、選択月の請求明細を1画面で見られるようにした。事業区分の修正導線は商品管理へ寄せた。
- `/business-divisions` は独立画面をやめ、`/dashboard` へリダイレクトするように変更した。サイドメニューの `事業区分集計` も削除した。
- `DashboardTest` に、請求データから事業区分分析が返ることと、旧 `/business-divisions` が `/dashboard` へ転送されることを固定するテストを追加した。
- 確認:
  - `php artisan test tests/Feature/DashboardTest.php`
  - `npm run build`
  - `tail -n 20 storage/logs/laravel.log`

### Step 59: 事業区分分析のグラフ化と見積作成中の負荷シミュレーションを追加
- `/dashboard` の `事業区分分析` に、上位事業区分の請求実績を月別に比較するグラフを追加した。表だけでなく推移でも判断できるようにした。
- `EstimateWorkloadSimulationService` を追加し、既存案件の納期月ベース予定工数を担当者別に集計して、見積作成/編集画面へ `workloadSimulation` として渡すようにした。失注・却下案件は集計から除外した。
- 見積作成/編集画面に `担当者負荷シミュレーション` を追加し、対象月、追加工数、未割当工数、担当者ごとの `既存予定 / この見積 / シミュ後 / 残余 / 稼働率` を表示するようにした。
- `EstimateItemAssignmentTest` に、作成画面で負荷シミュレーションが返ることと、編集画面では編集中の見積自身を二重計上しないことを固定するテストを追加した。
- 品質補正として `ManagementMetricsService` のダッシュボード集計から `lost` を除外し、失注案件が工数や予実へ残らないようにした。
- 確認:
  - `php artisan test tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`
  - `npm run build`
  - `tail -n 20 storage/logs/laravel.log`

## Step 60: 見積負荷シミュレーションの対象月キー不一致を修正
- 再点検で、見積画面の `simulationTargetMonthKey` が `YYYY-MM` 形式、バックエンドの `workloadSimulation.months[*].month_key` が `YYYY-MM-01` 形式で不一致になっており、既存案件の担当者負荷が対象月に正しく重ならない不具合を確認した。
- `resources/js/lib/estimateWorkloadSimulation.js` を追加し、対象日付を月初キーへ正規化する `toMonthStartKey()` と表示用 `formatMonthLabelFromKey()` を実装した。
- `resources/js/Pages/Estimates/Create.jsx` では上記 helper を使うように変更し、`simulationMonthBaseline?.month_label` を正しく参照するよう修正した。
- `tests/js/estimateWorkloadSimulation.test.js` を追加し、月初キー変換と表示ラベル生成を固定した。
- 確認:
  - `npm run test:js`
  - `php artisan test tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`
  - `npm run build`
  - `tail -n 20 storage/logs/laravel.log`

## Step 61: Xserver の開発/本番DB分離状況を実機確認
- GitHub Actions の deploy 設定から、`dev` は `/home/xero/rakudev`、`main` は `/home/xero/raku` へ配置されることを再確認した。
- Xserver へ read-only で接続し、`/home/xero/raku/.env` と `/home/xero/rakudev/.env` の `APP_ENV / DB_CONNECTION / DB_HOST / DB_DATABASE / DB_USERNAME` を確認した。
- 結果として、開発と本番は同じ MySQL ホスト `mysql807b.xserver.jp` を使うが、DB 名は `xero_raku` と `xero_rakudev` で分かれていた。したがって DB は別である。
- 補足として、`APP_ENV` は本番・開発ともに `production` になっていた。環境判定を `APP_ENV` に依存する実装があると、開発環境でも本番扱いになるため注意が必要。
- 確認:
  - `sed -n '1,240p' .github/workflows/deploy.yml`
  - `ssh xserver '... grep -E "^(APP_ENV|APP_NAME|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME)=" .env ...'`

## Step 62: Xserver 開発環境の APP_ENV を development へ変更
- repo 内を `APP_ENV` / `app()->environment()` / `config('app.env')` で検索し、今回のアプリコードでは `config/app.php` の `'env' => env('APP_ENV', 'production')` 以外に、環境名へ直接依存する分岐がないことを確認した。
- Xserver 開発環境 `/home/xero/rakudev/.env` を変更する前に `.env.backup_app_env_20260321_091653` を作成した。
- 初回は Python で `.env` を読んだ際にサーバ側の文字コード既定値で `UnicodeDecodeError` になったため、ファイルは壊さず中断した。UTF-8 前提の Python 編集は避け、`perl` で `APP_ENV=development` へ安全に置換した。
- 変更後に `/opt/php-8.2.28-2/bin/php artisan config:clear` を実行し、`php artisan about` で Environment が `development` へ切り替わったことを確認した。
- 確認:
  - `rg -n "app\(\)->environment|App::environment|APP_ENV|config\('app.env'\)|env\('APP_ENV'\)" ...`
  - `ssh xserver '... grep -E "^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_HOST|DB_DATABASE|DB_USERNAME)=" .env ...'`
  - `ssh xserver '... php artisan about | sed -n "1,40p"'`

## Step 63: 開発URLの公開状態を確認し、APP_DEBUG運用方針を確定
- `curl -I -L https://salesdev.xerographix.co.jp/` で開発URLへ外部から到達でき、`/login` まで通常の 302 -> 200 で公開されていることを確認した。Basic認証やIP制限のような前段ブロックは見当たらなかった。
- Xserver 上でも `public_html/salesdev -> /home/xero/rakudev/public` の公開シンボリックリンクと `public/.htaccess` を確認し、Laravel 標準の rewrite のみで追加アクセス制限はないことを確認した。
- そのため、開発環境で `APP_DEBUG=true` を常時有効にするのは非推奨と判断した。現在の安全な方針は `APP_ENV=development` + `APP_DEBUG=false` を通常運用とし、障害調査時だけ一時的に `APP_DEBUG=true` へ変更して戻す運用である。
- 確認:
  - `curl -I -L --max-redirs 5 https://salesdev.xerographix.co.jp/`
  - `ssh xserver 'ls -la /home/xero/xerographix.co.jp/public_html ...'`
  - `ssh xserver 'grep -E "^(APP_ENV|APP_DEBUG|APP_URL)=" /home/xero/rakudev/.env'`

## Step 64: DashboardDemoSeeder を既存顧客・既存スタッフ整合へ改修
- `DashboardDemoSeeder` の架空顧客・架空スタッフ依存を廃止し、既存 `partners` と `users` を参照してダミー見積・担当者按分・保守スナップショットを生成するように組み替えた。
- 顧客は `partners.mf_partner_id` と `partners.name` を使い、`estimates.client_id` も実在の `mf_partner_id` を設定するようにした。これにより顧客別集計と既存顧客マスタの整合を保つ。
- スタッフは `users` から既存ユーザーを取得し、`Codex/test/session` 系の補助ユーザーを除外したうえで、`staff_id / staff_name / items[*].assignees[*].user_id` を実在ユーザーへ寄せた。人数設定 `company_settings.operational_staff_count` も実在スタッフ数に合わせるようにした。
- `mf_department_id` の架空値 `demo-dept` はやめて `null` にした。存在しない部門IDをダミー見積へ入れないため。
- `php artisan db:seed --class=DashboardDemoSeeder` 後に、デモ見積 84 件で `missing_client_ids=[]`、`missing_assignee_ids=[]` を確認した。現在のローカルでは実在スタッフ母数が 2 名なので、運用上の人日キャパも 2 名基準へ揃う。
- 確認:
  - `php -l database/seeders/DashboardDemoSeeder.php`
  - `php artisan db:seed --class=DashboardDemoSeeder`
  - `php artisan tinker --execute='...'`
  - `tail -n 20 storage/logs/laravel.log`

## Step 65: DashboardDemoSeeder の明細を既存商品マスタへ紐付け
- 再確認で、デモ見積の `items[*]` は `product_id` と `code` を持たず、既存 `products` マスタが利用されていないことを確認した。
- `DashboardDemoSeeder` に既存 `Product` の読込を追加し、開発系は `A-001(要件定義)` `B-001(開発)` `F-001(サプライ)`、販売系は `E-001(ハードウェア)` `F-001(サプライ)` を既存商品から解決するように変更した。
- 生成明細は `product_id / code / name / unit / tax_category` を商品マスタ由来にし、価格・原価・事業区分だけ必要に応じて上書きする構成へ変更した。これにより商品マスタとの紐付けを保ちながら、ダッシュボード確認用の金額バリエーションも維持した。
- `php artisan db:seed --class=DashboardDemoSeeder` 後に、デモ明細 216 件で `items_missing_product_link=0`、`missing_product_ids_sample=[]` を確認した。
- 確認:
  - `php artisan tinker --execute='... Product::select(...) ...'`
  - `php artisan db:seed --class=DashboardDemoSeeder`
  - `php artisan tinker --execute='... items_missing_product_link ...'`

## Step 66: ローカル users の同期元を確認し、実在スタッフを安全に追加する方針を整理
- `DashboardDemoSeeder` の母集団を実運用へ寄せるため、ローカル `users` の同期元と既存経路を再確認する。
- `UserSeeder` は既存ユーザーが1件でもあると同期を止めるため、今のローカル状態では外部スタッフを追加できないことを確認した。
- `/api/users` は外部 API `https://api.xerographix.co.jp/api/users` からスタッフ一覧を取得しており、ローカル `.env` でも同系統の外部 API が利用可能なため、既存ユーザーを壊さず upsert できるローカル専用同期コマンドを追加する方針とした。
- 実行後は `DashboardDemoSeeder` を再投入し、担当者按分と人数設定が実在スタッフ母集団へ寄ることを確認する。
- `ExternalUserSyncService` と `users:sync-external` コマンドを追加し、既存ユーザーがいても外部 API のスタッフ一覧を local `users` へ upsert 同期できるようにした。`UserSeeder` はその Service を再利用する形へ寄せた。
- `php artisan users:sync-external` を実行し、外部 API 取得 10 件に対して `作成9件 / 更新1件 / external_user_id紐付け9件 / 合計13件` を確認した。同期後の実在スタッフ候補は `LARS HONDA HAPPEL / 植木健司 / 守部幸洋 / KCS湯原 / 牛島麻理子 / 古閑信広 / 園田美和 / 川口大希 / 吉井靖人 / 三井明` に加え、既存ローカルの `廣川 京助` を保持する形となった。
- `DashboardDemoSeeder` を再投入し、`demo_estimates=84`、`missing_client_ids=[]`、`missing_assignee_ids=[]`、`company_settings.operational_staff_count=11` を確認した。これでダッシュボード用ダミー見積の担当者按分と人数設定が実在スタッフ母集団に寄った。
- 確認:
  - `php artisan test tests/Feature/SyncExternalUsersCommandTest.php tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`
  - `php artisan users:sync-external`
  - `php artisan db:seed --class=DashboardDemoSeeder`
  - `php artisan tinker --execute="..."`

## Step 67: local users のパスワード統一と不要ユーザー削除
- local のログイン検証を簡単にするため、全 local users のパスワードを `00000000` へ統一し、不要ユーザー `廣川 京助` を削除する。
- 削除前に users 参照とダミー見積の担当者参照が壊れないよう確認し、必要なら `DashboardDemoSeeder` を再投入して整合を戻す。
- local users 全件のパスワードを `00000000` に統一した。ログイン検証時の混乱をなくすためで、対象は補助ユーザーも含む local `users` 全件。
- 不要ユーザー `廣川 京助`（id=1）を local DB から削除した。削除前に参照を確認し、`staff_id=1` はダミー見積 8 件のみ、その他本線データ参照はないことを確認した。
- 削除後に `DashboardDemoSeeder` を再投入し、`demo_staff_refs_deleted_user=0`、`demo_assignee_refs_deleted_user=0`、`operational_staff_count=10` を確認した。これでダミー見積の担当者割当から削除ユーザー参照は消えた。
- 確認:
  - `php -r '... User::query()->update([password => Hash::make("00000000")]) ...'`
  - `php artisan db:seed --class=DashboardDemoSeeder`
  - `php artisan tinker --execute="... Hash::check('00000000', User::find(3)->password) ..."`
  - `tail -n 20 storage/logs/laravel.log`

## Step 68: DashboardDemoSeeder を development 環境だけ自動適用する
- GitHub Actions の deploy は `main` と `dev` の両方で `php artisan migrate --force --seed` を実行しているため、`DatabaseSeeder` 側で環境分岐しないと本番にも DashboardDemoSeeder が載るリスクがある。
- `APP_ENV=development` に変えた Xserver 開発環境だけで DashboardDemoSeeder を自動実行し、production では絶対に走らせない分岐を `DatabaseSeeder` に追加する。
- `DatabaseSeeder` に `App::environment('development')` 分岐を追加し、`DashboardDemoSeeder` を Xserver 開発環境(dev)だけ自動適用するようにした。本番 production では呼ばれない。
- `DatabaseSeederEnvironmentTest` を追加し、development では `DashboardDemoSeeder` が呼ばれ、production では呼ばれないことを固定した。
- 確認:
  - `php -l database/seeders/DatabaseSeeder.php`
  - `php artisan test tests/Feature/DatabaseSeederEnvironmentTest.php tests/Feature/SyncExternalUsersCommandTest.php tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`

## Step 69: Xserver 開発環境(dev)で DashboardDemoSeeder を実行
- `dev` へ push 済みのため、Xserver 開発環境 `/home/xero/rakudev` に Seeder が配備済みか確認し、`DashboardDemoSeeder` を明示実行する。
- 本番 `/home/xero/raku` には触れず、開発環境 DB `xero_rakudev` のみ対象とする。
- Xserver 開発環境 `/home/xero/rakudev` で `APP_ENV=development`、`DB_DATABASE=xero_rakudev`、`database/seeders/DashboardDemoSeeder.php` 配備済み、デプロイ済みコミット `e9b0ec9` を確認した。つまり Seeder のコードは dev に載っている。
- 開発環境で `/opt/php-8.2.28-2/bin/php artisan db:seed --class=DashboardDemoSeeder --force` を実行した。
- 実行後に dev サーバで `demo_like=84`、`source_dashboard_seed=24`、`all_estimates=151`、`staff_count_setting=10` を確認した。これで開発 DB にダッシュボード確認用データが投入済み。
- 確認:
  - `ssh xserver 'cd /home/xero/rakudev && grep -E "^(APP_ENV|DB_DATABASE)=" .env && ls database/seeders/DashboardDemoSeeder.php'`
  - `ssh xserver 'cd /home/xero/rakudev && /opt/php-8.2.28-2/bin/php artisan db:seed --class=DashboardDemoSeeder --force'`
  - `ssh xserver 'cd /home/xero/rakudev && /opt/php-8.2.28-2/bin/php artisan tinker --execute="..."'`

## Step 70: /quotes の期限超過モーダルを複数件連続処理へ拡張
- 期限超過が複数ある場合、現状は 1 件だけ返して `後で確認` 後に次候補が見えないため、営業運用として弱い。
- `/quotes` で候補一覧を受け取り、モーダル内で 1 件ずつ処理しながら次候補へ進める方式へ変更する。残件数表示と `後で確認` 後の即時次候補表示も追加する。
- `QuoteOverdueFollowUpService` を複数件返却できるよう拡張し、期限超過候補を「古い期限順 → 同日なら id 順」で返すようにした。`findPromptCandidate()` は互換用に先頭1件を返すラッパーへ変更。
- `/quotes` は `overdueFollowUpPrompts` を受け取り、モーダル内で 1 件処理したら次候補へ進むキュー方式へ変更した。`後で確認` でも次候補へ進み、残件数を表示する。
- `EstimateLostStatusTest` を更新し、複数の期限超過案件が古い期限順で props に並ぶことを固定した。
- 当初 `Collection::sortBy([...])` では順序が安定せずテストが落ちたため、比較関数を使う `sort()` に修正して順序保証を明示した。
- 確認:
  - `php artisan test tests/Feature/EstimateLostStatusTest.php tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DatabaseSeederEnvironmentTest.php tests/Feature/SyncExternalUsersCommandTest.php`
  - `npm run build`
  - `tail -n 20 storage/logs/laravel.log`

## Step 71: 期限超過モーダルを営業判断用にブラッシュアップ
- 現状モーダルは見積番号・顧客名・期限だけで、失注か延長かの判断材料が不足している。
- `/quotes` の既存 props か追加 props を使い、金額・工数・担当者・承認状態・前回追跡メモ・詳細導線をモーダルへ追加する。
- `/quotes` の期限超過モーダルに、見積金額・粗利・概算工数のサマリカード、担当者・承認状態・見積日・納期・最終更新の情報、前回追跡メモ、主な明細、詳細シート導線を追加した。
- 既存の props だけで判断材料を組み立てるため、`Quotes/Index.jsx` 側で明細金額・原価・粗利・工数・承認状態を計算する helper を追加した。追加 API は増やしていない。
- `後で確認`・`まだ追う`・`失注にする` の既存フローは維持し、判断材料だけを増やした。詳細が必要な案件だけ `詳細シートを開く` で掘り下げられる。
- 確認:
  - `php artisan test tests/Feature/EstimateLostStatusTest.php tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php tests/Feature/DatabaseSeederEnvironmentTest.php tests/Feature/SyncExternalUsersCommandTest.php`
  - `npm run build`
  - `tail -n 20 storage/logs/laravel.log`

## Step 72: 期限超過モーダルの表示切れを防ぐ
- 期限超過モーダルは情報量が増えたことで、環境によって上下が切れやすくなっていた。
- `DialogContent` の幅を広げ、`max-h` と `overflow-y-auto` を追加して、縦方向に溢れた場合でもモーダル内スクロールで確認できるようにした。
- 失注/延長の操作フローや候補キュー処理は変更していない。
- 確認:
  - `php artisan test tests/Feature/EstimateLostStatusTest.php`
  - `npm run build`

## Step 73: /quotes のステータス表示を受注済・失注込みで整合させる
- 受注確定しても一覧と詳細のステータス表示が `承認済` のままで、業務上の状態と見た目がずれていた。
- `is_order_confirmed=true` を表示上の `受注済` として扱う helper を追加し、一覧と詳細シートのステータスバッジを統一した。
- ステータス絞り込みにも `受注済` を追加し、バックエンドでは `status=order_confirmed` を `is_order_confirmed=true` として扱うようにした。
- `期限` 列も受注済みは `受注済 / 納期` を優先表示するようにし、失注は従来どおり `失注` ラベルを維持する。
- 確認:
  - `php artisan test tests/Feature/EstimateLostStatusTest.php`
  - `npm run build`

## Step 74: 見積明細の担当者未設定をその場で警告する
- 見積作成/編集画面では担当者按分が任意のため、未設定のままでも保存できるが、工数シミュレーション精度が下がる。
- 明細カードの `担当者按分` セクション内に、工数対象なのに担当者未設定または按分割合未入力の明細だけ警告を表示するようにした。
- 保存ボタンエリアにも全体警告を追加し、未設定明細件数と影響工数をまとめて表示するようにした。保存自体は禁止しない。
- 警告が弱く見落としやすかったため、明細内警告と保存前警告を薄い赤系の配色に変更して目立たせた。
- さらに、空状態の `まだ担当者が未設定です` も警告扱いへ寄せ、赤系背景と警告アイコンを付けた。明細内警告・保存前警告にも同じアイコンを追加した。
- 確認:
  - `npm run build`
  - 実機で見積作成/編集画面を開き、担当者未設定時に明細警告と保存前警告が出ることを確認

## Step 75: ユーザー別の月間開発キャパ設定を追加する
- これまでの工数基準は全員同じ1人月前提だったため、営業や総務のように開発可能工数が異なる人を正しく表現できなかった。
- `users.work_capacity_person_days` を追加し、ユーザーごとに月間開発キャパを保持できるようにした。未設定ユーザーは従来どおり標準1人月をフォールバックで使う。
- 設定画面にユーザー別の月間開発キャパ入力欄を追加し、合計キャパと稼働人数は個別設定の合算で計算するようにした。
- ダッシュボードの担当者別工数と見積の負荷シミュレーションも、各担当者の個別キャパを基準に稼働率と残余を算出するように切り替えた。
- 確認:
  - `php artisan test tests/Feature/AdminCapacitySettingsTest.php tests/Feature/DashboardTest.php tests/Feature/EstimateItemAssignmentTest.php`
  - `npm run build`
- `標準人数設定` は誤解を生むため設定画面から外し、標準1人月の説明だけ残した。実際の月間キャパはユーザー別人日の合計を使う。

## Step 76: local 補助ユーザーを業務UIから除外する
- local に残す `Codex UI Check` / `Codex Session User` は開発補助用であり、業務上のスタッフ候補や工数母集団に混ぜると誤解を生む。
- `User::visibleForBusiness()` を追加し、設定画面のキャパ一覧や会社設定の人数・合計キャパ計算から補助ユーザーを除外するようにした。
- `/api/users` もレスポンス内の同名・同メール補助ユーザーを落とすようにし、担当者候補へ出にくくした。
- 確認:
  - `php artisan test tests/Feature/AdminCapacitySettingsTest.php`
  - `npm run build`

## Step 77: 見積負荷シミュレーションと見積一覧の工数表示を個別キャパ前提へ揃える
- 見積画面の `担当者負荷シミュレーション` は個別キャパ対応後も `標準月間キャパ` 表示が残っており、対象月の合計キャパや既存余力が見えず整合性が弱かった。
- 見積画面では `対象月の合計キャパ` と `対象月の既存余力` を追加し、標準1人月は補助説明へ下げた。これで個別キャパ設定との関係が読みやすくなった。
- `/quotes` の受注ベース工数サマリにも `月間キャパ工数 / 受注工数 / 余力工数(不足工数)` をカード表示し、設定内容が反映されているか一覧画面で確認できるようにした。
- `QuoteOperationsSummaryService` の工数換算も `人月=標準1人月`、`時間=8時間=1人日`、`第1種品目除外` のルールへ揃え、見積画面と一覧画面の工数計算差を減らした。
- 確認:
  - `php artisan test tests/Feature/EstimateLostStatusTest.php tests/Feature/AdminCapacitySettingsTest.php`
  - `npm run build`

## Step 78: /quotes 上部ブロックの並びを縦構成へ整理する
- `保存ビュー` と `受注ベース工数の目安` を横並びにしていたため、情報の種類が違う2ブロックを同時に追う必要があり視線移動が多かった。
- 上部レイアウトを縦積みに変え、`保存ビュー -> 工数判断` の順に読めるようにした。
- `保存ビュー` は丸ボタン群ではなくカード状のグリッドに変え、ラベル・補足・件数を1ブロックで把握できるようにした。
- `受注ベース工数の目安` はその下に独立配置し、今月/来月カードの比較をしやすくした。
- 確認:
  - `npm run build`

- Step 79 (2026-03-21 13:05 JST)
  - /quotes 上部の保存ビューを一覧直上へ移設し、横一列の小ボタンへ変更。
  - 承認待ち/MF未発行/自分担当/期限超過の重複サマリーカードを削除して、フィルタUIへ役割を集約。
  - build成功、ログに今回変更起因の新規例外なしを確認。

- Step 80 (2026-03-21 13:18 JST)
  - ダッシュボードの経営分析が全タブで overall 共通内容を表示していたため、区分別 analysis を追加。
  - overall は全社分析、development/sales/maintenance は区分別の売上・粗利・工数・回収観点へ分離。
  - DashboardTest に区分別 analysis の回帰確認を追加。

- Step 81 (2026-03-21 13:24 JST)
  - ダッシュボードの担当者別空き状況が、按分入力済み担当者だけを母集団にしていたため、個別キャパ設定済み担当者も0工数で集計対象へ含めるよう修正。
  - 表示文言も「20人日基準」から「個別キャパ基準」へ修正。
  - DashboardTest を更新し、未割当だがキャパ設定済み担当者が空きとして計上されることを固定。

- Step 82 (2026-03-21 13:38 JST)
  - ダッシュボードの担当者別空き状況は、個別キャパ設定済みで未割当の担当者も集計対象に含める仕様へ統一。
  - 集計テストは、ログインユーザーを業務UI非表示ユーザーとして作成し、キャパ設定済み担当者3名のみが母集団になることを固定。
  - DashboardTest と AdminCapacitySettingsTest を再実行し、担当者空き状況の回帰がないことを確認。

- Step 83 (2026-03-21 13:47 JST)
  - 未割当工数が 0 になっていた原因は、担当者別集計を delivery_date 当月だけに限定していたこと。
  - 納期未設定でも due_date / issue_date をフォールバックして担当者別集計対象月を決めるよう修正。
  - DashboardTest に、delivery_date なし・due_date 当月の未割当 2.5人日が `unassigned_person_days` に入る回帰テストを追加。

- Step 84 (2026-03-21 14:02 JST)
  - 見積編集画面で、旧データの top-level 担当者(staff_id/staff_name)しか入っていない工数明細は、初期表示時に 100% 按分として補完するよう修正。
  - これにより、既存見積を開いたときに『全部未割当』に見える問題を軽減し、保存時に新しい assignees 形式へ寄せられるようにした。
  - /quotes の工数注意列には `担当未割当` / `按分未設定` を追加し、トップレベル担当者しかない旧見積も一覧で判別できるようにした。
  - quoteEffortNotice の JS テストを追加し、未割当・按分未設定・按分済みの判定を固定した。

- Step 85 (2026-03-21 14:18 JST)
  - 工数対象/担当者必須の判定を『第1種以外は対象』へ修正し、`一式表示` や `式` 単位でも第1種以外なら未割当/按分未設定ラベルが出るように統一。
  - 旧見積の top-level 担当者補完も単位判定をやめ、第1種以外の明細なら 100% 按分候補として初期表示するよう修正。
  - 商品編集画面は、商品分類マスタが空のときに原因が分かる警告を表示し、分類セレクトを無効化して `+` から追加すべきことを明示した。

- Step 86 (2026-03-21 14:28 JST)
  - local DB の `categories` が空だったため、商品管理の分類プルダウンが実質使えない状態だった。
  - local に `コンサル(A) / 開発(B) / 設計(C) / 管理(D) / ハードウェア(E) / サプライ(F) / ライセンス(G)` を既存の英字自動採番ルールに沿って投入した。
  - これで local の商品編集/作成画面で商品分類を選択できる前提データが揃った。

- Step 87 (2026-03-21 14:34 JST)
  - 既存見積の明細で `product_id` が空でも `code/name` から既存商品を解決し、見積編集画面の品目プルダウンに選択状態が出るよう補完を追加。
  - `EST-9-110-261103-001` のように `A-001/B-001/C-001/B-002` だけ保持している旧明細でも、local の商品マスタに紐付いて表示される前提へ修正。

- Step 88 (2026-03-21 14:40 JST)
  - 見積編集画面の担当者按分UIで、第1種明細でも空の按分ブロックが赤警告表示されていたため、レンダリング条件を修正。
  - 第1種は `担当者設定不要` バッジと案内文だけを表示し、赤警告・担当者追加操作は出さないようにした。

- Step 89 (2026-03-21 14:48 JST)
  - local DB の `categories` が空で商品編集画面の分類選択が使えなかったため、`CategorySeeder` を local で実行。
  - 既存の自動採番ルールどおり `コンサル(A) / 開発(B) / 設計(C) / 管理(D) / ハードウェア(E) / サプライ(F) / ライセンス(G)` の 7 件が投入されたことを確認。

- Step 90 (2026-03-21 14:56 JST)
  - 見積編集画面で第1種商品を選んでも担当者未設定警告が残る不具合を修正。
  - 原因は事業区分判定が `item.business_division` を見ず、商品マスタ逆引きだけに依存していたこと。
  - 明細自身の `business_division` を最優先で使うようにし、第1種は即座に `担当者設定不要` 表示へ切り替わるようにした。

- Step 91 (2026-03-21 15:20 JST)
  - `/help` を旧仕様の静的説明から全面刷新し、最新版ルールに合わせた 2 層構成へ置き換えた。
  - 上部にクイックリンクと重要変更カード、本文はダッシュボード / 見積作成・編集 / 見積一覧 / 商品管理 / MF連携のセクション別アコーディオンへ再構成。
  - 第1種/第5種、担当者按分、個別キャパ、失注/追跡期限、事業区分分析統合など直近の仕様変更をヘルプへ同期した。

- Step 92 (2026-03-21 15:35 JST)
  - `/orders` の現状責務を確認。受注件数・粗利・資金繰りダッシュボードを同一画面に持っており、`/dashboard` と分析責務が重複している一方で、受注後運用の次アクション導線が弱いことを整理。
  - 方針は、`/orders` を『受注後の実行管理・回収管理』へ寄せ、経営分析や全社KPIは `/dashboard` へ寄せたままにする案を採用。
- Step 93 (2026-03-21 15:26 JST)\n  - /help を全面刷新し、最新仕様に合わせてダッシュボード / 見積作成・編集 / 見積一覧 / 注文書一覧 / 商品管理 / MF連携の章立てへ再構成。\n  - 画面ごとの役割分担、第1種/第5種ルール、個別キャパ、失注/追跡期限、経営分析が現時点ではルールベースである点をヘルプへ反映。\n  - UX は sticky 目次 + 章別アコーディオン + 重要変更カード + 最後の困ったとき導線に整理。

- Step 93 (2026-03-21 16:35 JST): ダッシュボード AI 分析は全社限定で進める方針を確定。現行の rule-based analysis / alerts は section ごとに残し、AI は overall だけを日次保存で上書きする設計を採用。初回アクセス時は metrics 集計後に当日分 AI キャッシュを確認し、未生成時のみ生成・保存する案を整理。

- Step 94 (2026-03-21 16:48 JST): ダッシュボードの overall 限定 AI 分析を実装。dashboard_ai_analyses テーブルと DashboardAiAnalysisService を追加し、初回アクセス時のみ OpenAI で overall 分析を生成して日次保存、以後は当日キャッシュを再利用。OpenAI 未設定/失敗時は既存 rule-based analysis を fallback として表示し、Dashboard には AI/ルールのメタ表示を追加。

- Step 95 (2026-03-21 17:05 JST): ダッシュボード最上部に表示する AI 総評を追加する方針で着手。選択年月に連動する overall AI 分析結果から、総評・注目ポイント・改善アクションを上部エリアへ表示する構成にする。

- Step 96 (2026-03-21 18:20 JST)
  - `/help` を最新版に合わせて全面刷新。トップにクイック導線、重要変更カード、先に押さえる運用ルールを配置し、本文は画面別の2階層アコーディオンへ再構成。
  - ダッシュボードの総合タブ AI 総評、section別ルール分析、第1種/第5種の工数ルール、個別キャパ、見積一覧/注文書一覧の役割分担、商品分類コード自動採番をヘルプへ反映。
  - sticky 目次、章ごとのチップ、困ったときカードを追加し、情報量が増えても目的別に辿れる UX へ整理。

- Step 97 (2026-03-21 18:32 JST)
  - ダッシュボード最上部に AI 経営総評を出すため、overall AI 分析の `overview` を controller から props へ明示的に渡す方針に決定。
  - 選択年月ごとに総評が切り替わることを前提に、summary / 注目ポイント / 改善アクションを上部カードへ集約する。
  - 併せて、AI失敗時フォールバックの未定義変数不具合も解消し、回帰テストを追加する。

- Step 98 (2026-03-21 18:36 JST)
  - `DashboardAiAnalysisService` の fallback 経路で未定義変数になっていた箇所を修正し、失敗時も `analysis_overview` を安全に扱えるようにした。
  - `DashboardController` から `dashboardMetrics.analysis_overview` を返し、`Dashboard.jsx` 最上部に「何がポイントか / 何を改善すべきか」を読む AI 総評カードを追加。
  - `php artisan test --filter=DashboardTest` は 12 件 pass、`npm run build` も成功。期間切替で保存済み AI 総評が切り替わる回帰テストを追加済み。

- Step 99 (2026-03-21 18:45 JST)
  - local DB で `dashboard_ai_analyses.analysis_overview` 列未反映のままAI保存が走り、`Unknown column 'analysis_overview'` で 500 になることを確認。
  - `DashboardAiAnalysisService` を後方互換化し、列が未作成の環境では `analysis_overview` を INSERT/UPDATE 対象から外すよう修正。
  - これにより migration 未適用でも画面表示は継続できるようにしつつ、列追加後は自動で永続保存を使う構成にした。

- Step 100 (2026-03-21 18:58 JST)
  - `dev` への共有依頼を受け、現ワークツリー差分をまとめて commit / push する方針に決定。
  - release readiness 上は実機/UI確認未完了のため `not_ready` 判定だが、共有優先で残リスクを明示したうえで進める。

- Step 101 (2026-03-21 19:05 JST)
  - 追加要望に合わせて、ダッシュボード最上部の AI 総評エリアをアコーディオン化する方針へ変更。
  - 初期表示は閉じた状態にし、必要なときだけ開いて総評・ポイント・改善アクションを読む構成へ調整する。

- Step 102 (2026-03-21 19:20 JST)
  - 保守売上管理の local / dev 数値差分を調査し、`dashboard_demo_seed_v1` の demo snapshot が API値より優先されていることを確認。
  - あわせて、直近6ヶ月グラフが選択月ではなく DB 末尾6件を表示している実装差分も確認。
  - demo snapshot の当月自動再同期と、選択月基準グラフへの修正で揃える方針に決定。

- Step 103 (2026-03-21 19:42 JST)
  - 追加調査で、dev デプロイ時に GitHub Actions が `php artisan migrate --force --seed` を実行し、`APP_ENV=development` では `DashboardDemoSeeder` が毎回 demo の保守 snapshot を再投入することを確認。
  - そのため dev では修正デプロイ後も非当月の保守 snapshot が `dashboard_demo_seed_v1` に戻り、本番と数値が乖離していた。
  - 対策は、demo seeder から保守 snapshot の自動投入を既定で外し、snapshot 不在時は当月を API から生成する方針とした。

- Step 104 (2026-03-21 19:55 JST)
  - dev / local の開発ノイズ対策として、`DashboardDemoSeeder` の自動実行を env フラグで明示許可制に変える方針を確定。
  - 既に混入した `DEMO-DASH` 見積と `dashboard_demo_seed_v1` snapshot を安全に削除する Artisan コマンドを追加する。
  - cleanup コマンドの回帰テストも追加し、実データを巻き込まないことを固定する。

- Step 105 (2026-03-21 20:12 JST)
  - 再発防止のため、`dev` deploy 後に `dashboard:purge-demo-data` を自動実行する方針を追加。
  - `DatabaseSeeder` の demo seed 条件は env フラグ込みで回帰テストへ反映し、`development` だから自動投入される状態を固定的に解消する。
  - 併せて、直アクセス可能な `/inventory` が mock 固定かつ実行時エラーを含んでいたため、mock 明示と最低限の表示整合も合わせて修正する。

- Step 106 (2026-03-21 20:18 JST)
  - `DatabaseSeederEnvironmentTest` 3件、`PurgeDashboardDemoDataCommandTest` 2件、`MaintenanceFeeControllerTest` 3件を実行し、seed 条件・cleanup・保守売上回帰がすべて pass。
  - `npm run build` も成功し、`Inventory/Index.jsx` の JSX 崩れや参照エラーがないことを確認。
  - 残確認は dev 実機デプロイ後に `dashboard:purge-demo-data` が実行され、既存 demo データが除去されることのサーバ確認のみ。

- Step 107 (2026-03-22 00:18 JST)
  - 保守売上管理の要件確認のため、`docs/MAINTENANCE_FEE.md`、`MaintenanceFeeController`、画面 JSX、見積・ダッシュボード側の利用箇所を横断確認。
  - 現仕様では `support_type` フィルタ UI 未実装、サマリーと検索結果の意味ずれ、履歴証跡不足、取得ロジック分散が主な課題と整理。
  - 改善は「表示の信頼性」「再同期/手修正の証跡」「ロジック集約」を先に行う方針でまとめる。

- Step 108 (2026-03-22 00:24 JST)
  - `docs/MAINTENANCE_FEE_BRUSHUP_PROPOSAL.md` を新規作成し、要件・現仕様・差分・優先度つき改善案・受け入れ観点を文書化。
  - `docs/INDEX.md` に索引を追加し、後から参照しやすい状態へ更新。
  - 今回は提案整理が目的のため挙動変更は行わず、次の実装フェーズで P1 項目から着手できるよう論点を固定した。

- Step 109 (2026-03-22 00:41 JST)
  - 保守売上管理の P1 実装として、`MaintenanceFeeSyncService` を追加し、API取得・snapshot 生成/再同期・source 判定・サポート種別分解を Service に集約。
  - `maintenance_fee_snapshots.last_synced_at` と `maintenance_fee_snapshot_items.entry_source` を追加する migration を作成し、source / 最終同期 / 手修正件数を画面で出せる前提を整備。
  - `MaintenanceFeeController` と `EstimateController` の保守売上取得を Service ベースへ寄せ、ロジック重複を削減。

- Step 110 (2026-03-22 00:48 JST)
  - `MaintenanceFees/Index.jsx` に source / 最終同期 / 手修正件数カード、`support_type` フィルタ、APIエラーバナー、`表示中 / 全体` 併記のサマリーを追加。
  - `MaintenanceFeeControllerTest` を 5 件へ拡張し、source メタ、support_type 絞り込み、API失敗表示を固定。
  - `DatabaseSeederEnvironmentTest`、`PurgeDashboardDemoDataCommandTest`、`DashboardTest`、`npm run build`、`php -l` も通過し、保守売上周辺の回帰を確認。

- Step 111 (2026-03-22 01:18 JST)
  - local の過去月表示を確認し、snapshot 自体は 24 件残っているが、demo source の月では過去の 4 件データが残っていることを確認。
  - 対策として、`dashboard_demo_seed_v1` の snapshot は当月に限らず選択時に API 値へ自動補正するよう変更。
  - フィルタカードを最上部へ移動し、数値状態カードへ適用中の年月・顧客名・サポート種別を表示して、どの条件で見ている数値かを明示する。

- Step 112 (2026-03-22 01:22 JST)
  - `MaintenanceFeeControllerTest` の demo refresh を過去月ケースへ更新し、support_type 適用条件の props も固定。
  - `php artisan test --filter=MaintenanceFeeControllerTest` は 5 件 pass、`npm run build` も成功。
  - これにより、過去月の demo snapshot は対象月を開いた時点で API へ置換され、上部フィルタから条件適用順に画面が読める構成になった。

- Step 113 (2026-03-22 01:34 JST)
  - 追加調査で、`2025-04` の 4件問題は demo ではなく `source=api` かつ `last_synced_at=null` の古い stale snapshot が残っていたことを確認。
  - local では snapshot 自体は 24 件残っており、`2025-04` だけ item_count=4 / total=725000 の古い API 結果だった。
  - 解決策として、local/dev 用に stale snapshot を一括再同期するコマンドを追加し、dev deploy 後にも自動補正できるようにする。

- Step 114 (2026-03-22 01:40 JST)
  - `maintenance:refresh-snapshots` コマンドを追加し、`--legacy-only` で demo または `last_synced_at=null` の古い api snapshot だけを補正できるようにした。
  - `RefreshMaintenanceSnapshotsCommandTest` を追加し、legacy snapshot だけが更新対象になることを固定。
  - dev deploy 後に `maintenance:refresh-snapshots --legacy-only` を自動実行するよう workflow へ追加した。

- Step 115 (2026-03-22 01:45 JST)
  - local で `php artisan maintenance:refresh-snapshots --legacy-only` を実行し、18 件の stale snapshot を API ベースで再同期。
  - `2025-04` は `item_count=65 / total_fee=614523 / last_synced_at=2026-03-22 01:26:23` へ更新されたことを確認。
  - その後 `php artisan test --filter=MaintenanceFeeControllerTest` も再実行し、5 件 pass を確認。

- Step 116 (2026-03-22 02:05 JST)
  - 保守以外の demo データを作り直す依頼に対し、`DashboardDemoSeeder` の現状と画面側の確認パターンを調査。
  - 現行 seeder は `2025-01` から `2026-12` まで固定投入で、見積パターンも受注/見込に偏っていることを確認。
  - 今回はマスターと保守 snapshot を触らず、`DEMO-DASH` 見積だけを `2025-01` から `2026-05` の範囲で再構成する方針を確定。

- Step 117 (2026-03-22 02:18 JST)
  - `DashboardDemoSeeder` を全面的に作り直し、投入対象を `2025-01` から `2026-05` へ制限。
  - 月ごとに `開発受注 / 開発承認待ち / 開発追跡 / 販売受注 / 販売送付済み / 販売失注 / ドラフト` の 7 パターンを固定で投入する構成へ変更。
  - `CompanySetting` 更新と maintenance snapshot 作成/削除を外し、`DEMO-DASH` 見積だけを purge 対象に絞った。

- Step 118 (2026-03-22 02:23 JST)
  - `DashboardDemoSeederTest` を追加し、119件投入、月範囲、主要ステータス、保守非変更を固定。
  - `DatabaseSeederEnvironmentTest` は再実行で 3 件 pass、`DashboardDemoSeederTest` は 1 件 pass。
  - local で `php artisan db:seed --class=DashboardDemoSeeder` を実行し、`DEMO-DASH` 見積 119 件、末尾月 `2026-05`、maintenance snapshot 24 件据え置きを確認。

- Step 119 (2026-03-23 00:06 JST)
  - 保守売上 snapshot の取得タイミングを再調査し、現状は画面アクセス時または手動再同期時のみで、月末自動確定は未実装と確認。
  - 月末自動取得は、当月 snapshot を API から確定保存する専用コマンドを追加し、scheduler で月末 23:55 実行とする方針を確定。
  - 手修正済み月を自動で壊さないため、manual / mixed snapshot は自動実行時にスキップする安全設計で進める。

- Step 120 (2026-03-23 00:15 JST)
  - `maintenance:capture-month-end` コマンドを追加し、当月または指定月の snapshot を API から確定保存できるようにした。
  - 既存 snapshot が manual / mixed かつ手修正を含む場合は既定でスキップし、`--force` 指定時のみ強制再同期する設計にした。
  - `MaintenanceFeeSyncService` に自動更新保護判定を追加し、月末自動化でも既存の手修正運用を壊さないようにした。

- Step 121 (2026-03-23 00:18 JST)
  - `routes/console.php` に scheduler を追加し、`maintenance:capture-month-end` を毎日 23:55 実行しつつ、月末日のみ起動するよう設定。
  - `MaintenanceMonthEndSnapshotCommandTest` を追加し、新規 snapshot 作成と manual snapshot スキップを固定。
  - `php artisan test --filter=MaintenanceMonthEndSnapshotCommandTest` 2件 pass、`MaintenanceFeeControllerTest` 5件 pass、`php artisan schedule:list` でも月末ジョブ登録を確認。

- Step 122 (2026-03-23 00:28 JST)
  - Xserver の cron 運用確認により、毎分 `schedule:run` 前提は不要かつ負荷に対して過剰と判断。
  - 方針を修正し、`maintenance:capture-month-end` コマンド自身に「月末日以外は何もしない」判定を持たせ、Xserver cron から直接呼ぶ構成へ変更することにした。
  - `routes/console.php` の scheduler 定義は削除し、今後は `28-31日 23:55` の軽量 cron からコマンドを直接叩く前提で整理する。

- Step 123 (2026-03-23 00:33 JST)
  - `maintenance:capture-month-end` を修正し、`--month` 未指定時は本日が月末日でなければ何もせず終了するようにした。
  - `MaintenanceMonthEndSnapshotCommandTest` を 3 件へ拡張し、非月末スキップ / 新規作成 / manual snapshot 保護を固定。
  - `php artisan test --filter=MaintenanceMonthEndSnapshotCommandTest` 3件 pass、`MaintenanceFeeControllerTest` 5件 pass、構文確認も問題なし。

- Step 124 (2026-03-23 00:42 JST)
  - 利用者承認を受けて、Xserver の cron 設定変更を実施するフェーズへ移行。
  - `salesdev` と `sales` の両方に対して、`28-31日 23:55` に `maintenance:capture-month-end` を直接実行する cron を追加する方針で操作を開始。

- Step 125 (2026-03-23 00:49 JST)
  - SSH で Xserver に接続し、既存 crontab を確認したうえで `salesdev` 用の月末保守 snapshot cron を追加。
  - `rakudev` では `maintenance:capture-month-end` を手動実行して、非月末日のため安全にスキップすることを確認。
  - `raku` 本番側はまだ `maintenance:capture-month-end` コマンド未反映で失敗したため、壊れた cron を残さないよう prod 用 cron はいったん削除し、main 反映後に再追加する方針へ切り替えた。

- Step 126 (2026-03-26 12:18 JST)
  - 見積下書き保存で明細が消える報告を受け、`Estimates/Create.jsx` と `EstimateController@saveDraft` の保存経路を調査。
  - 原因は `lineItems` と `useForm.data.items` の二重管理にあり、最新の明細変更が `data.items` へ反映し切る前に保存送信される race と判断。
  - 同じ構造が承認申請送信にもあることを確認し、保存 payload を都度 `lineItems` から組み立てる方針で修正に着手。

- Step 127 (2026-03-26 12:37 JST)
  - 追加の不整合調査として `Estimates/Create.jsx` の全送信経路と `EstimateController@previewPdf` を照合。
  - PDFプレビューだけは依然として `lineItems` キー前提で payload/view を組んでおり、保存・更新系の `items` 契約と分離していることを確認。
  - 今回の報告では、実害が確認できたものを findings として整理し、特にプレビュー系の stale 明細リスクを優先報告する方針にした。

- Step 128 (2026-03-26 13:02 JST)
  - 見積作成画面の旧「要件整理（内部用）」を廃止し、Google Drive 上の要件定義書を単一ソースとして AIドラフト生成へ刷新する方針を確定。
  - 既存の `google_docs_url` を添付用と AI解析用で兼用し、重複入力をなくす。旧 requirement chat routes/UI は撤去対象とした。
  - 備考生成は未保存見積でも機能するよう、`estimate_id` 依存を外して現在フォームの文脈を直接送る方向で実装に着手。

- Step 129 (2026-03-26 13:28 JST)
  - Google Drive 要件定義書選択 UI、Drive API 取得サービス、要件定義書解析 API、AIドラフト生成 API、備考生成のフォーム文脈反映を実装。
  - 旧 requirement chat routes/UI を撤去し、`google_docs_url` の重複入力を解消。`README_ESTIMATE` / `README_AI_ESTIMATE` / `.env.example` / バージョン表記も同期した。
  - `EstimateController::http()` がテストで `Http::fake()` を通るよう補正し、Drive 解析と備考生成の Feature テストを安定化した。

- Step 130 (2026-03-26 13:49 JST)
  - 「下書き保存後に明細が消える」再報告に対応し、編集モードの下書き保存成功後も新規作成と同様に明示的に `estimates.edit` へ再読込するよう修正した。
  - `EstimateItemAssignmentTest` に既存 draft 更新時の明細保持回帰テストを追加し、保存後に既存明細が空行へ置き換わらないことを backend 側で固定した。

- Step 131 (2026-03-26 13:58 JST)
  - GCP プロジェクト `kcs-portal-b0d21` の有効化状況を確認し、`drive.googleapis.com` と `picker.googleapis.com` が有効なことを確認。
  - この機能専用の Browser API key `KCS Portal Drive Picker Browser Key` を新規作成し、`localhost / 127.0.0.1 / salesdev / sales` の referrer 制限と `drive.googleapis.com / picker.googleapis.com` の API 制限を設定した。
  - local `.env` に `VITE_GOOGLE_DRIVE_API_KEY` を反映。残タスクは Web OAuth client の作成で、これは Cloud Console への本人再認証が必要な状態。

- Step 132 (2026-03-26 14:05 JST)
  - Cloud Console 上の既存 Web OAuth client `440151912207-54tmcfgi98r18di8026sa9g9r4vuoset.apps.googleusercontent.com` を編集し、`http://localhost:8000` `http://127.0.0.1:8000` `https://salesdev.xerographix.co.jp` `https://sales.xerographix.co.jp` を JavaScript origin に追加して保存した。
  - local `.env` に `VITE_GOOGLE_DRIVE_CLIENT_ID` を反映し、Drive Picker に必要な GCP 側の credential 構成を local 用に揃えた。

- Step 133 (2026-03-26 14:14 JST)
  - local の失敗ログを確認し、要件定義書解析失敗の正体が Drive ではなく OpenAI の `429 insufficient_quota` であることを特定。
  - AI失敗時に「解析失敗」だけで潰さず、利用上限超過などの具体的な OpenAI エラーをそのまま画面へ返すよう改善した。
  - Google Picker は共有ドライブの Google Docs も選択できるよう `setEnableDrives(true)` と `SUPPORT_DRIVES` を有効化した。Drive export にも `supportsAllDrives=true` を追加。

- Step 134: OpenAI利用枠回復後のlocal再検証を開始。ログ再確認とDrive/AI導線の実機確認に着手。

- Step 135: Drive Pickerでマイドライブ非表示・共有ドライブ深い階層に辿れない問題を調査開始。Picker設定を点検。
- Step 136: Drive Pickerをマイドライブ/共有ドライブの2ビュー構成に変更。フォルダ表示を有効化し、共有ドライブの深い階層遷移に対応。
- Step 137: 受注済み見積の工数がどの月に反映されるかをコード調査開始。月次集計の判定日付と条件を確認。
- Step 138: 受注確定時の着手日/納品日モーダル、商品再採番の衝突回避、ItemSeederの分類/原価backfillを実装。
- Step 139: local seed商品の単価/原価逆転を確認。ItemSeederのbackfillルールを修正してlocal実データも補正する。
- Step 140: ItemSeederの単価補正ロジックを修正し、localへ再seed適用。price >= cost を確認。
- Step 141: 月跨ぎ案件の工数を着手日〜納期で均等配賦する改修に着手。対象サービスと既存テストを確認。
- Step 142: `EstimateEffortAllocationService` を追加し、ダッシュボード・見積運用サマリー・工数シミュレーションで着手日〜納期の均等配賦を共通化。納期のみ案件は従来どおり納期月一括、ダッシュボードでは納期未設定を未配賦のまま維持。
- Step 143: 月跨ぎ工数の回帰テストを Dashboard / Quotes / Workload Simulation に追加。UI表示バージョンを `v1.0.15` に更新。
- Step 144: 見積編集の「要件定義書から AIドラフト生成（内部用）」アコーディオン見出しを黒背景・白文字に変更。本文の可読性は維持しつつ視認性だけを強め、UI表示バージョンを `v1.0.16` に更新。
- Step 145: 更新履歴と最新更新通知モーダルの実装に着手。履歴データの一元管理、ユーザー既読フラグ、ヘルプ画面と共通レイアウトへの表示方針を整理。
- Step 146: `ReleaseNoteService` と `last_read_release_version` で最新更新の既読判定を実装。共通レイアウトに未読時の確認モーダル、ヘルプ画面に更新履歴一覧を追加し、既読API・migration・Feature test・build を通した。UI表示バージョンを `v1.0.17` に更新。
- Step 147: 最新更新通知を大型アップデート告知へ差し替え。ダッシュボード、見積編集、工数管理、要件定義書からの AI 自動見積の変更点と「ヘルプ熟読必須」を強調し、ヘルプ本文も現仕様に合わせて補強。UI表示バージョンを `v1.0.18` に更新。
- Step 148: 更新通知モーダルの「確認してヘルプを読みます」押下後に、既読保存だけで終わらずヘルプの更新履歴セクションへ遷移するよう修正。モーダル内リンク文言も「ヘルプの更新履歴を見る」に揃えた。
- Step 149: `v1.0.19` として最新更新を再採番し、既読ユーザーにも大型アップデート通知が再表示されるよう調整。サイドメニューに「更新履歴」を追加して、ヘルプ内の更新履歴セクションへ常時アクセスできる導線を追加。
