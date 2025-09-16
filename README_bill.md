# サンプルリクエスト
<?php

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://invoice.moneyforward.com/api/v3/billings/{billing_id}",
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
  "id": "string",
  "pdf_url": "string",
  "operator_id": "string",
  "department_id": "string",
  "member_id": "string",
  "member_name": "string",
  "partner_id": "string",
  "partner_name": "string",
  "office_id": "string",
  "office_name": "string",
  "office_detail": "string",
  "title": "string",
  "memo": "string",
  "payment_condition": "string",
  "billing_date": "2023/08/24",
  "due_date": "2023/08/24",
  "sales_date": "2023/08/24",
  "billing_number": "string",
  "note": "string",
  "document_name": "string",
  "payment_status": "未設定",
  "email_status": "未送信",
  "posting_status": "未郵送",
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
      "updated_at": "2019-08-24T14:15:22Z",
      "delivery_number": "string",
      "delivery_date": "2023/08/24"
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
  "subtotal_with_tax_of_untaxable_excise": "string",
  "subtotal_with_tax_of_non_taxable_excise": "string",
  "subtotal_with_tax_of_tax_exemption_excise": "string",
  "subtotal_with_tax_of_five_percent_excise": "string",
  "subtotal_with_tax_of_eight_percent_excise": "string",
  "subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise": "string",
  "subtotal_with_tax_of_ten_percent_excise": "string",
  "total_price": "string",
  "registration_code": "string",
  "use_invoice_template": true,
  "config": {
    "rounding": "round_down",
    "rounding_consumption_tax": "round_down",
    "consumption_tax_display_type": "internal"
  }
}