# 商品マスタ & Money Forward 品目連携

## 目的
- 社内の商品・サービスをカテゴリ（商品分類）単位で管理し、Money Forward の品目 API と同期する。
- 商品コードを `<商品分類コード>-<3桁連番>` で自動採番し、MF 側の品目コードとも整合性を取る。

## 商品分類（categories）
| 項目 | 仕様 |
| --- | --- |
| 採番 | `CategoryController@store` で `A, B, ... Z, AA, AB, ...` の連番をロック付きで採番。 |
| 編集 | 名前のみ更新可能。コードは自動採番で不変。 |
| 削除 | 使用中チェックは未実装のため、必要に応じて外部キー制約で保護する。 |
| UI | `/products` の「商品分類を管理」ボタンで shadcn Dialog を開き、追加／編集／削除を実行。 |

## 商品（products）
- バリデーションは `App\Http\Requests\ProductRequest` で定義。`category_id` は必須。
- 新規作成時はトランザクションで `categories.last_item_seq` を `lockForUpdate()` し、シーケンスを +1 → `sku` を生成。
- `ProductController@update` では商品分類変更時に新しい商品分類のシーケンスで再採番。
- モデルは `mf_id`, `mf_updated_at` を保持し、Money Forward との双方向同期に利用。
- 簡易課税制度の「事業区分」を `business_division` カラムで保持。新規作成時は第5種事業（50%）が初期値。

### 事業区分の選択肢
| 値 | 表示名 | みなし仕入率 | 主な業種例 |
| --- | --- | --- | --- |
| `first_business` | 第1種事業 | 90% | 卸売業 |
| `second_business` | 第2種事業 | 80% | 小売業（第1種を除く）、農林・漁業（飲食料品関連を除く） |
| `third_business` | 第3種事業 | 70% | 農林・漁業（飲食料品関連）、製造業、電気・ガス等 |
| `fourth_business` | 第4種事業 | 60% | 飲食店業を除く加工・役務提供 |
| `fifth_business` | 第5種事業（初期値） | 50% | 運輸通信、金融、保険、IT/システム開発などのサービス業 |
| `sixth_business` | 第6種事業 | 40% | 不動産業 |

## UI のポイント
- `/products` 一覧は検索フォームと shadcn Table を利用。`CategoryDialog` をモーダルで呼び出し、保存後は `router.reload({ only: ['categories'] })` でフィルタ候補を更新。
- 商品編集画面では SKU は読み取り専用。商品分類コンボボックスはカテゴリリストを API から受け取って表示。
- 一覧・作成・編集フォームに「事業区分」セレクトを追加し、一覧ではバッジで表示。検索フォームからも絞り込み可能。

## Money Forward 連携
| 操作 | ルート | 処理内容 |
| --- | --- | --- |
| 画面表示で自動同期 | `GET /products` | 画面表示時に `performSyncAllToMf` を実行。ローカル商品から Money Forward へ差分同期（作成・更新・削除）し、結果をフラッシュメッセージで表示。 |
| 手動一括同期 | `GET /products/sync-all` | UI の「MFへ同期」ボタン経由。自動同期と同じ処理を明示的に実行。 |
| 単品同期（補助ルート） | `GET /products/{product}/sync-one` | Money Forward に品目を作成／更新。UI 上の個別ボタンは廃止したが、ルートは互換のため残存。 |
| OAuth 開始 | `GET /products/auth/start` | `scope = mfc/invoice/data.read mfc/invoice/data.write`。 |
| コールバック | `GET /products/auth/callback` | トークン保存後、セッションのアクションに応じて同期処理（自動同期・手動同期・単品同期）を実行。 |

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
- `ItemSeeder` は商品分類に属する商品を順次作成し、必要に応じて `category_id` / `seq` を設定。
- Seeder で生成される商品も `business_division = fifth_business`（第5種事業）で投入する。

## テスト観点
1. 新規商品作成時に SKU が `<カテゴリコード>-001` の形式になること。
2. 商品分類変更時に SKU が新しい商品分類コードで再採番されること。
3. Money Forward 連携フローでアクセストークンが無い場合に OAuth が開始され、復帰後に差分同期が継続されること。
4. 自動／手動同期後に Money Forward 側で作成・更新・削除された品目の `mf_id`／`mf_updated_at` がローカルに反映されること。
