# 見積システム API リファレンス

## 概要

RAKUSHIRU Cloud 見積システムの外部連携 API です。
外部システムから、受注確定済みの見積と、その売上金額・開発管理用金額・消費税・税込合計・工数人日を取得できます。

## ベース URL

| 環境 | URL |
| --- | --- |
| 本番 | `https://sales.xerographix.co.jp` |

## 認証

すべての API は Bearer token 認証です。

```http
Authorization: Bearer <EXTERNAL_INTEGRATION_API_TOKEN>
Accept: application/json
```

token はサーバー環境変数 `EXTERNAL_INTEGRATION_API_TOKEN` に設定します。
未設定、未送信、不一致の場合は `401` を返します。

## API の使い方

### 基本フロー

1. 連携先システムに `EXTERNAL_INTEGRATION_API_TOKEN` を安全に設定します。
2. 初回同期では `GET /api/v1/confirmed-estimates?per_page=100` を `page=1` から順に取得します。
3. 取得結果の `meta.current_page` が `meta.last_page` に達するまで `page` を増やして取得します。
4. 連携先システムには `id`、`estimate_number`、`updated_at` を保存します。
5. 2回目以降は `updated_since=<前回同期日時>` を付けて差分取得します。
6. 明細確認が必要な見積だけ `GET /api/v1/confirmed-estimates/{estimate}` を呼びます。

### 初回同期の例

```bash
API_TOKEN="your-token"
BASE_URL="https://sales.xerographix.co.jp"

curl -sS \
  -H "Authorization: Bearer ${API_TOKEN}" \
  -H "Accept: application/json" \
  "${BASE_URL}/api/v1/confirmed-estimates?per_page=100&page=1"
```

レスポンスの `meta.last_page` が `2` 以上の場合は、`page=2`、`page=3` のように最後のページまで取得してください。

### 差分同期の例

```bash
API_TOKEN="your-token"
BASE_URL="https://sales.xerographix.co.jp"
UPDATED_SINCE="2026-06-15T00:00:00+09:00"

curl -sS \
  -H "Authorization: Bearer ${API_TOKEN}" \
  -H "Accept: application/json" \
  "${BASE_URL}/api/v1/confirmed-estimates?per_page=100&updated_since=${UPDATED_SINCE}"
```

`updated_since` は「前回同期で正常に保存できた時刻」を使います。
取りこぼしを避けたい場合は、前回同期時刻より数分前を指定し、連携先システム側で `id` と `updated_at` を使って上書き保存してください。

### 一覧 API と詳細 API の使い分け

| 用途 | 使うAPI | 理由 |
| --- | --- | --- |
| 予実管理の案件一覧を作る | `GET /api/v1/confirmed-estimates` | 金額、工数、担当、納期など主要項目を一覧で取得できるため。 |
| 差分同期する | `GET /api/v1/confirmed-estimates?updated_since=...` | 更新された受注確定済み見積だけを取得できるため。 |
| 明細ごとの事業区分や金額を確認する | `GET /api/v1/confirmed-estimates/{estimate}` | `items` に明細別の `business_division`、税抜小計、工数が含まれるため。 |

### 開発の予実・工数管理で使う項目

開発に関する予実管理では、第1種事業の仕入れ販売額を開発予算に含めないでください。
連携先システムでは次のように使い分けます。

| 連携先の用途 | API フィールド | 備考 |
| --- | --- | --- |
| 開発予算 | `development_subtotal_excluding_tax` | 第1種を除外した税抜小計。予実管理の予算額に使います。 |
| 計画工数 | `effort_person_days` | 第1種を除外した工数人日。工数管理に使います。 |
| 受注売上 | `sales_subtotal_excluding_tax` | 第1種を含む見積全体の税抜小計。売上管理に使います。 |
| 仕入れ販売額 | `first_business_subtotal_excluding_tax` | 第1種だけの税抜小計。開発予算からは除外します。 |
| 互換用小計 | `subtotal_excluding_tax` | `sales_subtotal_excluding_tax` と同じ値。新規の予実管理では使わないでください。 |

例として、見積全体が 1,250,000 円、うち第1種が 90,000 円の場合、開発予算として使う値は `development_subtotal_excluding_tax = 1,160,000` です。
`subtotal_excluding_tax` や `sales_subtotal_excluding_tax` を開発予算に使うと、第1種の 90,000 円が混ざり、予算消化率や原価率の見方がずれます。

### 予実管理システムへの保存例

| 予実管理側の項目 | API から入れる値 |
| --- | --- |
| 外部見積ID | `id` |
| 見積番号 | `estimate_number` |
| 顧客名 | `customer_name` |
| 案件名 | `title` |
| 担当者 | `staff_name` |
| 予定開始日 | `start_date` |
| 納品予定日 | `delivery_date` |
| 開発予算（税抜） | `development_subtotal_excluding_tax` |
| 計画工数（人日） | `effort_person_days` |
| 売上金額（税抜） | `sales_subtotal_excluding_tax` |
| 第1種金額（税抜） | `first_business_subtotal_excluding_tax` |
| 最終更新日時 | `updated_at` |

