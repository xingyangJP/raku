import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ListChecks, Sparkles, FileText, Printer, HelpCircle, Wand2 } from 'lucide-react';

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

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card className="relative overflow-hidden">
                    <div className="absolute left-5 top-20 bottom-6 w-px bg-indigo-100" aria-hidden />
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="h-5 w-5 text-amber-500" />
                            使い方タイムライン
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {[
                            {
                                title: 'ゴールを書く',
                                desc: '「今日決めたいこと」を短い文で。例: 入出庫の流れを見える化したい。',
                            },
                            {
                                title: '前回メモ/URLを貼る',
                                desc: '共有リンクや要約を貼るとAIの質問が的確に（見られるURLだけ）。',
                            },
                            {
                                title: '生成ボタンを押す',
                                desc: '質問リストと「やる/やらない」が出る。不要な質問は外し、追記OK。',
                            },
                            {
                                title: 'リライトで深掘り',
                                desc: 'Yes/Noで終わりそうな質問は「質問リライト」で聞き方を変える。',
                            },
                            {
                                title: '決め切る＆印刷',
                                desc: '「やる/やらない」「今日決める3点」を埋め、必要なら印刷して共有。',
                            },
                        ].map((step, idx) => (
                            <div key={idx} className="relative pl-14">
                                <div className="absolute left-5 top-0 flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-sm font-semibold text-indigo-700">
                                    {idx + 1}
                                </div>
                                <div className="space-y-1">
                                    <p className="text-base font-semibold text-slate-800">{step.title}</p>
                                    <p className="text-sm text-slate-700">{step.desc}</p>
                                </div>
                            </div>
                        ))}
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

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5 text-sky-600" />
                            ショートカット
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3 text-sm">
                        <Button asChild variant="outline">
                            <Link href={route('sales-ai-coach.index')}>AIコーチ画面へ戻る</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('sales-ai-coach.guide')}>このページをもう一度見る</Link>
                        </Button>
                        {canManageSettings && (
                            <Button asChild variant="outline">
                                <Link href={route('sales-ai-coach.settings')}>カスタムプロンプトを設定</Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
