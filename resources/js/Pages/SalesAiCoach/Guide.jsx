import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ListChecks, Sparkles, FileText, Wand2, HelpCircle, Printer } from 'lucide-react';

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

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ListChecks className="h-5 w-5 text-emerald-600" />
                            要件定義ってなに？
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm text-slate-700">
                        <div className="rounded-lg border border-emerald-100 bg-emerald-50 p-3 space-y-2">
                            <p className="text-sm font-semibold text-emerald-800">一言でいうと</p>
                            <p>お客様のざっくりした希望・不満・仕事のやり方を、エンジニアがそのまま設計・開発に使えるレベルまで言葉にして整理することです。販売管理システムなら「売上・在庫・請求をまとめたい」「請求漏れをなくしたい」「社長が外から売上を見たい」といった声をそのまま渡すのではなく、「誰が・いつ・どの画面で・どんな項目を入力し」「どのタイミングで伝票や請求書が発行され」「誰が・どんな権限で・どこから確認できるのか」まで具体化する仕事です。</p>
                        </div>

                        <div className="rounded-lg border border-blue-100 bg-blue-50 p-3 space-y-2">
                            <p className="text-sm font-semibold text-blue-800">機能要件だけで終わらせない</p>
                            <p className="text-xs text-blue-700">機能要件は「何ができるか」（例: 売上登録ができる、在庫照会ができる）。</p>
                            <p className="text-sm font-semibold text-indigo-800">非機能要件も同じくらい重要</p>
                            <p className="text-xs text-indigo-700">どれくらい速く動くべきか、何人同時に使えるか、どれくらい止まってはいけないか、どのレベルのセキュリティが必要か、どれだけ簡単に使えるべきか、といった“使い勝手・品質・安全性・運用面”の条件。販売管理の例: 「朝9〜11時のピークでも検索3秒以内」「停止は月1回夜間2時間まで」「過去7年分をすぐ検索」「得意先ごとに参照権限を分ける」「毎晩自動バックアップ」。</p>
                            <p className="text-xs text-indigo-700">機能だけ固めて非機能を曖昧にすると、導入後に「遅い/落ちる/セキュリティ不安/使いにくい」で手戻り・追加コストになりがち。</p>
                        </div>

                        <div className="rounded-lg border border-amber-100 bg-amber-50 p-3 space-y-2">
                            <p className="text-sm font-semibold text-amber-800">レストランの例</p>
                            <p className="text-xs text-amber-700">お客:「今日、大事な接待で使うから、あまり重くなくて、お酒に合う料理を一人5,000円くらいでお願い」。<br />ホール（営業）がキッチン（エンジニア）に「なんか美味しいコース、いい感じでお願いします」だけだと、和/洋、肉/魚、アレルギー、5,000円に飲み物を含むのかが不明で「違う料理」が出るリスク大。</p>
                            <p className="text-xs text-amber-700">必要なのは「接待で失敗できない」「予算は料理のみ5,000円」「役員クラスで量より質」「お酒に合う和食中心」「18時に6名でスタート」といった注文票＝システムでいう機能要件の整理。</p>
                            <p className="text-xs text-amber-700">さらに「料理が出るまでの待ち時間」「提供ペース」「店内の静かさ・雰囲気」「食材管理・衛生」といった“見えにくい条件”＝システムでいう非機能要件（速度/同時アクセス/停止許容/セキュリティ/バックアップ）。</p>
                        </div>

                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-1">
                            <p className="text-sm font-semibold text-slate-800">まとめ</p>
                            <p className="text-sm text-slate-700">「いい感じ」は図面にならない。機能要件と非機能要件を数字や条件で固め、“設計と見積もりに直結する注文票”にするのが要件定義。営業が非機能まで引き出し合意するかで、成功率と信頼が大きく変わります。</p>
                        </div>
                    </CardContent>
                </Card>

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
                                icon: <HelpCircle className="h-4 w-4 text-indigo-600" />,
                            },
                            {
                                title: '前回メモ/URLを貼る',
                                desc: '共有リンクや要約を貼るとAIの質問が的確に（見られるURLだけ）。',
                                icon: <Link className="h-4 w-4 text-indigo-600" />,
                            },
                            {
                                title: '生成ボタンを押す',
                                desc: '質問リストと「やる/やらない」が出る。不要な質問は外し、追記OK。',
                                icon: <Sparkles className="h-4 w-4 text-amber-500" />,
                            },
                            {
                                title: 'リライトで深掘り',
                                desc: 'Yes/Noで終わりそうな質問は「質問リライト」で聞き方を変える。',
                                icon: <Wand2 className="h-4 w-4 text-purple-500" />,
                            },
                            {
                                title: '決め切る＆印刷',
                                desc: '「やる/やらない」「今日決める3点」を埋め、必要なら印刷して共有。',
                                icon: <Printer className="h-4 w-4 text-slate-600" />,
                            },
                        ].map((step, idx) => (
                            <div key={idx} className="relative pl-14">
                                <div className="absolute left-5 top-0 flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-sm font-semibold text-indigo-700">
                                    {idx + 1}
                                </div>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        {step.icon}
                                        <p className="text-base font-semibold text-slate-800">{step.title}</p>
                                    </div>
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
                            要件定義ってなに？
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-700">
                        <p>システム開発の営業の仕事は「いい感じ」を図面と見積りに変えること。何を作るか・誰が使うか・いつまでに・いくらで・やる/やらない・例外を言葉で固めます。</p>
                        <p className="text-sm text-slate-700">機能だけでなく、非機能（速さ・同時接続・停電/バックアップ・監査/ログ・セキュリティ）も最初に聞いておくと、後戻りを防げます。</p>
                        <div className="grid gap-3 lg:grid-cols-2">
                            <div className="space-y-2">
                                <p className="text-sm font-semibold text-slate-800">家づくりの例</p>
                                <div className="rounded-lg border border-amber-100 bg-amber-50 p-3 space-y-1">
                                    <p className="text-sm font-semibold text-amber-800">ダメなパターン</p>
                                    <p className="text-sm text-amber-700">お客さん:「家族4人で住めて、リビング広めの家がいいな」<br />新人営業:「いい感じの家、作っておきます！」→ 設計図なしで工事へ</p>
                                    <p className="text-xs text-amber-700">大工(エンジニア)は困る: 予算? いつまで? 駐車場? 子ども部屋? 収納?</p>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-sm font-semibold text-slate-800">営業がすべきこと</p>
                                <ul className="list-disc pl-5 space-y-1 text-sm text-slate-700">
                                    <li>予算: 3,000万円以内</li>
                                    <li>入居: 来年4月まで</li>
                                    <li>駐車場: 車2台分</li>
                                    <li>子ども部屋: 2部屋（将来仕切れるように）</li>
                                    <li>リビング: 20畳以上を優先</li>
                                    <li>安全・快適: 暖房/換気、耐震、騒音対策（＝システムでいう非機能）</li>
                                </ul>
                                <p className="text-xs text-slate-500">これが「図面にできる情報＝要件定義」。</p>
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-1">
                            <p className="text-sm font-semibold text-slate-800">まとめ</p>
                            <p className="text-sm text-slate-700">「いい感じに」は図面にならない。<br />「いい感じ」を設計できる言葉に変えるのが営業の仕事。システムも同じ。</p>
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Wand2 className="h-5 w-5 text-purple-500" />
                            リライトのコツ
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-slate-700">
                        <p className="flex items-center gap-2"><HelpCircle className="h-4 w-4 text-purple-500" /> まず事実を聞く → 理想を聞く → 制約/例外を聞く → 優先度を聞く。</p>
                        <p className="flex items-center gap-2"><FileText className="h-4 w-4 text-purple-500" /> 例: 「在庫ずれありますか？」→「現状: どこでずれが起きやすいですか？ 理想: どうなればOK？ 制約: システム/人/時間の制約は？ 例外: 特別対応は？ 優先: どれから直しますか？」</p>
                        <p className="flex items-center gap-2"><Sparkles className="h-4 w-4 text-purple-500" /> Yes/Noで終わる質問は、具体的な状況や数字を聞く形に変えると深掘りできます。</p>
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
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
