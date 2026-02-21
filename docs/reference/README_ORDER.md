AI作業ルール / AI Working Rules

本リポジトリにおける AI／開発者の作業原則を定義する。
Defines the operating principles for AI and developers in this repository.

✅ 基本ルール / Core Rules
	•	１ステップずつ進める
常に小さな単位（1コミット/1PR）で変更し、影響範囲を限定する。
Always proceed in small increments (one commit/PR) to limit blast radius.
	•	推測・仮定で進めない
仕様が曖昧な場合は作業を進めず、確認を取る。
Do not proceed on assumptions; pause work and seek confirmation for ambiguities.
	•	不明点は質問するまたは調査する
公式ドキュメントや既存コードを優先して調査し、必要なら質問する。
Investigate via official docs and codebase first; ask questions if still unclear.
	•	PROGRESS.mdに進捗を毎回記録する
変更内容・理由・次アクションを短く残す（時系列で追えること）。
Log changes, rationale, and next actions each time in chronological order.
	•	画面毎にUI仕様設計 README_UI_"画面名".md を作り常にアップデートする
画面の目的・フロー・状態遷移・バリデーション・i18n・アクセシビリティを記録/更新する。
Maintain per-screen specs with purpose, flow, states, validation, i18n, and accessibility.

⸻

🔁 作業フロー / Workflow
	1.	要求の受領 → 要件確認
タスクの目的・入出力・完了条件(DoD)を明文化。
Receive request → Clarify requirements: Define purpose, I/O, and Definition of Done.
	2.	最小変更の提案
影響範囲・代替案・リスクを1段落で提示。
Propose minimal change with scope, alternatives, and risks briefly.
	3.	実装 → ローカル検証
ユニット/スモークテストを実行、スクショ/ログを保存。
Implement → Verify locally with unit/smoke tests; save screenshots/logs.
	4.	PROGRESS.md 更新
要約・変更点・次アクション・未解決事項を追記。
Update PROGRESS.md with summary, changes, next steps, open issues.
	5.	PR 作成
PR テンプレに目的/変更/確認観点/影響/スクショを記載。
Open PR including purpose, changes, test points, impact, and screenshots.

⸻

❓ 質問・調査のトリガー / When to Ask or Research
	•	仕様の矛盾・不足を検知したとき
When specification is conflicting or incomplete.
	•	既存実装と要件が乖離しているとき
When existing code diverges from stated requirements.
	•	セキュリティ/可用性に影響し得る変更のとき
When changes may affect security or availability.

⸻

🧾 PROGRESS.md 記入テンプレ / Logging Template

## 2025-09-16
### 要約
ログイン後ボタン押下→期待画面表示の判定ロジックを追加。  
Added post-login button click → expected screen assertion logic.

### 変更点
- Playwright: `checks/login-flow.spec.ts` を追加  
- Firestore: `results` コレクションに `screenshotUrl` を保存  
- UI: 結果一覧に失敗ハイライトを追加  
- Playwright: Added `checks/login-flow.spec.ts`  
- Firestore: Store `screenshotUrl` in `results`  
- UI: Highlight failures in Result List

### 検証
ローカルで 5 ケース成功・1 ケース失敗、スクショ確認済み。  
Verified 5 pass / 1 fail locally with screenshots.

### 次アクション
- 失敗時のGoogle Chatカード通知にスクショURLを添付  
- Attach screenshot URL to Google Chat card on failure.

### 未解決
- ログイン多要素対応の方針未確定  
- Policy for MFA handling remains undecided.


⸻

🗂 UI仕様ファイル命名とテンプレ / UI Spec Naming & Template
	•	命名 / Naming: README_UI_<画面名>.md（例: README_UI_Login.md）
Use README_UI_<Screen>.md (e.g., README_UI_Login.md).

# <画面名> / <Screen Name>

## 目的 / Purpose
この画面のゴールを1～2文で記述。  
State the goal of this screen in 1–2 sentences.

## エントリ条件 / Entry Conditions
遷移元・認証要件・前提データ。  
Source pages, auth, and prerequisites.

## 主要フロー / Primary Flow
1) ユーザー操作 → 2) システム応答 → 3) 成功条件  
1) User action → 2) System response → 3) Success criteria

## 状態・例外 / States & Edge Cases
ローディング/空/エラー/権限不足など。  
Loading/empty/error/permission cases.

## 入力/検証 / Inputs & Validation
必須・型・長さ・エラーメッセージ。  
Required, types, lengths, error messages.

## コンポーネント / Components (shadcn/ui)
使用コンポーネントとバリアント。  
List used components and variants.

## セレクタ / Test Selectors
`data-testid` 一覧（E2Eの安定性のため）  
List `data-testid`s for stable E2E.

## i18n / アクセシビリティ
対応言語、キーボード操作、ARIA属性。  
Locales, keyboard support, ARIA.

## 計測 / Telemetry
イベント名・ペイロード・送信タイミング。  
Event names, payloads, timing.

## スクリーンショット
最新UIの画像リンク。  
Link to latest UI screenshots.


⸻

✅ Definition of Done（完了条件） / DoD
	•	要件が満たされ、自動・手動の両テストが通っている
Requirements met with both automated and manual tests passing.
	•	README_UI_*.md と PROGRESS.md が更新されている
README_UI_*.md and PROGRESS.md are updated.
	•	失敗シナリオの再現手順と期待挙動が明記されている
Failure reproduction steps and expected behavior are documented.
	•	セキュリティ/性能に与える影響が記載されている
Security/performance impact is noted.

⸻

🚫 禁止事項 / Prohibited
	•	推測・仮定でコード/仕様を変更
Changing code/spec on assumptions.
	•	大量の無関係変更を単一PRに混在
Bundling unrelated large changes into one PR.
	•	PROGRESS.md・UI仕様の未更新のままマージ
Merging without updating PROGRESS or UI specs.