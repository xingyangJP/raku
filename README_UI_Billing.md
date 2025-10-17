# Billing / 請求書一覧

## 目的 / Purpose
- 自社およびMoney Forwardから取得した請求書を一覧表示し、必要な絞り込みとPDF閲覧動線を提供する。

## エントリ条件 / Entry Conditions
- 認証済みユーザーのみアクセス可能 (`AuthenticatedLayout` を利用)。
- Money Forward連携設定が存在する場合に請求書が表示される。

## 主要フロー / Primary Flow
1. ユーザーが請求書一覧画面を開く。
2. 請求月やキーワードで対象データを絞り込み、サマリーカードとテーブルで請求内容を確認する。
3. 各請求書からPDF出力・Money Forward画面リンク・ローカル編集等のアクションを実行できる。

## 状態・例外 / States & Edge Cases
- `error` プロップが渡された場合、画面上部にエラーバナーを表示。
- フィルタ結果が0件のときは「請求データがありません。」と表示。
- Money Forward未連携のローカル請求書は「MF未生成」を表示し、`invoices.viewPdf.start` へのリンクでローカルPDFを取得。
- 金額は `formatCurrency` で円表示、日付は `formatDate` で和暦形式表示。
- 画面アクセスごとにMoney Forwardの請求書同期を実行し、最終同期時刻を通知。
- 請求月フィルタは年月単位での絞り込みを行い、未指定時は全期間を対象とする。
- Money Forwardトークンが未認証の場合は認証ダイアログ(外部OAuth)へ強制遷移し、完了まで画面に戻らない。
- 支払期日フィルタや旧売掛サマリー機能は統合に伴い廃止済み。

## 入力/検証 / Inputs & Validation
- 請求月 From/To: `<input type="month">`。初期値はデフォルトレンジ（サーバで計算）。
- タイトル: `title` に部分一致でフィルタ。
- 取引先: `partner` に部分一致でフィルタ。
- ステータス: `resolvePaymentStatus()` の結果（未入金/入金）で絞り込み。
- リセット: デフォルトの請求月レンジ（前月）に戻し、その他フィルタを空にする。

## コンポーネント / Components
- 入力類は標準 Tailwind クラスを使用（shadcn 表示は未使用）。
- Money Forward 認証リンク: `<a href={moneyForwardAuthUrl}>マネーフォワードから取得</a>` で OAuth を起動。
- ローカル請求への導線: `Link` コンポーネントで `route('billing.create')` に遷移（現状プレースホルダ）。

## セレクタ / Test Selectors
- 現状 `data-testid` は未定義。E2Eテスト追加時はフィルタフォームと主要テーブルに識別子を付与する。

## i18n / アクセシビリティ
- 表示テキストは日本語固定。将来的な多言語対応は未実装。
- フォーム要素は `label` / `htmlFor` を明示し、スクリーンリーダー対応。
- テーブル行はクリックで Money Forward 画面／ローカル編集に遷移できるが、リンクとして実装しているためキーボード操作可能。

## 計測 / Telemetry
- 現状、イベント計測は実装されていない。

## スクリーンショット
- TODO: 最新UIのスクリーンショットを取得し、リンクを追記する。
