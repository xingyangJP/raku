# Estimates × Money Forward テストシナリオ

Money Forward との見積書連携とローカル承認ワークフローを安全に運用するためのテスト観点を整理する。  
対象コード例: `EstimateController`, `MoneyForwardApiService`, `MoneyForwardQuoteSynchronizer`, `resources/js/Pages/Estimates/Create.jsx`, `resources/views/estimates/pdf.blade.php`。

---

## 0. 前提・用語
- ステータス: `draft`（ドラフト）/`pending`（承認待ち）/`sent`（承認済み）。`EstimateController@update` で `approval_flow` の未承認者がいれば強制的に `pending` へ落とす。
- Money Forward 連携ボタンは `sent` かつ `client_id` と `mf_department_id` が揃っているときのみ活性化（`README_quote2bill.md` 参照）。
- `mf_quote_id` 保持中にステータスが `sent` 以外へ戻ると `EstimateController@update` から `deleteMoneyForwardQuote()` が呼ばれ、MF 側データ削除＋ローカル ID クリア。
- MF 側で見積が消えた場合は `MoneyForwardQuoteSynchronizer::markMissingQuotesAsDeleted()` が `mf_deleted_at` を打ち、ローカル ID を破棄する。
- 明細には `display_mode`（`calculated` / `lump`）を持ち、`lump` の場合は UI/PDF では「1 式表示」。MF API へ送信する数量は元のまま、単位だけ `display_unit` に置換（`MoneyForwardApiService::createQuoteFromEstimate`）。

---

## 1. ローカル承認フローの基本シナリオ

| ID | シナリオ | 手順 | 期待結果 |
| --- | --- | --- | --- |
| L-01 | 新規作成→承認申請→承認 | ドラフト作成 → `approval_flow` を 2 名に設定し申請 → 各承認者が順番に承認 | `approval_flow[i].approved_at` が埋まり、最終承認時に `status=sent`・`approval_started=false`。Google Chat 通知が申請・各承認・最終承認で送られる。 |
| L-02 | 承認待ちキャンセル | L-01 の途中で「申請取消」 | `status=draft`、`approval_flow=[]`。Chat 通知 `notifyApprovalCancelled` が飛ぶ。 |
| L-03 | 承認済み再申請 | `sent` の見積を編集し数量変更→申請 | `status` が `pending` に落ち、旧 `mf_quote_id` が削除される（MF API Delete 成功/失敗のログ確認）。 |
| L-04 | 承認者 ID のバリデーション | `approval_flow` に存在しない user.id / external_user_id を混在 | `EstimateController@updateApproval` が「現在の承認ステップの担当者ではありません。」を返し操作できない。 |
| L-05 | 日付 UI 例外 | 10 月 31 日の見積日を月だけ 11 月へ変更 → フロントで日も 31 日のまま | `Create.jsx` 日付入力で `isCompleteDateInput` に反しエラー。`due_date <= issue_date` の場合に自動補正されることを確認（無限ロードが無い）。 |
| L-06 | 却下操作 | 現行承認者が「却下する」を押下 | `status=rejected` に遷移し、以降の承認者は操作できない。Google Chat に却下通知が飛ぶ。 |
| L-07 | 注文確定トグル | 承認済み見積の編集画面で注文確定を ON/OFF | `is_order_confirmed` が更新され、予実カード（予算/実績/達成率）に即時反映される。承認前はトグルが無効。 |

---

## 2. Money Forward 連携（ローカル起点）

| ID | シナリオ | 手順 | 期待結果 |
| --- | --- | --- | --- |
| MF-01 | 初回 OAuth & 発行 | `sent` かつ `client_id` / `mf_department_id` あり → 「マネーフォワードで見積書発行」 → OAuth → 発行 | `createQuoteFromEstimate` 成功。`mf_quote_id`・`mf_quote_pdf_url` 保存、ボタンが「PDFダウンロード」に変わる。 |
| MF-02 | display_mode = lump | 単価 700,000・数量 0.2・`display_mode=lump`/`display_unit=式` で MF 発行 | MF API へ `{quantity:0.2, unit:'式', price:700000}` が送られ、MF UI では 0.2 人月計算結果が金額に反映されつつ単位は「式」表示。 |
| MF-03 | Partner Contact 同期 | `client_contact_name/title` を編集し MF 発行 | `EstimateController::_doCreateMfQuote` 前に `syncPartnerContactWithMoneyForward` が呼ばれ、MF 部門の担当者が最新化される。 |
| MF-04 | 必須値抜けのバリデーション | `client_id` or `mf_department_id` 未選択のまま発行ボタン | `createQuote.start` 前に UI 側で非表示／強行した場合は API 400、「取引先を選択してください」。 |
| MF-05 | トークン失効 | `mf_quote_id` あり → PDF ダウンロード → 401 → OAuth | `handleCallback` が `view_quote_pdf` で再トークンを取得し `quotes/{id}.pdf` を inline 応答。 |
| MF-06 | 請求書へ変換 | `mf_quote_id` あり → 「請求へ変換」 | `/quotes/{id}/convert_to_billing` 成功で `mf_invoice_id` と PDF URL が保存され、UI で「請求書を確認」リンクが出る。 |

