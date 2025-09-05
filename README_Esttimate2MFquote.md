# 📄 見積書画面 UI要件定義書（マネーフォワード見積書発行連携）

## ✅ 概要

自社販売管理システムにおいて、承認済みの見積書からマネーフォワードクラウド請求書APIを用いて、**ワンクリックで見積書を発行**する機能を提供する。

さらに、見積書が発行済みでかつ請求書が未発行の場合には、「**請求書に変換**」ボタンを表示することで、後続処理に接続可能とする。

---

## 🧩 システム構成と前提条件

| 項目 | 内容 |
|------|------|
| 対象画面 | 見積書詳細画面（`/estimates/:id`） |
| 対象見積ステータス | `承認済み` |
| 発行API | `POST /api/v3/quotes`（Money Forward 請求書API） |
| 認証方式 | OAuth2 Authorization Code |
| トークン設定 | `.env` に `TOKEN` を保持済み（別画面請求書連携でも共通使用） |
| コールバックURL | `.env` に1つ設定済み（請求書取得機能で利用中、共通利用） |
| スコープ | `mfc/invoice/data.write` |
| 顧客ID対応 | 自社の `customer.id` ⇄ `mf_partner_id` にてマッピング済み |
| 見積商品明細 | 自社DBに準拠し、API仕様に合わせて変換必要（税区分excise指定必須） |

---

## 🖥️ ボタン表示条件

### ① マネーフォワードで見積書発行

| 条件 | 説明 |
|------|------|
| ステータス | `承認済み` |
| 顧客に `mf_partner_id` がある |
| 見積に `mf_quote_id` が未設定 |

