📄 見積→MF見積/請求 連携（最新仕様）

環境変数
- MONEY_FORWARD_CLIENT_ID: 発行済みクライアントID
- MONEY_FORWARD_CLIENT_SECRET: クライアントシークレット
- MONEY_FORWARD_QUOTE_SCOPE: 既定 `mfc/invoice/data.write`
- MONEY_FORWARD_ESTIMATE_REDIRECT_URI: `http://localhost:8000/estimates/create-quote/callback`
- MONEY_FORWARD_PARTNER_REDIRECT_URI: `http://localhost:8000/mf/partners/callback`
- MONEY_FORWARD_INVOICE_REDIRECT_URI: `http://localhost:8000/invoices/send/callback`
- MONEY_FORWARD_CONVERT_REDIRECT_URI: `http://localhost:8000/estimates/convert-to-billing/callback`
- MONEY_FORWARD_QUOTE_VIEW_REDIRECT_URI: `http://localhost:8000/estimates/view-quote/callback`

MF 側で必ず登録する Redirect URI
- `http://localhost:8000/estimates/create-quote/callback`
- `http://localhost:8000/estimates/convert-to-billing/callback`
- `http://localhost:8000/estimates/view-quote/callback`
- `http://localhost:8000/mf/partners/callback`
- `http://localhost:8000/invoices/send/callback` ← ローカル請求書送信フロー

概要（OAuth 2.0 Authorization Code）
- 画面からMF認可画面へ遷移 → 認可コード取得
- サーバ側で `token` エンドポイントに交換 → アクセストークン取得
- API 呼び出し（見積作成 `/quotes`、請求変換 `/quotes/{id}/convert_to_billing`、請求作成 `/invoice_template_billings`）
- 成功時にIDやPDF URLを保存しUI更新

主なフローと使用ルート
- 見積→MF見積作成: `GET /estimates/{estimate}/create-quote`
  - コールバック: `GET /estimates/create-quote/callback`
  - コントローラ: `EstimateController@redirectToAuthForQuoteCreation` / `handleQuoteCreationCallback`
- 見積→請求へ変換: `GET /estimates/{estimate}/convert-to-billing`
  - コールバック: `GET /estimates/convert-to-billing/callback`
  - コントローラ: `EstimateController@redirectToAuthForBillingConversion` / `handleBillingConversionCallback`
- ローカル請求→MF請求作成: `GET /invoices/{invoice}/send`
  - コールバック: `GET /invoices/send/callback`
  - コントローラ: `LocalInvoiceController@redirectToAuthForSending` / `handleSendCallback`

必要なスコープ
- `mfc/invoice/data.write`（見積・請求の作成/変換/ダウンロードに必要）

よくあるエラー（400 Bad Request）
- 原因: `redirect_uri` 未登録または不一致
  - 対応: 上記の各コールバックURLをMFアプリのリダイレクトURIに追加。アプリ側で使用しているURLと完全一致（ホスト/ポート/パス/スキーム）させる
- 原因: `client_id`/`secret` 誤り
  - 対応: `.env` を再確認
- 原因: スコープ不正
  - 対応: `.env` の `MONEY_FORWARD_QUOTE_SCOPE` を `mfc/invoice/data.write` に

よくあるエラー（422 Unprocessable Entity）
- エラー: `Validation failed: Partner department not found`
  - 原因: 送信している `department_id` が取引先のMF部門IDと不一致（デモデータや手入力のコードを使っている等）
  - 対応: ダッシュボードの「取引先同期」を実行して最新の部門IDをDBに保存し、画面で部門を選択し直す。部門が1件も無い場合はMF側で部門を作成してから再同期する

実装の要点
- コールバックURLはフロー毎に固定
  - 見積作成: `.env:MONEY_FORWARD_ESTIMATE_REDIRECT_URI`
  - ローカル請求作成: `.env:MONEY_FORWARD_INVOICE_REDIRECT_URI`
  - パートナー同期: `.env:MONEY_FORWARD_PARTNER_REDIRECT_URI`
- トークン交換時は「認可リクエスト時に使ったのと同じ `redirect_uri`」を渡す
- item_id を指定しないitemsは `excise` 必須
- `quote_number` は30文字以内（実装で丸め込み済み）

API リンク
- 認可: `https://api.biz.moneyforward.com/authorize`
- トークン: `https://api.biz.moneyforward.com/token`
- 見積: `POST https://invoice.moneyforward.com/api/v3/quotes`
- 見積→請求: `POST https://invoice.moneyforward.com/api/v3/quotes/{quote_id}/convert_to_billing`
- 請求作成(テンプレート): `POST https://invoice.moneyforward.com/api/v3/invoice_template_billings`

トラブルシュートチェックリスト
- `.env` の ID/Secret/各 Redirect URI が正しい
- MF デベロッパー設定の Redirect URI に上記を全て登録済み
- ブラウザから遷移している `redirect_uri`（ネットワークログ）とMF設定が一致
- `scope=mfc/invoice/data.write` で送っている

補足（PDF表示）
- 見積PDF: `GET /estimates/{estimate}/view-quote` → コールバックでPDFストリーム
- ローカル請求PDF: `GET /invoices/{invoice}/view-pdf` → コールバックでPDFストリーム
	•	見積書の送信状態(transmit_status)や受注状態(order_status)のUI表示
	•	見積 → 請求書 自動変換ワークフロー
	•	MF見積書リストとの双方向同期

⸻


# サンプルリクエスト

<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/quotes",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'department_id' => 'string',
    'quote_number' => 'string',
    'title' => 'string',
    'memo' => 'string',
    'quote_date' => '2022-12-09',
    'expired_date' => '2022-12-10',
    'note' => 'string',
    'tag_names' => [
        'string'
    ],
    'items' => [
        [
                'item_id' => 'string',
                'name' => 'string',
                'detail' => 'string',
                'unit' => 'string',
                'price' => 10,
                'quantity' => 10,
                'is_deduct_withholding_tax' => null,
                'excise' => 'untaxable'
        ]
    ],
    'document_name' => 'string'
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

# サンプルレスポンス
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

#　Request Body
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
