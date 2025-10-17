# 商品マスタ & Money Forward 品目連携

## 目的
- 社内の商品・サービスをカテゴリ（分類）単位で管理し、Money Forward の品目 API と同期する。
- 商品コードを `<分類コード>-<3桁連番>` で自動採番し、MF 側の品目コードとも整合性を取る。

## 分類（categories）
| 項目 | 仕様 |
| --- | --- |
| 採番 | `CategoryController@store` で `A, B, ... Z, AA, AB, ...` の連番をロック付きで採番。 |
| 編集 | 名前のみ更新可能。コードは自動採番で不変。 |
| 削除 | 使用中チェックは未実装のため、必要に応じて外部キー制約で保護する。 |
| UI | `/products` の「分類を管理」ボタンで shadcn Dialog を開き、追加／編集／削除を実行。 |

## 商品（products）
- バリデーションは `App\Http\Requests\ProductRequest` で定義。`category_id` は必須。
- 新規作成時はトランザクションで `categories.last_item_seq` を `lockForUpdate()` し、シーケンスを +1 → `sku` を生成。
- `ProductController@update` では分類変更時に新しい分類のシーケンスで再採番。
- モデルは `mf_id`, `mf_updated_at` を保持し、Money Forward との双方向同期に利用。

## UI のポイント
- `/products` 一覧は検索フォームと shadcn Table を利用。`CategoryDialog` をモーダルで呼び出し、保存後は `router.reload({ only: ['categories'] })` でフィルタ候補を更新。
- 商品編集画面では SKU は読み取り専用。分類コンボボックスはカテゴリリストを API から受け取って表示。

## Money Forward 連携
| 操作 | ルート | 処理内容 |
| --- | --- | --- |
| 品目全件同期 | `GET /products/sync-all` | `MoneyForwardApiService::getItems()` で全品目を取得し、`mf_id` をキーに upsert。 |
| 単品同期 | `GET /products/{product}/sync-one` | Money Forward に品目を作成／更新。`mf_id` が無効な場合は再作成。 |
| OAuth 開始 | `GET /products/auth/start` | `scope = mfc/invoice/data.read mfc/invoice/data.write`。 |
| コールバック | `GET /products/auth/callback` | トークン保存後、セッションのアクションに応じて同期処理を実行。 |

### API マッピング
- `createItem` / `updateItem` は Money Forward API `/api/v3/items` を使用。
- `Product` → Money Forward 品目のフィールド対応:
  - `name` → `name`
  - `sku` → `code`（null の場合は MF 側で自動採番）
  - `description` → `detail`
  - `unit`, `price`, `quantity`, `tax_category` → `excise` に対応
  - `is_deduct_withholding_tax` は個人事業主向け項目としてそのまま送信

## Seeders
- `CategorySeeder` と `ItemSeeder` が初期データを投入。両方ともロックとリトライを実装しており、並列実行でもユニーク制約に耐える。
- `ItemSeeder` はカテゴリーに属する商品を順次作成し、必要に応じて `category_id` / `seq` を設定。

## テスト観点
1. 新規商品作成時に SKU が `<カテゴリコード>-001` の形式になること。
2. 分類変更時に SKU が新しい分類コードで再採番されること。
3. Money Forward 連携フローでアクセストークンが無い場合に OAuth が開始されること。
4. `sync-all` 実行後に `products` テーブルへ `mf_id` が保存されること。
