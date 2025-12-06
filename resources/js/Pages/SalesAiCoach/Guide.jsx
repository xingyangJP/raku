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
                            まずやること
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-700">
                        <p>1) 「今日決めたいこと」を短い文で書く（例: 入出庫の流れを見たい）。</p>
                        <p>2) 前回のメモやURLがあれば「過去議事録URL/要約」に貼る（見られるURLだけ）。</p>
                        <p>3) 「優先質問を生成」を押す → 質問と「やる/やらない」が出る。</p>
                        <p>4) 聞き方を変えたいときは「質問リライト」を使う。</p>
                        <p>5) 「やる/やらない」と「今日決める3点」を埋めて、必要なら印刷。</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ListChecks className="h-5 w-5 text-emerald-600" />
                            AIがよく聞くポイント
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-slate-700">
                        <ul className="list-disc pl-5 space-y-1">
                            <li>帳票・印刷: どんな帳票、レイアウト、紙/プリンタ、Excel・CSV</li>
                            <li>承認・権限: 誰が承認、例外、代理、通知、ログ</li>
                            <li>在庫・購買: 入出庫、ロット/期限、直送、返品、棚卸、発注点</li>
                            <li>請求・会計: 税/端数、締め/請求/入金、MF連携、仕訳への影響</li>
                            <li>非機能・運用: 同時利用、速さ、バックアップ/冗長化、監査・保存期間</li>
                        </ul>
                        <p className="text-xs text-slate-500">カスタムプロンプトに書くと、ここに追記されます。</p>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
