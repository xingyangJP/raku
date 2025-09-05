# 見積書画面 UI仕様書（マネーフォワード見積書発行）
http://localhost:8000/customers/sync-mf/callback
## ✅ 概要

自社販売管理システムにおいて、承認済みの見積から**マネーフォワードクラウド請求書API `/api/v3/quotes`** を用いて、ワンクリックで見積書を発行する機能を提供する。

---

## 🧩 前提条件

| 項目 | 内容 |
|------|------|
| 対象画面 | 見積書詳細画面 |
| 対象見積 | ステータスが「承認済み」 |
| API | POST `/api/v3/quotes` |
| 顧客コード | 自社顧客コード ≠ MF側 `partner_id`（マッピング必要か？） |
| 認証 | OAuth2 Bearer トークン（事前取得） |

---

## 🖥️ UI仕様

### 🔘 ボタン表示条件

- **ボタン1：マネーフォワードで見積書発行**
  - ステータスが「承認済み」
  - `mf_quote_id` が未設定
  - `mf_partner_id` が設定済み（中間マッピングあり）

- **ボタン2：見積書を確認**
  - `mf_quote_id` が存在
  - `mf_quote_pdf_url` が存在

```jsx
{estimate.status === '承認済み' && estimate.customer.mf_partner_id && !estimate.mf_quote_id && (
  <Button onClick={handleIssueMFQuote}>
    マネーフォワードで見積書発行
  </Button>
)}

{estimate.mf_quote_pdf_url && (
  <a href={estimate.mf_quote_pdf_url} target="_blank" rel="noopener noreferrer">
    <Button>
      見積書を確認
    </Button>
  </a>
)}


⸻

📤 発行処理フロー

1. ユーザー操作
	•	「マネーフォワードで見積書発行」ボタンをクリック
	•	モーダルで確認：「この見積からマネーフォワードで見積書を作成しますか？」

2. API リクエスト生成

POST /api/v3/quotes
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json

{
  "department_id": "xxx",
  "partner_id": "xxx",
  "quote_number": "EST-2025-009",
  "title": "システム開発 2025年9月",
  "memo": "見積メモ",
  "quote_date": "2025-09-05",
  "expired_date": "2025-10-05",
  "note": "納期：発注後2週間以内",
  "document_name": "見積書",
  "items": [
    {
      "name": "顧問契約",
      "detail": "2025年9月の顧問料",
      "unit": "式",
      "price": 100000,
      "quantity": 1,
      "excise": "ten_percent"
    }
  ]
}

3. API レスポンス例（201 Created）

{
  "id": "OfEG-jR-EH4gZoBfDcz1xg",
  "pdf_url": "https://invoice.moneyforward.com/api/v3/quotes/OfEG-jR-EH4gZoBfDcz1xg.pdf",
  ...
}


⸻

✅ 発行後の処理

処理	内容
DB保存	mf_quote_id に id を、mf_quote_pdf_url に pdf_url を保存
ボタン表示切替	「マネーフォワードで見積書発行」→「見積書を確認」
「見積書を確認」クリック時	新しいタブで PDF を開く（target="_blank"）


⸻

🧾 DB構成変更（推奨）

estimates テーブルに以下のカラムを追加：

カラム名	型	説明
mf_quote_id	string	MF上の見積書ID（発行済み管理）
mf_quote_pdf_url	string	PDF URL（MF上の見積書PDF）


⸻

⚠️ 注意事項

項目	内容
APIは即時同期でPDF URLを返すため、追加の確認APIは不要	
同一見積からの重複発行防止のため mf_quote_id で発行済みを判定	
PDFは pdf_url に直接アクセス可能（期限なし）	
partner_id のマッピングがないと発行できないため、事前整備必須	


⸻

✅ テストシナリオ

ステータス	mf_partner_id	mf_quote_id	表示ボタン
承認済	有	無	マネーフォワードで発行
承認済	有	有	見積書を確認
承認済	無	無	表示なし
未承認	有/無	無/有	表示なし


⸻

🧪 将来的な拡張
	•	MF側で送付やステータス更新のUI反映
	•	見積→請求自動変換連携


    MF側スキーマ
    Quote
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
{
  "id": "string",
  "pdf_url": "string",
  "operator_id": "string",
  "department_id": "string",
  "member_id": "string",
  "member_name": "string",
  "partner_id": "string",
  "partner_name": "string",
  "partner_detail": "string",
  "office_id": "string",
  "office_name": "string",
  "office_detail": "string",
  "title": "string",
  "memo": "string",
  "quote_date": "2023/08/24",
  "quote_number": "string",
  "note": "string",
  "expired_date": "2023/08/24",
  "document_name": "string",
  "order_status": "failure",
  "transmit_status": "default",
  "posting_status": "default",
  "created_at": "2019-08-24T14:15:22Z",
  "updated_at": "2019-08-24T14:15:22Z",
  "is_downloaded": true,
  "is_locked": true,
  "deduct_price": "string",
  "tag_names": [
    "string"
  ],
  "items": [
    {
      "id": "string",
      "name": "string",
      "code": "string",
      "detail": "string",
      "unit": "string",
      "price": "string",
      "quantity": "string",
      "is_deduct_withholding_tax": true,
      "excise": "untaxable",
      "created_at": "2019-08-24T14:15:22Z",
      "updated_at": "2019-08-24T14:15:22Z"
    }
  ],
  "excise_price": "string",
  "excise_price_of_untaxable": "string",
  "excise_price_of_non_taxable": "string",
  "excise_price_of_tax_exemption": "string",
  "excise_price_of_five_percent": "string",
  "excise_price_of_eight_percent": "string",
  "excise_price_of_eight_percent_as_reduced_tax_rate": "string",
  "excise_price_of_ten_percent": "string",
  "subtotal_price": "string",
  "subtotal_of_untaxable_excise": "string",
  "subtotal_of_non_taxable_excise": "string",
  "subtotal_of_tax_exemption_excise": "string",
  "subtotal_of_five_percent_excise": "string",
  "subtotal_of_eight_percent_excise": "string",
  "subtotal_of_eight_percent_as_reduced_tax_rate_excise": "string",
  "subtotal_of_ten_percent_excise": "string",
  "total_price": "string"

サンプルリクエスト
curl --request POST \
  --url https://invoice.moneyforward.com/api/v3/quotes \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer 123' \
  --header 'Content-Type: application/json' \
  --data '{
  "department_id": "string",
  "quote_number": "string",
  "title": "string",
  "memo": "string",
  "quote_date": "2022-12-09",
  "expired_date": "2022-12-10",
  "note": "string",
  "tag_names": [
    "string"
  ],
  "items": [
    {
      "item_id": "string",
      "name": "string",
      "detail": "string",
      "unit": "string",
      "price": 10,
      "quantity": 10,
      "is_deduct_withholding_tax": false,
      "excise": "untaxable"
    }
  ],
  "document_name": "string"
}'

サンプルレスポンス
{
  "id": "OfEG-jR-EH4gZoBfDcz1xg",
  "pdf_url": "https:/invoice.moneyforward.com/api/v3/quotes/OfEG-jR-EH4gZoBfDcz1xg.pdf",
  "operator_id": "fbeo9WVrdW36B1CKP3KASg",
  "department_id": "qwc4iT7ZrywxipJCOqtZQg",
  "member_id": "-UNhHGbLKnWH5xlrFhj2ow",
  "member_name": "hb3m8kaxz9eex1czmpn2",
  "partner_id": "95PHKI9_FeSw3coTj673Cg",
  "partner_name": "p41uz1dyvw3cj71qrkja",
  "partner_detail": "〒123-4567\n山形県hb3m8kaxz9eex1czmpn2\nhb3m8kaxz9eex1czmpn2\nhb3m8kaxz9eex1czmpn2\nhb3m8kaxz9eex1czmpn2\nhb3m8kaxz9eex1czmpn2様",
  "office_id": "tZ7wyN9WVuTy7nsisjGsjA",
  "office_name": "My Office Corporation",
  "office_detail": "〒123-4567\n北海道Address 1\nAddress 2\nTEL: 03-1234-5678\nFAX: 03-1234-5678\n",
  "title": "title_149fedb5bq",
  "memo": "memo_149fedb5bq",
  "quote_date": "2022/12/01",
  "quote_number": "quote num_149fedb5bq",
  "note": "note_149fedb5bq",
  "expired_date": "2023/12/30",
  "document_name": "見積書",
  "order_status": "default",
  "transmit_status": "default",
  "posting_status": "default",
  "created_at": "2023-03-20 15:56:27 +0900",
  "updated_at": "2023-03-20 15:56:27 +0900",
  "is_downloaded": false,
  "is_locked": false,
  "tag_names": [
    "tags"
  ],
  "items": [
    {
      "id": "Z12BKLtb0x4IoBHTDY4y5Q",
      "name": "name_0snq9xx1mv",
      "code": "code_0snq9xx1mv",
      "detail": "detail_0snq9xx1mv",
      "unit": "unit_0snq9xx1mv",
      "price": "10",
      "quantity": "10",
      "excise": "untaxable",
      "created_at": "2023-06-07 16:00:19 +0900",
      "updated_at": "2023-06-07 16:00:19 +0900"
    }
  ],
  "excise_price": "0.0",
  "excise_price_of_untaxable": "0.0",
  "excise_price_of_non_taxable": "0.0",
  "excise_price_of_tax_exemption": "0.0",
  "excise_price_of_five_percent": "0.0",
  "excise_price_of_eight_percent": "0.0",
  "excise_price_of_eight_percent_as_reduced_tax_rate": "0.0",
  "excise_price_of_ten_percent": "0.0",
  "subtotal_price": "100.0",
  "subtotal_of_untaxable_excise": "100.0",
  "subtotal_of_non_taxable_excise": "0.0",
  "subtotal_of_tax_exemption_excise": "0.0",
  "subtotal_of_five_percent_excise": "0.0",
  "subtotal_of_eight_percent_excise": "0.0",
  "subtotal_of_eight_percent_as_reduced_tax_rate_excise": "0.0",
  "subtotal_of_ten_percent_excise": "0.0",
  "subtotal_with_tax_of_untaxable_excise": "100.0",
  "subtotal_with_tax_of_non_taxable_excise": "0.0",
  "subtotal_with_tax_of_five_percent_excise": "0.0",
  "subtotal_with_tax_of_tax_exemption_excise": "0.0",
  "subtotal_with_tax_of_eight_percent_excise": "0.0",
  "subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise": "0.0",
  "subtotal_with_tax_of_ten_percent_excise": "100.0",
  "total_price": "100.0"
}