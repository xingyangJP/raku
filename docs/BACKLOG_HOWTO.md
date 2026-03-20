# Backlog 起票ガイド

> ステータス: 運用手順（開発チーム向け）。

## 目的
Backlog へタスクを追加するときに改行が `\n` のまま表示される問題を防ぎ、読みやすい書式を維持するための手順をまとめる。

## 基本ルール
1. **API リクエストは `curl --data-urlencode` を利用**
   - 改行を含む本文は `--data-urlencode "description=..."` で送信し、文字列中に実際の改行を埋め込む。
   - シェル展開を避けるため、`"` 内で `\n` を使わない。複数行をそのまま記述する。
2. **カテゴリ/種別は事前に確認**
   - `GET /projects/{id}/categories` で既存カテゴリを確認し、必要なら先に作成する。
3. **テンプレート構造**
   - 先頭は `目的:`、続いて空行、`ステップ:` と番号付き箇条書きにする。
   - 例:
     ```
     目的: ...

     ステップ:
     1. ...
     2. ...
     ```
4. **修正時も `--data-urlencode` を使用**
   - 誤記を直すときも `PATCH ... --data-urlencode "description=..."` を用い、本文全体を置き換える。
5. **記録**
   - 起票完了後は issue key (例: KCSSYSTEM-40) を控え、ドキュメントやコミットメッセージに記載する。

## 参考コマンド
```bash
# 例: 改行付き description を新規登録
curl -s -X POST \
  -d "projectId=704099" \
  -d "summary=タスク名" \
  -d "issueTypeId=3747399" \
  --data-urlencode "description=目的: ...\n\nステップ:\n1. ..." \
  "https://xkcs.backlog.com/api/v2/issues?apiKey=${BACKLOG_API_KEY}"
```
