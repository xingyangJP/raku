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

2. フロー比較

🟢 1回目（初回認証時）
	1.	ユーザーが「MFから同期」ボタンをクリック
	2.	自社システム → authorize にリダイレクト（ログイン + アクセス許可）
	3.	MF認可サーバ → authorization_code を redirect_uri に返す
	4.	自社システム → token エンドポイントに authorization_code を送信
	5.	MFトークンサーバ → access_token + refresh_token を返却
	6.	自社システムは mf_tokens テーブルに保存
	7.	以降のAPI呼び出しに access_token を利用

👉 初回だけユーザーがログイン/承認操作を行う

⸻

🔵 2回目以降
	1.	ユーザーが「MFから同期」ボタンをクリック
	2.	自社システムが DB の mf_tokens をチェック
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
