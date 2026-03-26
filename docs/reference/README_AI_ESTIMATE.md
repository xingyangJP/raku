# AI見積支援（要件定義書起点）の仕様

## 目的
- Google Drive 上の要件定義書を単一ソースとして扱い、添付要件と AI 解析元を分けない。
- OpenAI を用いて、第5種事業向けのドラフト明細と対外備考案を自動生成する。
- 営業が同じ内容を「要件整理」「要件定義書 URL」「AIプロンプト」に重複入力しない UX にする。

## 入力 UI
1. **要件定義書の選択**
   - `estimate.google_docs_url` を Google Drive Picker から選択して保持する。
   - Picker 未設定環境では同じフィールドに URL 手入力できるが、AI 解析は Drive から選択してアクセストークンを取得した文書を前提とする。
2. **AI 抽出結果**
   - 要件定義書本文から `estimate.requirement_summary` を自動生成する。
   - 同時に `functional_requirements` / `non_functional_requirements` / `unresolved_requirements` を抽出して画面表示する。
   - 備考生成に使える `notes_prompt` の初期候補も同時に生成する。
3. **PM 必要チェック**
   - チェックボックス「PM支援が必要」。ON の場合は PM 系品目を必須挿入する。
4. **AI ドラフト生成**
   - 「AIで要件定義書からドラフト見積生成」を押すと、文書解析とドラフト明細生成を連続実行する。
   - 結果はモーダルで確認し、「すべて置換」「末尾に追加」を選べる。

## 明細生成ルール
- 対象は第5種事業のみ。第1種事業（ハードウェア、仕入など）は候補に含めない。
- 品目は `products` テーブルの第5種商品から選ぶ。
- 数量は人日単位で生成し、0.5 人日刻みへ正規化する。
- 単価と原価は商品マスタの値をそのまま使う。

## 備考生成
- AI ドラフト生成時に対外備考案も同時に返す。
- 別途「備考生成」ボタンを押した場合も、未保存見積を含む現在フォームの顧客名・案件名・明細・要件要約・要件定義書 URL を backend へ渡し、OpenAI で再生成する。
- 備考生成プロンプトは AI 抽出時に自動下書きされ、必要なら営業が調整して再生成できる。

## Google 連携
- 文書選択 UI は Google Picker を使う。
- 文書本文取得は Google Drive API を使う。
- 現行実装では Google ドキュメントとテキスト系ファイルを主対象とし、Google ドキュメントは `files.export(text/plain)` で本文取得する。

## 注意点
- 設計/開発明細が含まれる場合、`google_docs_url` は必須。
- Drive Picker 用に `VITE_GOOGLE_DRIVE_CLIENT_ID` と `VITE_GOOGLE_DRIVE_API_KEY` が必要。
- 旧「要件整理チャット」は廃止し、新規入力導線としては使わない。
