商品管理／分類マスター 要件定義

1. 背景・目的
	•	分類は Money Forward（MF）と切り離し、本プロジェクト専用マスターとして管理する。
	•	商品に 分類コードベースの商品コード を自動採番して一意性と可読性を両立する。

⸻

2. 対象画面
	•	/products：商品一覧＋分類マスターのDialog管理（CRUD）
	•	/products/*/edit：商品編集（分類選択／商品コードは保存時自動生成）

⸻

3. UI/UX 要件

3.1 分類選択 UI（重要）
	•	表示ラベル：分類名（必要に応じて （コード） を補助表示）
	•	送信値（value）：category_id（数値 or UUID）
	•	サーバ側解決：受け取った category_id から categories.code を取得し、商品コード生成に利用
→ 画面では「テキスト（分類名）を選ぶ」だけで、内部でコード（例：A） が解決される。

3.2 /products（商品一覧・分類Dialog）
	•	一覧上部に任意の 分類フィルタ（Select/Combobox）
	•	右上に 「分類を管理」 ボタン → shadcn/ui Dialog で分類マスターCRUD
	•	Dialog フォーム項目
	•	分類名：text（必須）
	•	分類コード：readOnly/disabled（保存時に自動採番、UIは「自動生成」プレースホルダ）
	•	使用中の分類は 削除不可（適切な警告表示）

3.3 /products/*/edit（商品編集）
	•	フィールド（抜粋）
	•	分類：Select/Combobox（必須、valueは category_id）
	•	商品コード：readOnly/disabled（保存時に自動生成。未発番時は空 or 「自動生成」表示）
	•	分類の新規作成導線：プルダウン横の 「＋分類を追加」（Dialog起動）→ 保存後プルダウンへ即時反映＆選択状態維持

⸻

4. データモデル

4.1 categories（分類）

フィールド	型/制約	説明
id	PK	
name	string, required	分類名
code	string, required, unique, UPPER CASE, 編集不可	英大文字の連番 A, B, …, Z, AA, AB, …
last_item_seq	uint, default 0	当該分類で発番した最新の通し番号
created_at/updated_at	timestamp	

4.2 products（商品：関連）

フィールド	型/制約	説明
id	PK	
category_id	FK → categories.id, required	選択された分類
code	string, unique, nullable（草稿時）	初回保存で確定。以降不変
seq	uint, nullable, unique(category_id, seq)	当該分類内の連番（codeの数値部）

推奨制約
	•	unique(code)
	•	unique(category_id, seq)
	•	FK：ON UPDATE CASCADE, ON DELETE RESTRICT

⸻

5. ビジネスルール

5.1 分類コード（categories.code）
	•	英大文字の連番：A, B, …, Z, AA, AB, …（base-26昇順、A=1）
	•	サーバ保存時に自動採番（ユーザー編集不可・一意）
	•	採番は 既存最大コードを+1 する方式

5.2 商品コード（products.code）
	•	発番タイミング：新規作成 or これまで未発番の商品が初めて保存される時
	•	形式：<分類コード>-<3桁ゼロ埋め>（例：A-001, A-002 …／B-001 …）
	•	例：分類コード A を選択して初回保存 ⇒ A-001
	•	不変性：一度発番された商品コードは以降変更不可
	•	分類変更の扱い
	•	未発番の状態での分類変更：保存時に変更後の分類で発番
	•	発番済みでの分類変更：原則不可（整合性のため）。必要時は別要件（再発番ポリシー）を定義

⸻

6. サーバ処理（同時更新・整合性）

6.1 分類作成（Dialog 保存）
	•	トランザクション開始
	•	code = nextAlphaCode() で自動採番（既存最大コードからインクリメント）
	•	last_item_seq = 0 で作成
	•	コミット

6.2 商品保存（新規／初回発番）
	•	バリデーション：category_id 必須。code は受け取らない（サーバ生成）
	•	トランザクション開始
	1.	category_id 対象のレコードを SELECT ... FOR UPDATE でロック
	2.	last_item_seq += 1
	3.	seq = last_item_seq
	4.	code = categories.code + '-' + LPAD(seq, 3, '0')
	5.	products へ保存（code, seq 確定）
	•	unique 競合時は安全に リトライ（まれな同時保存対策）
	•	コミット

⸻

7. バリデーション／表示
	•	分類名：required|string|max:100
	•	分類コード：UI編集不可（サーバ生成）／DB一意
	•	商品コード：UI編集不可（サーバ生成）
	•	プルダウン：未選択保存不可。大量件数時はCombobox（検索可）

⸻

8. エラー処理・ガード
	•	使用中分類の削除：禁止（関連商品がある場合はエラーを返しDialogで案内）
	•	同時更新：トランザクション＋行ロックで整合性を担保
	•	見せ方：保存成否はトースト（成功／失敗）で通知、必要に応じてDialog自動クローズ

⸻

9. 受け入れ基準（Acceptance Criteria）
	1.	分類マスター
	•	新規作成のたびに A → B → C … と コードが自動採番される
	•	分類コードはUI編集不可／DBで一意
	2.	商品編集
	•	分類（表示は分類名、valueは category_id）を選択して保存すると、商品コードが
A-001（当該分類の初回）→ 次は A-002 … と自動採番される
	•	別分類では B-001 から始まる
	•	既に商品コードがある商品の再編集では商品コードは変わらない
	3.	分類変更
	•	未発番商品の分類変更は許可され、保存時に変更後の分類で発番
	•	発番済み商品の分類変更は原則禁止
	4.	同時更新耐性
	•	同一分類に対する同時保存でも、seq/code の重複が発生しない
	5.	削除ガード
	•	関連商品が存在する分類は削除できない

⸻

10. 使用コンポーネント（shadcn/ui）
	•	Dialog：分類マスターのCRUDモーダル
	•	Select/Combobox：分類選択（表示＝分類名、value＝category_id）

⸻

11. 変更履歴（要点）
	•	「分類コードAを選択」という表現を廃止し、表示は分類名／内部値は category_id に統一
	•	商品コード／分類コードはUI編集不可、保存時の自動採番に一本化