---

## 3. Money Forward → ローカル同期

| ID | シナリオ | 手順 | 期待結果 |
| --- | --- | --- | --- |
| SY-01 | MF のみ存在する見積を同期 | MF 上で見積作成 → `/quotes` 表示で `syncIfStale` 実行 | `estimates` にレコードが作成され `status=sent`。`estimate_number` は MF のもの、`items` は MF API から構築。 |
| SY-02 | quote_number 正規化 | MF 上で `EST-CRM-1-XXX` のような番号を設定 | `normalizeQuoteNumber` により `EST-1-XXX` でローカル一致させる（ログ `normalized_quote_number`）。 |
| SY-03 | MF 側削除検知 | ローカルに `mf_quote_id` がある見積を MF 管理画面で削除 → `/quotes` で同期 | `markMissingQuotesAsDeleted` が `mf_deleted_at` にタイムスタンプ、`mf_quote_id` / `mf_quote_pdf_url` / `mf_invoice_*` を null 化。再発行時は `mf_deleted_at` がクリアされる。 |
| SY-04 | MF 側編集反映 | MF でタイトル・金額を修正 → `/quotes` で同期 | `updateEstimate()` が `title`, `total_amount`, `items` などを上書き。ローカル手入力との差分確認。 |
| SY-05 | 複数アカウント同期 | 同じ企業の MF を複数ユーザーが OAuth 登録 → `/quotes` 表示 | `sync()` が全 `MfToken` を走査しアクセストークン毎に同期。両者で二重レコードが発生しない（`synced_quote_ids` で dedup）。 |
| SY-06 | MF item キャッシュ | `/quotes` 表示前に `/items` を呼んでおき SKU/ID をキャッシュ | 同期ログに `MF quote sync: item cache loaded`。以降アイテム解決が `item_id`/`code` 経由で成功。 |

---

## 4. 削除・再承認・エッジケース

| ID | シナリオ | 手順 | 期待結果 |
| --- | --- | --- | --- |
| DL-01 | ローカル削除（MF 未削除） | `mf_quote_id` を持つ見積を `/estimates/{id}` DELETE | 現状 `EstimateController@destroy` では MF 側削除が行われない。テストでは MF に孤児データが残ることを確認し、改善チケットを起票（ギャップ G-01 参照）。 |
| DL-02 | 再承認時の旧 PDF ボタン非表示 | `sent` → MF 発行 → ローカル編集で `pending` → UI 表示確認 | `estimate.mf_quote_id=null` になり PDF ボタンが消える。再承認後にのみ再表示。 |
| DL-03 | 承認後ステータスを手動で `draft` に更新 | API 経由で `status=draft` を送信 | `deleteMoneyForwardQuote` が走り、`approval_flow` はクリアされないため UI との整合を確認。 |
| DL-04 | `rejected` ステータス遷移 | 却下済み見積を編集 / 再申請 | UI から却下→再申請のフローが機能するか、ボタン表示・通知が期待通りかを確認。 |
| DL-05 | `mf_deleted_at` 再利用 | SY-03 後に再度 MF 発行 | `mf_deleted_at` が null に戻り、新 `mf_quote_id` を保持。 |

---

## 5. バリデーション / エラー系

| ID | シナリオ | 観点 |
| --- | --- | --- |
| ER-01 | `title` 未入力で承認申請 | コントローラから `validation.required` が返り、Inertia エラーが Create.jsx で `ErrorBanner` に表示される（ログ例: `承認申請エラー: {title: 'validation.required'}`）。 |
| ER-02 | 数量入力 | `Input[type=number]` からスピンボタンを廃止し、小数第1位まで直接入力。`normalizeNumber` が `NaN` の場合に `fallback` を採用することを確認。 |
| ER-03 | display_qty ≤ 0 | `handleItemChange` で 1 に補正される。MF/API/PDF 出力も 1 式となる。 |
| ER-04 | `due_date` < `issue_date` | サーバで `Carbon` が `expired->lte(issue)` を検知し +1 month。 |
| ER-05 | OAuth 失敗 | `handleCallback` で code 無し → `Authorization failed.` を返し `/quotes` へ。 |