### 注意点

- この API は受注確定済み見積だけを返します。未受注見積の見込み予算管理には使えません。
- `updated_since` は削除済み見積を返しません。削除連携が必要な場合は、別途削除検知用 API の追加が必要です。
- 明細単位の厳密な内訳が必要な場合は、詳細 API の `items` を保存してください。
- token は連携先システムのサーバー側で保管し、ブラウザやフロントエンドコードに埋め込まないでください。

## 共通仕様

- Content-Type は JSON です。
- 日付は `YYYY-MM-DD` 形式です。
- `updated_at` は ISO 8601 形式です。
- 金額は円です。
- 工数は人日です。
- 取得対象は `is_order_confirmed = true` かつ `mf_deleted_at IS NULL` の見積だけです。
- 社内メモ、原価、粗利、承認フローは返しません。

## 税抜小計と工数の計算

### 税抜小計

`subtotal_excluding_tax` は互換用の見積全体税抜小計です。新規連携では用途に応じて次のフィールドを使ってください。

| Field | 用途 |
| --- | --- |
| `sales_subtotal_excluding_tax` | 売上・受注額用。第1種を含む見積全体の税抜小計。 |
| `development_subtotal_excluding_tax` | 開発の予実・工数管理用。第1種を除外した税抜小計。 |
| `first_business_subtotal_excluding_tax` | 第1種だけの税抜小計。仕入れ販売額の確認用。 |
| `subtotal_excluding_tax` | 互換用。`sales_subtotal_excluding_tax` と同じ値。 |

`sales_subtotal_excluding_tax` と `subtotal_excluding_tax` は次の順で計算します。

1. 保存済みの `total_amount - tax_amount` を優先します。
2. 保存値が欠ける場合だけ、明細の `total_price` / `amount` / `qty * price` から補完します。

`development_subtotal_excluding_tax` と `first_business_subtotal_excluding_tax` は明細単位の税抜小計を事業区分で分けて集計します。
開発の予実・工数管理では `development_subtotal_excluding_tax` を使ってください。

### 工数人日

`effort_person_days` は第1種を除外し、以下の単位を人日に換算します。

| 単位 | 換算 |
| --- | --- |
| 空欄 / `人日` を含む単位 | `qty` をそのまま人日として扱う |
| `人月` を含む単位 | `qty * APP_PERSON_DAYS_PER_PERSON_MONTH` |
| `人時` / `時間` / `h` / `hr` | `qty / APP_PERSON_HOURS_PER_PERSON_DAY` |
| その他 | `0` |

`business_division = first_business` の明細は工数対象外です。

## エンドポイント一覧

| Method | Path | 用途 |
| --- | --- | --- |
| GET | `/api/v1/confirmed-estimates` | 受注確定済み見積一覧を取得 |
| GET | `/api/v1/confirmed-estimates/{estimate}` | 受注確定済み見積の詳細を取得 |

## GET `/api/v1/confirmed-estimates`

受注確定済み見積の一覧を取得します。

### Query Parameters

| Name | Type | Required | Default | Description |
| --- | --- | --- | --- | --- |
| `per_page` | integer | No | `50` | 1ページあたりの件数。`1` から `100`。 |
| `updated_since` | date/datetime | No | なし | 指定日時以降に更新された見積だけを取得。例: `2026-06-01T00:00:00+09:00` |
| `page` | integer | No | `1` | ページ番号。Laravel paginator の標準パラメータ。 |

### Request Example

```bash
curl -sS \
  -H "Authorization: Bearer <API_TOKEN>" \
  -H "Accept: application/json" \
  "https://sales.xerographix.co.jp/api/v1/confirmed-estimates?per_page=50"
```

### Response Example

```json
{
  "data": [
    {
      "id": 180,
      "estimate_number": "EST-7-9-261305-003",
      "customer_name": "有限会社 サンプル",
      "client_id": "example-client-id",
      "title": "サーバー更新",
      "status": "sent",
      "is_order_confirmed": true,
      "issue_date": "2026-05-12",
      "due_date": "2026-06-11",
      "start_date": "2026-06-01",
      "delivery_date": "2026-06-25",
      "staff_id": 7,
      "staff_name": "担当者名",
      "subtotal_excluding_tax": 1436200,
      "sales_subtotal_excluding_tax": 1436200,
      "development_subtotal_excluding_tax": 800000,
      "first_business_subtotal_excluding_tax": 636200,
      "tax_amount": 143620,
      "total_amount": 1579820,
      "effort_person_days": 12.5,
      "updated_at": "2026-06-15T10:00:00+09:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1
  }
}
```

