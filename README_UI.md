# アラート
https://ui.shadcn.com/docs/components/alert-dialog


# トーストの実装要件

採用方針
	•	Sonner を採用（shadcn/uiが現在こちらを推奨）。<Toaster /> をアプリ共通レイアウトの最上位で1回だけ設置し、どこからでも toast() を呼べる構成にする。 ￼
	•	Laravel×Inertiaの フラッシュメッセージ を自動でトースト表示。検証エラー・通信失敗などの Inertiaイベント もフックして通知を出す。 ￼

実装チェックリスト
	•	npm i sonner を導入し、<Toaster richColors closeButton /> を共通レイアウトに1つ設置。 ￼
	•	位置・余白: 右上（デフォルト）でOK。ヘッダーと重ならないよう top マージンはToaster側オプションで調整可能。
	•	種類: toast.success / .error / .info を使い分ける。非同期処理は toast.promise でローディング→成功/失敗を一括管理。 ￼
	•	重複抑止: 同一キーのメッセージは id 指定で置換。
	•	国際化: メッセージ文字列は翻訳関数を通す（必要なら）。
	•	アクセシビリティ: 自動消滅時間はデフォルトのまま（ユーザー操作で閉じられるよう closeButton 有効化）。
	•	サーバ連携: Laravel側で session()->flash('success'|'error'|'info', '...') した値を Inertia の shared data に載せ、クライアント起動時/画面遷移時にトースト表示。 ￼

追加ファイル/修正

1) 共通レイアウトに Toaster を設置（抜粋）

// resources/js/components/layout/AppLayout.tsx
import { Toaster } from "sonner";
export default function AppLayout({ children }) {
  return (
    <div className="min-h-dvh bg-background text-foreground">
      {/* ...Header / Sidebar... */}
      <main className="mx-auto max-w-screen-2xl px-4 py-6">{children}</main>
      <Toaster richColors closeButton />
    </div>
  );
}

2) フラッシュ & エラーを自動トースト

// resources/js/components/providers/ToastBridge.tsx
import { useEffect } from "react";
import { usePage } from "@inertiajs/react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";

type Flash = { success?: string; error?: string; info?: string };

export default function ToastBridge() {
  const { props } = usePage<{ flash?: Flash }>();

  // Flash messages
  useEffect(() => {
    const f = (props as any).flash as Flash | undefined;
    if (!f) return;
    if (f.success) toast.success(f.success);
    if (f.error) toast.error(f.error);
    if (f.info) toast(f.info);
  }, [props]);

  // Inertia events: 検証エラー/通信エラーなど
  useEffect(() => {
    const offError = router.on("error", (errors: Record<string, string[]>) => {
      const first = Object.values(errors)?.[0]?.[0];
      if (first) toast.error(first);
    });
    const offInvalid = router.on("invalid", () => {
      toast.error("Invalid request");
    });
    return () => { offError(); offInvalid(); };
  }, []);

  return null;
}

Inertiaのshared data/flashの基本とイベントは公式ドキュメント準拠。router.on('error') などでバリデーションエラーを拾える。 ￼

3) レイアウトで <ToastBridge /> を読み込む

// AppLayout.tsx（抜粋）
import ToastBridge from "@/components/providers/ToastBridge";
...
<ToastBridge />
<Toaster richColors closeButton />

4) 使い方（任意のコンポーネント）

import { toast } from "sonner";
toast.success("保存しました");
toast.promise(apiCall(), { loading: "保存中...", success: "保存完了", error: "保存失敗" });

Sonnerの toast() / toast.promise() で統一。 ￼

⸻

レイアウトの実装要件

採用方針
	•	App Shell構造（Header / Sidebar / Main / Footer）。Sidebarは**モバイルでSheet（ドロワー）**に切替。shadcnのSheetを使用。 ￼
	•	共通UI（Breadcrumb / User Menu / Theme Toggle / Search）をヘッダー右側に集約。DropdownMenu でユーザーメニュー実装。 ￼
	•	レスポンシブ: md: でサイドバー常時表示、<md は非表示＋ハンバーガーでSheetを開閉。
	•	アクセシビリティ: Skip link、フォーカス可視化、モバイルSheetはフォーカストラップ。
	•	最大幅: コンテンツは max-w-screen-2xl をデフォルト。
	•	グローバル: <Toaster /> はこのレイアウト直下に設置（上記トースト章）。

実装チェックリスト
	•	ヘッダー: 高さ h-14、左にロゴ/アプリ名、右に検索・テーマ切替・ユーザーメニュー。
	•	サイドバー: デスクトップは固定幅（例 w-64）。モバイルは Sheet 化（side="left"）。 ￼
	•	パンくず: ページ上部に Breadcrumb。
	•	メイン: コンテンツ余白 px-4 py-6、max-w-screen-2xl。
	•	フッター: border-t、小さめの文字でコピーライト。
	•	スキップリンク: sr-only focus:not-sr-only で実装（メインへジャンプ）。
	•	Theme: ダーク/ライト切替（HTMLに class="dark" 戦略）。
	•	テスト安定化: 重要要素に data-testid を付与。

