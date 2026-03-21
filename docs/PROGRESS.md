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
