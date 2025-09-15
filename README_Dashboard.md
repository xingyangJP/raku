
# .envの内容
MONEY_FORWARD_CLIENT_ID=164430176903963
MONEY_FORWARD_CLIENT_SECRET=5PyCb1J1SgyCY2n3WHgBMO_5b56NaGFtk9e7hZTw_xzPAbXF1ZJEEVktgNXXSDe9gbnlJKG7S4F-7KN0ujZQmw
# 請求書取得機能用
MONEY_FORWARD_BILLING_REDIRECT_URI=http://localhost:8000/mf/billing/callback
# 取引先同期機能用
MONEY_FORWARD_PARTNER_REDIRECT_URI=http://localhost:8000/mf/partners/callback

# ダッシュボード画面：顧客取得ボタン UI仕様

目的
	•	ダッシュボード上で「取引先取得」アクションを実行し、MF（マネーフォワード）側から取得した顧客データをすべてDBに保存する。

⸻

条件・初期表示
	•	“取引先取得” ボタンは、認証済み状態または初回のみ表示。
	•	トークン未取得・期限切れの場合も表示し、認証フローへ誘導。

<Button onClick={handleFetchPartners}>取引先取得</Button>


⸻

UIフロー（ステップ別）

[ ダッシュボード (/dashboard) ]
    │ ユーザーが「取引先取得」ボタンをクリック
    ▼
[ MF 認証画面へリダイレクト（OAuth 認可コードフロー） ]
    │ ユーザーがログイン・アクセス許可を承認
    ▼
[ MF 認可サーバから自社コールバックに `authorization_code` 付きでリダイレクト ]
    │ 自社サーバが `authorization_code` を使用しアクセストークン取得
    ▼
[ アクセストークン取得完了 → 顧客一覧 API 呼び出し （GET /api/v3/partners） ]
    ▼
[ 成功時：返却された全顧客情報を DB に完全保存 ]
    ▼
[ Dashboard に戻り「顧客取得済」表示・UI更新 ]


⸻

実装ポイント

ステップ	内容
認証フロー	OAuth 2.0 Authorization Code Flow を実装し、MF 認可画面によるログインとアクセス承認を必須化  ￼ ￼
API 呼び出し	GET /api/v3/partners による取得。必要に応じて検索クエリ追加可能（例：name、code など） ￼
DB 保存	取得した顧客情報の全フィールドを、該当する partners テーブルなどに逐次保存
UI 表示	保存完了後は「顧客取得済み」ステータスや取得件数などを表示し、ユーザーに成功を通知


# サンプルリクエスト
<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/partners",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Authorization: Bearer 123"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}

# サンプルレスポンス
{
  "data": [
    {
      "id": "95PHKI9_FeSw3coTj673Cg",
      "code": "p41uz1dyvw3cj71qrkja",
      "name": "Ryu p41uz1dyvw3cj71qrkja",
      "name_kana": "p41uz1dyvw3cj71qrkja",
      "name_suffix": "御中",
      "memo": "p41uz1dyvw3cj71qrkja",
      "created_at": "2023-03-20 13:39:28 +0900",
      "updated_at": "2023-03-20 13:39:28 +0900",
      "departments": [
        {
          "id": "qwc4iT7ZrywxipJCOqtZQg",
          "zip": "123-4567",
          "tel": "1234567",
          "prefecture": "山形県",
          "address1": "hb3m8kaxz9eex1czmpn2",
          "address2": "hb3m8kaxz9eex1czmpn2",
          "person_name": "hb3m8kaxz9eex1czmpn2",
          "person_title": "hb3m8kaxz9eex1czmpn2",
          "person_dept": "hb3m8kaxz9eex1czmpn2",
          "email": "hb3m8kaxz9eex1czmpn2@moneyforward.com",
          "cc_emails": "hb3m8kaxz9eex1czmpn2@moneyforward.com",
          "peppol_id": "0088:0000000000001",
          "office_member_name": "hb3m8kaxz9eex1czmpn2",
          "office_member_id": "-UNhHGbLKnWH5xlrFhj2ow",
          "created_at": "2023-03-20 13:44:52 +0900",
          "updated_at": "2023-03-20 13:44:52 +0900"
        }
      ]
    }
  ],
  "pagination": {
    "total_count": 3,
    "total_pages": 3,
    "per_page": 1,
    "current_page": 3
  }
}