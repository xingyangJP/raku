# 事業区分集計 要件定義

## 背景
- Money Forward (MF) で発行した請求書と、社内で作成したローカル請求書 (`local_invoices`) の両方を横断して事業区分別の売上・粗利を確認したい。
- 取引先・期間を跨いだ金額のブレを可視化し、課税区分の申告や月次会議の資料作成に利用する。

## データソース
| ソース | テーブル | 備考 |
| --- | --- | --- |
| Money Forward 同期 | `billings`, `billing_items` | `BillingController` で同期済みレコード。`mf_deleted_at` が null のもののみ集計。 |
| ローカル請求 | `local_invoices` | `/invoices` 画面で作成した請求書。`items` カラム（JSON）を展開して利用。 |

### 重複排除
- `local_invoices.mf_billing_id` が設定されており、同じ ID のレコードが `billings.id` に存在する場合は MF 側を優先しローカル側を除外する。
- `billing_number` は UI 表示のみ使用（ID 判定は MF ID 基準）。

## 集計条件
| 項目 | 仕様 |
| --- | --- |
| 期間 | `billing_date` を基準に `from`〜`to` の年月でフィルタ。 |
| 金額 | 明細 (`items`) の `quantity × price` を合計。 |
| 粗利 | 金額 − `cost`（品目 or 商品マスタから取得）。`cost` 未設定の場合は 0 とみなす。 |
| 品目→商品解決 | `code` → `products.sku`、次に `name` → `products.name` でマッチ。どちらも一致しない場合は未分類。 |
| 事業区分 | `products.business_division` の値を使用。商品マスタが未設定なら `unclassified` としてカウント。 |
| 月別集計 | 期間内の各 `Y-m` でまとめ、全体合計も併記。 |

## UI 要件
- 画面: `/business-divisions`（メニュー「商品管理 > 事業区分集計」）
- フィルタ: 年月 From/To、検索ボタン／リセットボタン。
- 表示コンポーネント:
  1. 事業区分ごとのカード（第1〜第6種＋未分類）に月内合計を表示。
  2. 月別内訳テーブル（列 = 事業区分、行 = 月）。
  3. 詳細一覧（行 = 月／事業区分数字／品目名／顧客名／詳細／数量／金額／粗利）。
- 交互作用:
  - 0 円のカードは非表示。
  - カードをクリックするとその事業区分の詳細リストへスクロール。
  - 未分類の品目に対しては「事業区分を設定する」ボタンで商品マスタをアップデートできる。

## 計算ロジック概要
1. 期間内の `billings` を取得（`mf_deleted_at` null）。
2. 期間内の `local_invoices` を取得。
3. 各レコードの明細を正規化し `['source','billing_id','billing_date','partner_name','items'=>[]]` の配列にまとめる。
4. `mf_billing_id` が一致するローカル請求を除外し、残りを結合。
5. 全明細を走査し、商品マスタを参照して事業区分ラベルを決定。未解決は `unclassified`。
6. `monthlyTotals` / `divisionTotals` / `detailRows` を算出し、Inertia へ渡す。

## 仕様上の留意点
- `local_invoices.items` は JSON 形式（`qty`, `price`, `description`, `code`, `cost`）。過去データでプロパティ名が揺れるため、`qty`/`quantity`, `description`/`detail`, `code`/`product_code` を許容。
- ローカル請求の `billing_date` が null の場合は集計から除外。
- `products` に存在しない品目は未分類として扱うが、詳細一覧から商品編集へのリンクを提供し、ユーザーが直接区分を設定できるようにする（UI 実装は別途）。

## 今後の改善候補
1. **課税区分別集計**: みなし仕入率だけでなく、税区分（10%/軽減など）での切り替えを追加。
2. **CSV エクスポート**: 月別内訳と詳細一覧を一括ダウンロードできるようにする。
3. **グラフ表示**: shadcn + recharts で円グラフや棒グラフを追加し、事業区分の割合を視覚化する。