### Response Fields

| Field | Type | Description |
| --- | --- | --- |
| `id` | integer | 見積ID |
| `estimate_number` | string/null | 見積番号 |
| `customer_name` | string/null | 顧客名 |
| `client_id` | string/null | 顧客ID |
| `title` | string/null | 見積件名 |
| `status` | string | 見積ステータス |
| `is_order_confirmed` | boolean | 受注確定フラグ。返却対象では常に `true`。 |
| `issue_date` | string/null | 見積日 |
| `due_date` | string/null | 期限日 |
| `start_date` | string/null | 着手日 |
| `delivery_date` | string/null | 納品日 |
| `staff_id` | integer/string/null | 担当者ID |
| `staff_name` | string/null | 担当者名 |
| `subtotal_excluding_tax` | number | 互換用の小計（税抜）。`sales_subtotal_excluding_tax` と同じ値。 |
| `sales_subtotal_excluding_tax` | number | 売上・受注額用の小計（税抜）。第1種を含む見積全体。 |
| `development_subtotal_excluding_tax` | number | 開発の予実・工数管理用の小計（税抜）。第1種を除外。 |
| `first_business_subtotal_excluding_tax` | number | 第1種だけの小計（税抜）。 |
| `tax_amount` | number | 消費税 |
| `total_amount` | number | 合計（税込） |
| `effort_person_days` | number | 工数人日 |
| `updated_at` | string/null | 更新日時 |

## GET `/api/v1/confirmed-estimates/{estimate}`

受注確定済み見積の詳細を取得します。
一覧レスポンスに加えて、明細 `items` を返します。

未受注、削除扱い、存在しないIDの場合は `404` です。

### Request Example

```bash
curl -sS \
  -H "Authorization: Bearer <API_TOKEN>" \
  -H "Accept: application/json" \
  "https://sales.xerographix.co.jp/api/v1/confirmed-estimates/180"
```

### Response Example

```json
{
  "data": {
    "id": 180,
    "estimate_number": "EST-7-9-261305-003",
    "customer_name": "有限会社 サンプル",
    "client_id": "example-client-id",
    "title": "サーバー更新",
    "status": "sent",
    "is_order_confirmed": true,
    "issue_date": "2026-05-12",
    "due_date": "2026-06-11",
    "start_date": "2026-06-01",
    "delivery_date": "2026-06-25",
    "staff_id": 7,
    "staff_name": "担当者名",
    "subtotal_excluding_tax": 1436200,
    "sales_subtotal_excluding_tax": 1436200,
    "development_subtotal_excluding_tax": 800000,
    "first_business_subtotal_excluding_tax": 636200,
    "tax_amount": 143620,
    "total_amount": 1579820,
    "effort_person_days": 12.5,
    "updated_at": "2026-06-15T10:00:00+09:00",
    "items": [
      {
        "product_id": 123,
        "code": "DEV-001",
        "name": "開発作業",
        "quantity": 10,
        "unit": "人日",
        "unit_price": 80000,
        "business_division": "fifth_business",
        "line_subtotal_excluding_tax": 800000,
        "effort_person_days": 10
      }
    ]
  }
}
```

### Item Fields

| Field | Type | Description |
| --- | --- | --- |
| `product_id` | integer/null | 商品ID |
| `code` | string/null | 商品コード |
| `name` | string/null | 明細名 |
| `quantity` | number | 数量 |
| `unit` | string/null | 単位 |
| `unit_price` | number | 単価 |
| `business_division` | string/null | 事業区分 |
| `line_subtotal_excluding_tax` | number | 明細小計（税抜） |
| `effort_person_days` | number | 明細工数人日 |

## Error Responses

### 401 Unauthorized

認証 token が未設定、未送信、不一致の場合。

```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found

詳細APIで、対象IDが存在しない、未受注、または削除扱いの場合。

```json
{
  "message": "Not Found"
}
```

### 422 Unprocessable Entity

クエリパラメータの形式が不正な場合。

```json
{
  "message": "The per page field must not be greater than 100.",
  "errors": {
    "per_page": [
      "The per page field must not be greater than 100."
    ]
  }
}
```

## 運用メモ

- token 実値は Git にコミットしません。
- token 変更後は Laravel の config cache 更新が必要です。
- 本番では deploy workflow が `config:clear` と `config:cache` を実行します。
- token 漏洩時は `EXTERNAL_INTEGRATION_API_TOKEN` を差し替えて再度 config cache を更新してください。
- API利用側には一覧APIの `updated_since` を使った差分取得を推奨します。
- 開発の予実・工数管理では、見積全体の `subtotal_excluding_tax` ではなく `development_subtotal_excluding_tax` を使ってください。

## 最終確認済み

- 本番ルート登録: 確認済み
- token 未送信時: `401`
- token 送信時: `200`
- 本番ログ: API関連 error なし
