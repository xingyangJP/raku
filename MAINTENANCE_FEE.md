# 保守売上管理メニュー 設計メモ

## UI仕様（一覧画面）
- メニュー名: 「保守売上管理」
- レイアウト: テーブル一覧
    - 列: 顧客名 (`customer_name`), サポート種別 (`support_type` ラベル表示), 月額保守料金 (`maintenance_fee`、通貨形式)
    - 0円のレコードは表示しない（API取得時点で除外）
    - 並び順: 月額保守料金の降順（要件に応じて顧客名昇順へ変更可）
- ページング: 50件/ページ想定（APIレスポンスが多い場合に備える）
- フィルタ:
    - 顧客名の部分一致検索
    - サポート種別の絞り込み（ドロップダウン: 例「スタンダード」「プレミアム」など、APIから得られる値をそのままラベル化）
- 空状態: 「月額保守料金が設定された顧客がありません」と表示
- エラーハンドリング: APIエラー時はトーストまたはアラートでメッセージ表示＋再試行ボタン
- 手動再同期: 「当月を再同期」ボタンで当月スナップショットをAPI値で再取得・再計算する。

### サマリーカード（画面上部）
- 月額保守合計: 取得データの `maintenance_fee` 合計を通貨形式で表示。
- アクティブ顧客数: `maintenance_fee > 0` の件数。
- 平均保守金額: 合計 ÷ アクティブ顧客数（件数0の場合は `—` または0円で表示）。
- レイアウト: 3枚のカードを横並び（レスポンシブで縦積み）。
- データソース: 一覧と同じレスポンスをクライアントで集計（サーバプロキシの場合はサーバ側で集計して返しても可）。

## データ取得
- API: `GET https://api.xerographix.co.jp/api/customers/{id}` ではなく、保守費用一覧のエンドポイントが必要。  
  - 仕様に明示された一覧がない場合、`GET /api/customers` で `maintenance_fee` を含む顧客全件を取得してクライアント側で `maintenance_fee > 0` にフィルタする。
  - 取得量が多い場合はサーバ側でクエリパラメータを使い `maintenance_fee_min=1`（存在すれば）や pagination を利用。
- 認証: 既存の XEROPM API 認証方式（恐らく Bearer Token）を流用。
- キャッシュ: 簡易に SWR/React Query の stale-while-revalidate を検討。初回読み込みを早くするためローカルキャッシュ許容。

### 月次スナップショット
- テーブル: `maintenance_fee_snapshots`（migration 追加済み）
    - `month`(date, unique) / `total_fee` / `total_gross`（保守は粗利＝売上想定）/ `source`
- ロジック:
    - まず当該月のスナップショットを参照（存在すればそれを採用）。
    - 無ければ API から最新値を取得してスナップショットに保存し、5分キャッシュ。
    - 過去の月は保存済みスナップショットを見に行くので、新しい顧客追加で過去月が書き換わることを防ぐ。
    - 当月のみ、画面の「当月を再同期」操作で強制再取得可能。

## 実装計画
1) フロントエンド
   - ページ追加: `resources/js/Pages/MaintenanceFees/Index.jsx`（仮）で一覧描画。
   - ルーティング: Inertia ルート `maintenance-fees.index` を追加。
   - API呼び出し: Axios で `GET /api/customers` (または専用エンドポイント) を呼び、`maintenance_fee > 0` にフィルタ。クエリ文字列で検索・種別フィルタを付与。
   - UI: Shadcn テーブル + Combobox + Input でフィルタ。`support_type` はラベルマップを用意（例: `standard` → 「スタンダード」）。
2) バックエンド（Laravel）
   - ルート追加: `Route::get('/maintenance-fees', [MaintenanceFeeController::class, 'index'])->name('maintenance-fees.index');`
   - コントローラ: API をプロキシ or 直接呼び出す `MaintenanceFeeController@index` を追加し、Inertia ページへ初期データを渡す or JS 側で直接 API を叩く方針を決定。
   - 認証トークン: `.env` の API キーを利用（例: `XEROPM_API_TOKEN`）。Proxy する場合はサーバ側でヘッダ付与。
3) バリデーション／制御
   - フロント: 入力文字列サニタイズ、デバウンス付き検索。
   - サーバ: クエリパラメータ（キーワード・種別・ページ）をバリデーションし API に渡す。
4) 表示フォーマット
   - 金額: `¥{Number(maintenance_fee).toLocaleString()}`。
   - サポート種別: マッピングテーブルでラベル化、未知の値はそのまま表示。
5) テスト
   - フロント: コンポーネント単体テスト（フィルタ適用で 0円が消えること）。
   - サーバ: API プロキシの 200/4xx/5xx ハンドリング、`maintenance_fee > 0` フィルタが効いていること。

## 留意事項
- 0円除外を API 側で行えるならサーバプロキシでフィルタする方が効率的。応答が大きい場合はページング必須。
- API スキーマ（`maintenance_fee` の単位/型）が未確定なら、まずサンプルレスポンスを確認してから実装する。
- 権限: 保守売上管理を閲覧できるロールを確認し、必要ならミドルウェアで制御。
