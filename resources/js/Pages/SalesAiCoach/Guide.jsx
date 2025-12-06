import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ListChecks, Sparkles, FileText } from 'lucide-react';

export default function Guide() {
    const { auth } = usePage().props;
    const currentUserId = auth?.user?.id;
    const canManageSettings = [3, 8].includes(currentUserId);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-2xl font-semibold text-slate-800">訪問前AIコーチ 使い方</h2>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('sales-ai-coach.index')}>戻る</Link>
                        </Button>
                        {canManageSettings && (
                            <Button asChild variant="ghost" size="sm">
                                <Link href={route('sales-ai-coach.settings')}>設定</Link>
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="訪問前AIコーチ 使い方" />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="h-5 w-5 text-amber-500" />
                            基本の使い方
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-700">
                        <p>1) 「今日決めたいこと」をできるだけ具体的に入力します（例: 発注→入荷→出庫の流れを追えるようにしたい）。</p>
                        <p>2) 「過去議事録URL/要約」に共有リンクや要約を貼ると、内容を読み取り質問精度が上がります（閲覧可能URLのみ）。</p>
                        <p>3) 「優先質問を生成」を押すと、質問リストと「やる/やらない」案が出ます。不要な質問は外し、追記してください。</p>
                        <p>4) 「質問リライト」で Yes/No になりがちな質問を深掘り版に変換できます。</p>
                        <p>5) 右カラムの「今日決め切る3点」と「やる/やらない」を埋め、印刷ボタンで1枚にまとめられます。</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ListChecks className="h-5 w-5 text-emerald-600" />
                            質問の観点（デフォルト）
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-slate-700">
                        <ul className="list-disc pl-5 space-y-1">
                            <li>帳票/印刷: 帳票種類、レイアウト、プリンタ/紙サイズ、Excel/CSV出力</li>
                            <li>ワークフロー/権限: 承認経路、例外、代理、通知、操作ログ</li>
                            <li>在庫/購買: 入出庫、ロット/期限、直送、返品、棚卸、発注点/リードタイム</li>
                            <li>請求/会計: 税・端数、締め/請求/入金消込、MF連携、仕訳影響</li>
                            <li>非機能/運用: 同時利用、レスポンス、バックアップ/冗長化、監査・保持期間</li>
                        </ul>
                        <p className="text-xs text-slate-500">カスタムプロンプトを設定すると上記に追記されます。</p>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