---

## 6. 仕様ギャップ・追加対応案

| ID | 事象 / 想定される抜け | 影響 | 対応案 |
| --- | --- | --- | --- |
| G-01 | ローカル見積を削除しても MF 見積は削除されない（`destroy` で API Delete 無し） | MF 側に不要な見積が残り、再承認時に番号重複や監査上の混乱を招く。 | `EstimateController@destroy` で `deleteMoneyForwardQuote` を呼び、失敗してもログ＋ユーザー通知。 |
| G-02 | 承認取消 (`cancel`) 時に `mf_quote_id` を保持したまま | ユーザーが「申請取消 → 再編集 → 再申請」の間に旧 MF PDF が表示されたままになる可能性。 | `cancel()` にも `deleteMoneyForwardQuote` + ID クリアを追加する。 |
| G-03 | `rejected` ステータスの UI/通知未対応 | API では受理されるが表示・通知が未定義。 | UI で `rejected` 状態をフラグ表示し、再申請時の Chat 通知ルールを決める。 |
| G-04 | MF 発行後に `client_id` を変更しても自動で MF 再発行されない | 異なる取引先の MF 見積が残る。 | `client_id` 変更時はステータス強制 `pending` + 旧 `mf_quote_id` 削除を行うバリデーションを追加。 |
| G-05 | 多アカウント同時同期時の権限差異 | 片方の OAuth スコープが不足すると同期全体が `status=error` になりがち（`hadFailure`）。 | エラーをアカウント単位でリスト化して UI に表示、成功分は成功と明示。 |
| G-06 | MF 側で直接編集された見積がローカル承認フローを持たない | `sync` 時に `approval_flow` は空のまま `status=sent` になるため社内承認履歴が欠落。 | MF 由来データには「外部起票」フラグをセットし、ローカルで再承認する際のガイドを設ける。 |

---

## 7. 推奨テスト実施順序
1. **ローカル承認フロー**（L-01〜L-05）で UI/通知の基本を確認。
2. **MF 発行フロー**（MF-01〜MF-06）を実行し、`mf_quote_id`／PDF／請求変換の一連をカバー。
3. **同期検証**（SY-01〜SY-06）で MF 側操作 → ローカル反映を確かめる。
4. **再承認・削除系**（DL-01〜DL-05）で `deleteMoneyForwardQuote` と UI 表示切替を確認。
5. **エラー系**（ER-01〜ER-05）で入力や OAuth 例外を最終チェック。

すべてのシナリオを E2E で自動化するのが難しい場合でも、上記順序で手動検証すれば主要なデグレを検知できる。ギャップ（G-01〜G-06）は別途バックログ化し、仕様確定後に回帰テストへ追加する。***

---

## 8. Backlog チケット対応状況（2025-11-11 更新）
| 区分 | Backlog Key | 内容 |
| --- | --- | --- |
| G-01 | `KCSSYSTEM-15` | ローカル削除時に Money Forward 見積を連動削除 |
| G-02 | `KCSSYSTEM-16` | 申請取消時に MF 見積情報をクリア |
| G-03 | `KCSSYSTEM-17` | `rejected` ステータスの UI/通知定義（理由入力／通知連携まで実装済） |
| G-04 | `KCSSYSTEM-18` | MF 発行後の取引先変更フロー（再承認） |
| G-05 | `KCSSYSTEM-19` | 多アカウント同期エラーの UI 可視化 |
| G-06 | `KCSSYSTEM-20` | 外部起票見積のフラグと再承認導線 |
| Test Step 1 | `KCSSYSTEM-21` | ローカル承認フロー回帰（L-01〜L-05） |
| Test Step 2 | `KCSSYSTEM-22` | MF 発行・請求変換フロー（MF-01〜MF-06） |
| Test Step 3 | `KCSSYSTEM-23` | MF→ローカル同期確認（SY-01〜SY-06） |
| Test Step 4 | `KCSSYSTEM-24` | 再承認・削除系テスト（DL-01〜DL-05） |
| Test Step 5 | `KCSSYSTEM-25` | エラーハンドリング＆入力検証（ER-01〜ER-05） |

※チケットは `https://xkcs.backlog.com/view/<KEY>` で参照可能。進捗更新時は本表と Backlog の両方を同期させる。***
