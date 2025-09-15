# Get items

## scope

Authorization Code OAuth Flow

Authorize URL: https://api.biz.moneyforward.com/authorize

Token URL: https://api.biz.moneyforward.com/token

Refresh URL: https://api.biz.moneyforward.com/token

Scopes:

mfc/invoice/data.write - Grant read and write access to all your office's data
mfc/invoice/data.read - Grant read-only access to all your office's data


## サンプルリクエスト
<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/items",
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


## サンプルレスポンス
{
  "data": [
    {
      "id": "t93_uqoFUT_EnX85CJ16XA",
      "name": "Name",
      "code": "Code",
      "detail": "Detail",
      "unit": "Unit",
      "price": "1234.1",
      "quantity": "1",
      "excise": "ten_percent",
      "created_at": "2022-07-14 13:14:03 +0900",
      "updated_at": "2023-01-27 16:19:56 +0900"
    }
  ],
  "pagination": {
    "total_count": 1,
    "total_pages": 1,
    "per_page": 1,
    "current_page": 1
  }
}


## Detail
Get items
get
/api/v3/items
品目一覧の取得

Request
Query Parameters
code
string
Item code, it can be specified multiple value by separating them with a comma.

name
string
Item name, it can be specified multiple value by separating them with a comma.

page
integer
default: 1

per_page
integer
default: 100

Responses
200
OK

Body

application/json

application/json
responses
/
200
data
array[Item]
required
id
string
required
name
string
required
code
string
required
detail
string
unit
string
price
string
quantity
string
is_deduct_withholding_tax
boolean
源泉徴収税額の有り無し:

事業者が法人の時: null
事業者が個人事業主: true or false
excise
string
税率:

untaxable - 不課税
non_taxable - 非課税
tax_exemption - 免税
five_percent - 5%
eight_percent - 8%
eight_percent_as_reduced_tax_rate - 8%(軽減税率)
ten_percent - 10%
Allowed values:
untaxable
non_taxable
tax_exemption
five_percent
eight_percent
eight_percent_as_reduced_tax_rate
ten_percent
created_at
string<date-time>
required
updated_at
string<date-time>
required
pagination
PaginationData
required
total_count
number
required
total_pages
number
required
per_page
number
required
current_page
number
required

# Create new item

## サンプルリクエスト
<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/items",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'name' => 'string',
    'code' => 'string',
    'detail' => 'string',
    'unit' => 'string',
    'price' => 10000000,
    'quantity' => 10000000,
    'is_deduct_withholding_tax' => null,
    'excise' => 'untaxable'
  ]),
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Authorization: Bearer 123",
    "Content-Type: application/json"
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

## サンプルレスポンス
{
  "id": "t93_uqoFUT_EnX85CJ16XA",
  "name": "Name",
  "code": "Code",
  "detail": "Detal",
  "unit": "Unit",
  "price": "1239.1",
  "quantity": "1",
  "excise": "ten_percent",
  "created_at": "2022-07-14 13:14:03 +0900",
  "updated_at": "2023-01-27 16:19:56 +0900"
}

## Detail
Body

application/json

application/json
Request body for creating a Item

name
string
required
>= 1 characters
<= 450 characters
code
string
>= 1 characters
<= 30 characters
detail
string
<= 200 characters
unit
string
<= 20 characters
price
number
>= -10_000_000_000
<= 10_000_000_000
quantity
number
>= -10_000_000_000
<= 10_000_000_000
is_deduct_withholding_tax
boolean
源泉徴収税額の有り無し:

事業者が法人の時: null
事業者が個人事業主: true or false
excise
string
required
税率:

untaxable - 不課税
non_taxable - 非課税
tax_exemption - 免税
five_percent - 5%
eight_percent - 8%
eight_percent_as_reduced_tax_rate - 8%(軽減税率)
ten_percent - 10%
Allowed values:
untaxable
non_taxable
tax_exemption
five_percent
eight_percent
eight_percent_as_reduced_tax_rate
ten_percent
Responses
201
400
Created

Body

application/json

application/json
id
string
required
name
string
required
code
string
required
detail
string
unit
string
price
string
quantity
string
is_deduct_withholding_tax
boolean
源泉徴収税額の有り無し:

事業者が法人の時: null
事業者が個人事業主: true or false
excise
string
税率:

untaxable - 不課税
non_taxable - 非課税
tax_exemption - 免税
five_percent - 5%
eight_percent - 8%
eight_percent_as_reduced_tax_rate - 8%(軽減税率)
ten_percent - 10%
Allowed values:
untaxable
non_taxable
tax_exemption
five_percent
eight_percent
eight_percent_as_reduced_tax_rate
ten_percent
created_at
string<date-time>
required
updated_at
string<date-time>
required


# Update an item

## サンプルリクエスト
<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/items/{item_id}",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "PUT",
  CURLOPT_POSTFIELDS => json_encode([
    'name' => 'string',
    'code' => 'string',
    'detail' => 'string',
    'unit' => 'string',
    'price' => 10000000,
    'quantity' => 10000000,
    'is_deduct_withholding_tax' => null,
    'excise' => 'untaxable'
  ]),
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Authorization: Bearer 123",
    "Content-Type: application/json"
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

