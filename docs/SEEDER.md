# SEEDER の仕様と運用ポリシー

> ステータス: 運用方針メモ。実装差分が出た場合は `database/seeders` のコードを優先。

## 方針
- 既存データは壊さない・上書きしない。
- シードは初期セットアップ時のみ実データを作成し、運用以降は **no-op** になる。
- ローカル開発でのみパスワード初期化を実施。

## 実行順序（`DatabaseSeeder`）
1) `UserSeeder`  
   - ユーザが1件でも存在すれば **何もしない**。外部API同期もスキップ。  
   - ユーザが0件のときだけ外部API(`EXTERNAL_API_BASE`/`EXTERNAL_API_TOKEN`)から取得し、メールで突合して作成。
2) 既存の `UsersTableSeeder` / `CustomersTableSeeder` があれば呼び出し。
3) `DevUserPasswordSeeder`（`local` 環境のみ）  
   - ローカル開発用に全ユーザのパスワードを `00000000` に更新。
4) `CategorySeeder`
5) `ItemSeeder`  
   - `products` テーブルに1件でもデータがあれば **スキップ**。  
   - データが空のときだけカテゴリに紐づく品目を投入（SKUはカテゴリの連番で採番）。

※ 見積・請求のダミーデータシード（Quote/Invoice）は無効化済み。

## 運用上の注意
- 本番/ステージングで `php artisan db:seed` を実行しても既存ユーザ・商品は変更されません。
- ローカルでパスワードを初期化したくない場合は `DevUserPasswordSeeder` を呼ばないようにするか、`local` 以外の環境で実行してください。
- `ItemSeeder` は商品が空の環境でのみ動くため、商品マスターを維持したまま他のシードを流しても安全です。

## よく使うコマンド
- 全体シード: `php artisan db:seed`
- 個別シード: `php artisan db:seed --class=UserSeeder`
