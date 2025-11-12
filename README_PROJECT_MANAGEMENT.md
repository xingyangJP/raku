# プロジェクト管理ガイド（Backlog）

## 採用ツール
- **Backlog** (`https://xkcs.backlog.com`)
- プロジェクト: **`KCSSYSTEM`**（新KCS販売管理システム）
- チケット種別: 「タスク」を基本。障害は「バグ」、改善要望は「要望」を使用。

## チケット運用ルール
1. **命名**: `[ドメイン/機能] 概要` 形式（例: `【見積/MF連携】残存ギャップの解消`）。
2. **説明**: 背景 → 対応範囲 → 期待成果 → 検証観点の順で記載。
3. **優先度**: 既存運用へ影響するものは「中」以上。緊急障害は「高」。
4. **リンク**: 関連仕様・README・ログを必ず貼る（例: `README_ESTIMATE_TEST.md` の該当節）。

## API でのチケット登録例
```bash
curl -X POST "https://xkcs.backlog.com/api/v2/issues?apiKey=YOUR_KEY" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "projectId=704099" \
  --data-urlencode "issueTypeId=3747399" \
  --data-urlencode "priorityId=3" \
  --data-urlencode "summary=【見積/MF連携】残存ギャップの解消" \
  --data-urlencode "description=README_ESTIMATE_TEST.md ...（略）"
```
※ API キーは個別に管理。Git や共有ノートには記載しない。

## 既存チケット
- `KCSSYSTEM-3`: 「【見積/MF連携】残存ギャップの解消（rejected表示や多アカ同期のフィードバック）」
  - 背景: `README_ESTIMATE_TEST.md` の G-03〜G-06 が未対応。
  - 対応項目: rejected ステータス UI/通知、MF 再発行ルール強化、同期エラーの可視化、外部起票フラグ整備。
  - 状態: 未対応。優先度「中」。

## 今後のフロー
1. 仕様差分や新規要望はまず README に落とし込み → バックログに登録。
2. 実装着手時に担当者をアサインし、サブタスクで詳細を分割。
3. リリース後は Backlog で完了クローズし、対応内容を README/CHANGELOG に反映。