## サンプルレスポンス
{
  "id": "t93_uqoFUT_EnX85CJ16XA",
  "name": "Name",
  "code": "Code",
  "detail": "Detal",
  "unit": "Unit",
  "price": "1239.1",
  "quantity": "1",
  "excise": "ten_percent",
  "created_at": "2022-07-14 13:14:03 +0900",
  "updated_at": "2023-01-27 16:19:56 +0900"
}

## Detail
Path Parameters
item_id
string
required
Body

application/json

application/json
Request body for updating a Item

name
string
>= 1 characters
<= 450 characters
code
string
>= 1 characters
<= 30 characters
detail
string
<= 200 characters
unit
string
<= 20 characters
price
number
>= -10_000_000_000
<= 10_000_000_000
quantity
number
>= -10_000_000_000
<= 10_000_000_000
is_deduct_withholding_tax
boolean
源泉徴収税額の有り無し:

事業者が法人の時: null
事業者が個人事業主: true or false
excise
string
税率:

untaxable - 不課税
non_taxable - 非課税
tax_exemption - 免税
five_percent - 5%
eight_percent - 8%
eight_percent_as_reduced_tax_rate - 8%(軽減税率)
ten_percent - 10%
Allowed values:
untaxable
non_taxable
tax_exemption
five_percent
eight_percent
eight_percent_as_reduced_tax_rate
ten_percent
Responses
200
400
OK

Body

application/json

application/json
id
string
required
name
string
required
code
string
required
detail
string
unit
string
price
string
quantity
string
is_deduct_withholding_tax
boolean
源泉徴収税額の有り無し:

事業者が法人の時: null
事業者が個人事業主: true or false
excise
string
税率:

untaxable - 不課税
non_taxable - 非課税
tax_exemption - 免税
five_percent - 5%
eight_percent - 8%
eight_percent_as_reduced_tax_rate - 8%(軽減税率)
ten_percent - 10%
Allowed values:
untaxable
non_taxable
tax_exemption
five_percent
eight_percent
eight_percent_as_reduced_tax_rate
ten_percent
created_at
string<date-time>
required
updated_at
string<date-time>
required

# 要件定義・UI仕様

📘 UI要件指示書（商品管理画面）

1. 画面レイアウト
	•	メイン画面構成
	•	商品一覧（テーブル形式）
	•	検索・フィルタ（商品名・コード・税区分）
	•	新規登録ボタン
	•	編集・削除ボタン
	•	「MF同期」ボタン（全体同期／個別同期）

2. 一覧表示項目

項目	必須	備考
商品コード	○	MFのcodeと対応
商品名	○	MFのnameと対応
詳細	△	任意入力（説明文）
単位	△	「個」「kg」など
単価	○	price
標準数量	△	quantity
税区分	○	excise
源泉徴収有無	△	is_deduct_withholding_tax
最終更新日時	○	自社DB基準／MF API基準

3. 機能UI
	•	検索
	•	商品名・コードで部分一致検索
	•	税区分でフィルタリング
	•	CRUD操作
	•	新規登録：モーダルフォーム
	•	編集：行右端の編集ボタン
	•	削除：確認ダイアログ付き
	•	同期操作
	•	全体同期：MF APIから最新データ取得 → 自社DB更新
	•	個別同期：行単位でMFへPUT/POST
	•	同期状況はステータスラベルで表示（例：同期済み / 未同期 / エラー）

⸻

📗 機能要件指示書（API連携）

1. 対象API
	•	一覧取得：GET /api/v3/items
	•	新規登録：POST /api/v3/items
	•	更新：PUT /api/v3/items/{item_id}

2. データ同期方針
	•	自社システム → MF
	•	新規商品追加 → POST
	•	既存商品の修正 → PUT
	•	自社DBが正の場合、MFを更新
	•	MF → 自社システム
	•	定期バッチ or 手動同期で GET 実行
	•	MFに存在して自社に無い商品 → 自社側へ新規登録
	•	双方向同期ルールは「更新日時」で判定

3. バリデーション要件
	•	商品名（name）：1～450文字 必須
	•	商品コード（code）：1～30文字、重複不可
	•	詳細（detail）：最大200文字
	•	単位（unit）：最大20文字
	•	単価（price）：-10,000,000,000 ～ 10,000,000,000
	•	数量（quantity）：-10,000,000,000 ～ 10,000,000,000
	•	税区分（excise）：必須、選択肢固定（ten_percentなど）
	•	源泉徴収（is_deduct_withholding_tax）：
	•	法人 → null
	•	個人事業主 → true / false

4. エラーハンドリング
	•	400エラー：入力不備 → UIにバリデーションメッセージ表示
	•	401エラー：認証エラー → アクセストークン更新処理
	•	429エラー：API制限 → リトライ処理 & ユーザー通知
	•	500系：サーバエラー → ログ保存・ユーザーへリトライ案内

5. 同期トリガー
	•	手動同期：ユーザーが「MF同期」ボタン押下時
	•	保存時同期：商品登録/更新時に即座にAPI連携（オプション）

6. ログ管理
	•	同期ログテーブルを自社DBに保持
	•	項目：商品ID、同期方向、自社更新日時、MF更新日時、結果、エラーメッセージ
	•	UIから参照可能にする（管理者向け）