```jsx
{estimate.status === '承認済み' &&
 estimate.customer?.mf_partner_id &&
 !estimate.mf_quote_id && (
  <Button onClick={handleIssueMFQuote}>
    マネーフォワードで見積書発行
  </Button>
)}


⸻

② 請求書に変換

条件	説明
mf_quote_id が存在（＝発行済）	
mf_invoice_id が未設定（＝未請求）	

{estimate.mf_quote_id && !estimate.mf_invoice_id && (
  <Button onClick={handleConvertToInvoice}>
    請求書に変換
  </Button>
)}


⸻

📤 発行処理フロー
	1.	ユーザーが「見積書発行」ボタンをクリック
	2.	モーダル確認：「この見積からマネーフォワードで見積書を発行しますか？」
	3.	OAuthトークンを使用して、/api/v3/quotes にPOST
	4.	レスポンスの id および pdf_url を保存
	5.	UI更新 → 「請求書に変換」ボタン表示

⸻

📦 APIリクエスト仕様（POST /api/v3/quotes）

{
  "department_id": "XXX",
  "partner_id": "XXX",
  "quote_number": "EST-2025-009",
  "title": "システム開発2025年9月",
  "memo": "メモ内容",
  "quote_date": "2025-09-05",
  "expired_date": "2025-10-05",
  "note": "納期：発注後2週間以内",
  "document_name": "見積書",
  "items": [
    {
      "name": "顧問契約",
      "detail": "顧問料2025年9月",
      "unit": "式",
      "price": 100000,
      "quantity": 1,
      "excise": "ten_percent"
    }
  ]
}


⸻

✅ レスポンス処理とDB更新

{
  "id": "OfEG-jR-EH4gZoBfDcz1xg",
  "pdf_url": "https://invoice.moneyforward.com/api/v3/quotes/OfEG-jR-EH4gZoBfDcz1xg.pdf"
}

保存対象カラム	内容
mf_quote_id	発行されたID（レスポンスの id）
mf_quote_pdf_url	PDF URL（レスポンスの pdf_url）


⸻

🧾 推奨DB構成

カラム名	型	説明
mf_quote_id	string	MF見積書ID（重複発行防止）
mf_quote_pdf_url	string	MF見積PDF URL
mf_invoice_id	string	MF請求書ID（将来的に使用）


⸻

✅ テストシナリオ表

ステータス	mf_partner_id	mf_quote_id	mf_invoice_id	表示ボタン
承認済み	あり	未設定	未設定	見積書発行
承認済み	あり	設定済	未設定	請求書に変換
承認済み	あり	設定済	設定済	なし
承認済み	なし	未設定	未設定	なし
未承認	*	*	*	表示なし


⸻

⚠️ 注意事項

項目	内容
OAuthトークン・コールバックURLは .env に既に設定済み（請求書機能と共有）	
mf_quote_id による重複発行防止あり	
APIは同期で pdf_url を返す（ポーリング不要）	
partner_id が未設定の場合、APIリクエストはエラーとなるため事前に整備が必要	


⸻

🔮 今後の拡張案
	•	見積書送信状態（transmit_status）や受注状態（order_status）の取得・表示
	•	「見積→請求」自動変換ワークフロー構築
	•	MF側の見積一覧との双方向同期




Create new quote
post
/api/v3/quotes
見積書の作成

Request
Authorization Code OAuth Flow

Authorize URL: https://api.biz.moneyforward.com/authorize

Token URL: https://api.biz.moneyforward.com/token

Refresh URL: https://api.biz.moneyforward.com/token

Scopes:

mfc/invoice/data.write - Grant read and write access to all your office's data
Body

application/json

application/json
Request body for creating a quote

department_id
string
required
quote_number
string
<= 30 characters
title
string
<= 200 characters
memo
string
<= 450 characters
quote_date
string<date>
required
Example:
2022-12-09
expired_date
string<date>
required
Example:
2022-12-10
note
string
<= 2000 characters
tag_names
array[string]
document_name
string
<= 25 characters
items
array[object]
item_idを指定しない場合、exciseは必須となります。

item_id
string
name
string
item_idを指定した場合は、こちらのnameを指定しても、item_idに紐づいたマスタitemのnameで登録します。

>= 1 characters
<= 450 characters
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
responses
/
201
id
string
required
pdf_url
string
required
operator_id
string
required
department_id
string
required
member_id
string
required
member_name
string
required
partner_id
string
required
partner_name
string
required
partner_detail
string
office_id
string
office_name
string
required
office_detail
string
required
title
string
required
memo
string
quote_date
string<date>
required
Example:
2023/08/24
quote_number
string
note
string
expired_date
string<date>
required
Example:
2023/08/24
document_name
string
order_status
string
受注ステータス:

failure - 失注
default - 未設定
not_received - 未受注
received - 受注済み
Allowed values:
failure
default
not_received
received
transmit_status
string
メールステータス:

default - 未設定
sent - 送付済み
already_read - 受領済み
received - 受信
Allowed values:
default
sent
already_read
received
posting_status
string
郵送ステータス:

default - 未設定
request - 郵送依頼
sent - 郵送済み
cancel - 郵送取消
error - 郵送失敗
Allowed values:
default
request
sent
cancel
error
created_at
string<date-time>
required
updated_at
string<date-time>
is_downloaded
boolean
is_locked
boolean
deduct_price
string
Only return if my office type is individual

tag_names
array[string]
items
array[Item]
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
excise_price
string
required
excise_price_of_untaxable
string
excise_price_of_non_taxable
string
excise_price_of_tax_exemption
string
excise_price_of_five_percent
string
excise_price_of_eight_percent
string
excise_price_of_eight_percent_as_reduced_tax_rate
string
excise_price_of_ten_percent
string
subtotal_price
string
required
subtotal_of_untaxable_excise
string
subtotal_of_non_taxable_excise
string
subtotal_of_tax_exemption_excise
string
subtotal_of_five_percent_excise
string
subtotal_of_eight_percent_excise
string
subtotal_of_eight_percent_as_reduced_tax_rate_excise
string
subtotal_of_ten_percent_excise
string
total_price
string
required


Convert the quote to billing
post
/api/v3/quotes/{quote_id}/convert_to_billing
見積書を請求書に変換

Request
Authorization Code OAuth Flow

Authorize URL: https://api.biz.moneyforward.com/authorize

Token URL: https://api.biz.moneyforward.com/token

Refresh URL: https://api.biz.moneyforward.com/token

Scopes:

mfc/invoice/data.write - Grant read and write access to all your office's data
Path Parameters
quote_id
string
required
Responses
201
404
Created

Body

application/json

application/json
responses
/
201
/
config
.
consumption_tax_display_type
id
string
required
pdf_url
string
required
operator_id
string
required
department_id
string
required
member_id
string
required
member_name
string
required
partner_id
string
required
partner_name
string
required
office_id
string
office_name
string
required
office_detail
string
required
title
string
required
memo
string
payment_condition
string
billing_date
string<date>
required
Example:
2023/08/24
due_date
string<date>
required
Example:
2023/08/24
sales_date
string<date>
Example:
2023/08/24
billing_number
string
note
string
document_name
string
payment_status
string
入金ステータス:

0 - 未設定
1 - 未入金
2 - 入金済み
3 - 未払い
4 - 振込済み
Allowed values:
未設定
未入金
入金済み
未払い
振込済み
email_status
string
メールステータス:

null - 未送信
sent - 送付済み
already_read - 受領済み
received - 受信
Allowed values:
未送信
送付済み
受領済み
受信
posting_status
string
郵送ステータス:

null - 未郵送
request - 郵送依頼
sent - 郵送済み
cancel - 郵送取消
error - 郵送失敗
Allowed values:
未郵送
郵送依頼
郵送済み
郵送取消
郵送失敗
created_at
string<date-time>
required
updated_at
string<date-time>
is_downloaded
boolean
is_locked
boolean
deduct_price
string
Only return if my office type is individual

tag_names
array[string]
items
array[BillingItem]
請求書の品目

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
delivery_number
string
delivery_date
string<date>
Example:
2023/08/24
excise_price
string
required
excise_price_of_untaxable
string
excise_price_of_non_taxable
string
excise_price_of_tax_exemption
string
excise_price_of_five_percent
string
excise_price_of_eight_percent
string
excise_price_of_eight_percent_as_reduced_tax_rate
string
excise_price_of_ten_percent
string
subtotal_price
string
required
subtotal_of_untaxable_excise
string
subtotal_of_non_taxable_excise
string
subtotal_of_tax_exemption_excise
string
subtotal_of_five_percent_excise
string
subtotal_of_eight_percent_excise
string
subtotal_of_eight_percent_as_reduced_tax_rate_excise
string
subtotal_of_ten_percent_excise
string
subtotal_with_tax_of_untaxable_excise
string
subtotal_with_tax_of_non_taxable_excise
string
subtotal_with_tax_of_tax_exemption_excise
string
subtotal_with_tax_of_five_percent_excise
string
subtotal_with_tax_of_eight_percent_excise
string
subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise
string
subtotal_with_tax_of_ten_percent_excise
string
total_price
string
required
registration_code
string
use_invoice_template
boolean
required
config
BillingConfig
請求書の詳細設定

rounding
string
required
明細行ごとの端数処理:

round_down - 切り捨て
round_up - 切り上げ
round_off - 四捨五入
Allowed values:
round_down
round_up
round_off
rounding_consumption_tax
string
required
消費税の端数処理:

round_down - 切り捨て
round_up - 切り上げ
round_off - 四捨五入
Allowed values:
round_down
round_up
round_off
consumption_tax_display_type
string
required
消費税の表示方式:

internal - 内税
external - 外税
Allowed values:
internal
external