# SCOPE

Authorization Code OAuth Flow

Authorize URL: https://api.biz.moneyforward.com/authorize

Token URL: https://api.biz.moneyforward.com/token

Refresh URL: https://api.biz.moneyforward.com/token

Scopes:

mfc/invoice/data.write - Grant read and write access to all your office's data
mfc/invoice/data.read - Grant read-only access to all your office's data


📘 Money Forward OAuth2.0 認可コードフロー仕様書（拡張版）

1. 認可サーバー情報
	•	Authorize URL: https://api.biz.moneyforward.com/authorize
	•	Token URL: https://api.biz.moneyforward.com/token
	•	Refresh URL: https://api.biz.moneyforward.com/token
	•	利用スコープ
	•	mfc/invoice/data.read : データの読み取り
	•	mfc/invoice/data.write : データの読み書き

⸻

2. フロー比較（実装反映）

🟢 1回目（初回認証時）
	1.	ユーザーが同期処理を開始（例: 画面表示時の自動同期、もしくは「MFへ同期」ボタン押下）
	2.	自社システム → authorize にリダイレクト（ログイン + アクセス許可）
	3.	MF認可サーバ → authorization_code を redirect_uri に返す
4.	自社システム → token エンドポイントに authorization_code を送信（コード実装: MoneyForwardApiService::getAccessTokenFromCode）
5.	MFトークンサーバ → access_token + refresh_token を返却
6.	自社システムは mf_tokens テーブルに保存（コード実装: MoneyForwardApiService::storeToken）
	7.	以降のAPI呼び出しに access_token を利用

👉 初回だけユーザーがログイン/承認操作を行う

⸻

🔵 2回目以降
	1.	ユーザーが同期処理を開始（自動同期／手動同期）
2.	自社システムが DB の mf_tokens をチェック（コード実装: MoneyForwardApiService::getValidAccessToken）
	•	expires_at が有効 → そのまま access_token を使用
	•	期限切れ → refresh_token を使って新しい access_token を取得し DB更新
	3.	Authorization: Bearer {access_token} で API 呼び出し

👉 この場合は ユーザーがMF認証画面にリダイレクトされない
👉 ただし、refresh_token が無効化された場合のみ再度初回フローに戻る

⸻

3. シーケンス図

sequenceDiagram
    participant User as ユーザー
    participant App as 自社システム
    participant MF as MF認可サーバー

    rect rgb(200,255,200)
    Note over User,MF: 1回目のフロー
    User->>App: 「MF同期」クリック
    App->>MF: /authorize
    User->>MF: ログイン & 承認
    MF->>App: redirect_uri + code
    App->>MF: /token (authorization_code)
    MF->>App: access_token + refresh_token
    App->>DB: 保存
    App->>MF: API呼び出し
    end

    rect rgb(200,200,255)
    Note over User,MF: 2回目以降のフロー
    User->>App: 「MF同期」クリック
    App->>DB: トークン確認
    App->>MF: /token (refresh_token) ※必要時のみ
    MF->>App: 新しい access_token
    App->>MF: API呼び出し
    end


⸻

4. DB保存モデル例

CREATE TABLE mf_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  access_token TEXT NOT NULL,
  refresh_token TEXT NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  scope VARCHAR(255),
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY (user_id)
);


⸻

5. ポイント
	•	1回目フロー: 認証画面が必須、ここで refresh_token を確保
	•	2回目以降: refresh_token でアクセストークンを更新すればリダイレクト不要
	•	refresh_token が失効したときのみ 再度 1回目フローに戻る

⸻

👉 これで「1回目だけ認証画面 → 以降は自動で同期」が可能になります。

⸻

6. 請求書（Local → MF）実装ポイントとUX

- 画面: `/invoices/{id}/edit`（ローカル請求書編集）
  - 「MFで請求書を作成する」ボタン: OAuth 認可 → `invoices.send.callback` でトークン交換 → API `/invoice_template_billings` で作成 → 成功時に `local_invoices.mf_billing_id`（+ `mf_pdf_url`）を保存し同画面へリダイレクト。
  - 判定: `mf_billing_id` の有無でUIを分岐。
    - あり: 「MFで請求書を編集する」（MFの編集URLを別タブ）/「PDFを確認」（OAuth経由でPDF表示）
    - なし: 「MFで請求書を作成する」

- 一覧: `/billing`（MF請求+ローカル請求の統合表示）
  - 「請求書番号」のリンク挙動（404対策・UX最適化）
    - ローカル請求: `route('invoices.edit', { invoice: local_invoice_id })`
    - MF請求: `https://invoice.moneyforward.com/billings/{mf_billing_id}/edit` を新規タブで開く
  - クイックアクション
    - ローカル: `MF未生成`/`PDFを確認`/`MFで編集`（mf_billing_id 有無で分岐）
    - MF: `編集`（MFサイト）、`詳細`（DL済PDFがあれば自アプリから配信）

- コールバック/リダイレクトURI
  - 送信（作成）: `MONEY_FORWARD_INVOICE_REDIRECT_URI`（例: `http://localhost:8000/invoices/send/callback`）
  - PDF閲覧: 送信と同じリダイレクトURIを再利用（登録URIの追加不要）。サーバ側はコールバックで `local_invoice_id_for_pdf` の有無によりPDF閲覧フローへ分岐。

- トークン運用
  - 初回のみ認可画面 → `mf_tokens` に保存
  - 2回目以降は `refresh_token` 更新で自動実行（ユーザー無操作）

- エラー時ガイド
  - `department_id` が無効な場合はMF取引先詳細から部門一覧を取得して補正（最初の部門に置換）
  - APIエラーはレスポンスをログに記録。画面にはわかりやすいメッセージを表示
