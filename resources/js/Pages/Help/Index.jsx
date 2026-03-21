import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import {
    BarChart3,
    BookOpen,
    Boxes,
    CircleCheckBig,
    ClipboardList,
    FileText,
    Link as LinkIcon,
    Package,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';

const quickLinks = [
    { id: 'overview', label: 'まずここ', icon: BookOpen },
    { id: 'dashboard', label: 'ダッシュボード', icon: BarChart3 },
    { id: 'estimates', label: '見積作成', icon: FileText },
    { id: 'quotes', label: '見積一覧', icon: ClipboardList },
    { id: 'products', label: '商品管理', icon: Package },
    { id: 'sync', label: 'MF連携', icon: RefreshCw },
];

const highlights = [
    {
        title: '担当者按分を追加',
        description: '第1種以外の明細は担当者按分で工数を管理します。第1種は工数対象外なので担当者設定不要です。',
        tone: 'border-rose-200 bg-rose-50',
    },
    {
        title: '個別キャパ設定',
        description: '全員一律20人日ではなく、ユーザーごとに月間開発キャパを設定してダッシュボードと見積シミュレーションへ反映します。',
        tone: 'border-sky-200 bg-sky-50',
    },
    {
        title: '見積一覧を運用画面化',
        description: '保存ビュー、工数注意、失注、追跡期限の処理を見積一覧で完結できるようにしています。',
        tone: 'border-amber-200 bg-amber-50',
    },
    {
        title: '事業区分分析を統合',
        description: '旧「事業区分集計」はダッシュボードへ統合しました。分析はダッシュボード、マスタ修正は商品管理で行います。',
        tone: 'border-emerald-200 bg-emerald-50',
    },
];

const overviewRules = [
    '第1種 = 仕入れ販売。工数計画・担当者按分の対象外です。',
    '第5種 = 開発/設計など。担当者按分の対象です。',
    '一式表示は表示だけの切替です。工数対象かどうかの判定には使いません。',
    '受注済は表示ステータス上「受注済」として扱い、失注や期限超過の対象から外れます。',
    'ダッシュボードの担当者別空き状況は、個別キャパ設定と明細の担当者按分を元に集計します。',
];

const dashboardSections = [
    {
        title: '何を見る画面か',
        items: [
            '経営判断用です。予算/実績、前年比、資金繰り、工数、事業区分分析をまとめて見ます。',
            '営業の作業処理は見積一覧、詳細入力は見積作成/編集に分けています。',
        ],
    },
    {
        title: '工数の見方',
        items: [
            '担当者別の空き状況は、第1種以外かつ担当者按分が入力された明細を基本に集計します。',
            '未割当工数は、当月対象の第1種以外で担当者未設定の明細です。',
            '個別キャパ設定済みだが未割当の担当者も「空きあり」の母集団に含みます。',
            '標準1人月は未設定ユーザーへのフォールバックです。実際の月間キャパはユーザー別設定の合計です。',
        ],
    },
    {
        title: '資金繰りの見方',
        items: [
            '受注確定案件は、注文書納期を優先し「納期月請求・翌月入金」の近似で見ています。',
            '未受注案件は見込みとして扱うため、確定資金繰りとは分けて解釈してください。',
            '実入金日までは持っていないため、現時点では経営判断用の予測値です。',
        ],
    },
    {
        title: '事業区分分析',
        items: [
            '事業区分分析はダッシュボード内へ統合済みです。',
            '請求実績ベースの構成比・月次推移を見る場所で、商品の事業区分修正は商品管理で行います。',
        ],
    },
];

const estimateSections = [
    {
        title: '見積作成の基本ルール',
        items: [
            '明細は商品マスタを選んで作ります。旧見積は code/name から既存商品へ自動補完されます。',
            '商品の事業区分が第1種なら担当者設定不要、第5種なら担当者按分が必要です。',
            '担当者按分は保存時に合計100%へ正規化します。',
            '担当者未設定や按分未入力は赤警告で表示されますが、保存自体は禁止していません。',
        ],
    },
    {
        title: '担当者按分',
        items: [
            '複数人で分担する場合は担当者を追加し、按分率を入力します。',
            '旧見積でヘッダ担当者しかない場合は、編集画面で100%按分として初期表示します。',
            '第1種は仕入れ販売扱いなので「担当者設定不要」表示になります。',
        ],
    },
    {
        title: '担当者負荷シミュレーション',
        items: [
            '納期月の既存予定工数に、この見積の担当者按分を重ねて見ます。',
            '対象月の合計キャパ、既存余力、未割当工数、担当者ごとの個別キャパを確認できます。',
            '営業や総務など、人ごとに異なる月間キャパ設定をそのまま使います。',
        ],
    },
    {
        title: '承認から発行まで',
        items: [
            '下書き保存 → 承認ルート設定 → 承認申請 → 承認済 → Money Forward発行、の順です。',
            '承認済みの見積を編集すると再申請扱いになります。',
            '受注確定後は表示上「受注済」になり、失注登録はできません。',
        ],
    },
];

const quoteSections = [
    {
        title: '見積一覧の役割',
        items: [
            '見積一覧は経営ダッシュボードではなく、営業・見積運用の作業台です。',
            '保存ビューで、承認待ち・自分担当・MF未発行・期限超過・失注・要フォローを切り替えます。',
            '保存ビューは見積一覧の直上にあり、押した結果が一覧にすぐ反映されます。',
        ],
    },
    {
        title: 'ステータスの意味',
        items: [
            '受注確定した見積は表示上「受注済」です。',
            '失注は「失注」ラベルで表示し、期限超過や要フォローの対象から外れます。',
            '受注済と失注は、どちらも期限超過モーダルの対象外です。',
        ],
    },
    {
        title: '工数注意列',
        items: [
            '第1種以外で担当者未設定なら「担当未割当」、按分が空なら「按分未設定」を優先表示します。',
            '未設定がなければ、今月/来月の受注ベース工数に対して「余力あり / 逼迫 / 過負荷」を表示します。',
            '第1種は工数管理対象外なので、担当者未設定ラベルは出しません。',
        ],
    },
    {
        title: '期限超過・失注・追跡期限',
        items: [
            '見積期限を過ぎた未受注案件は、一覧アクセス時に判断モーダルを出します。',
            'モーダルでは「失注にする」か「まだ追う」を選べます。',
            'まだ追う場合は追跡期限を延長し、次回確認日として管理します。',
            '複数件ある場合は古い期限順に1件ずつ続けて処理できます。',
        ],
    },
];

const productSections = [
    {
        title: '商品分類とSKU',
        items: [
            '商品分類は A〜G の分類コードを持ち、SKU採番の基準になります。',
            '分類を新規追加すると、英字コードは自動採番されます。',
            '商品の事業区分は第1種/第5種の工数ルールに直結します。',
        ],
    },
    {
        title: '事業区分の使われ方',
        items: [
            '第1種商品を見積へ入れた場合、その明細は工数対象外です。',
            '第5種商品を見積へ入れた場合、その明細は担当者按分と工数シミュレーションの対象です。',
            '事業区分分析はダッシュボードで見ます。商品管理では商品マスタを正しく保つことが役割です。',
        ],
    },
];

const syncSections = [
    {
        title: '同期の基本',
        items: [
            'Money Forward 連携は画面ごとに役割が違います。',
            'ダッシュボードは取引先、見積一覧は見積、請求管理は請求、商品管理は品目を主に同期します。',
            'トークンが失効すると再認証が必要です。再認証メッセージが出たらその画面からやり直してください。',
        ],
    },
    {
        title: '自動同期と手動同期',
        items: [
            'ダッシュボードの取引先同期はクールダウン付きの自動同期です。必要なら手動で更新できます。',
            '見積一覧・請求一覧・商品一覧は、画面上の同期ボタンで再取得できます。',
            'Money Forward 側で直接編集したものをすぐ反映したいときは、手動同期を使ってください。',
        ],
    },
];

const sectionGroups = [
    { id: 'dashboard', title: 'ダッシュボード', icon: BarChart3, sections: dashboardSections },
    { id: 'estimates', title: '見積作成・編集', icon: FileText, sections: estimateSections },
    { id: 'quotes', title: '見積一覧 /quotes', icon: ClipboardList, sections: quoteSections },
    { id: 'products', title: '商品管理', icon: Boxes, sections: productSections },
    { id: 'sync', title: 'Money Forward連携', icon: LinkIcon, sections: syncSections },
];

function QuickLinkButton({ id, label, icon: Icon }) {
    return (
        <a
            href={`#${id}`}
            className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
        >
            <Icon className="h-4 w-4" />
            {label}
        </a>
    );
}

export default function HelpIndex({ auth }) {
    const version = auth?.user?.manual_version ?? '最新版';

    return (
        <AuthenticatedLayout header={<h2 className="text-2xl font-semibold text-slate-800">ヘルプ</h2>}>
            <Head title="ヘルプ" />

            <div className="space-y-8">
                <section id="overview" className="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-[1px] shadow-xl">
                    <div className="rounded-3xl bg-white p-8">
                        <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                            <div className="max-w-3xl space-y-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge className="bg-slate-900 text-white">最新版ガイド</Badge>
                                    <Badge variant="outline">ver. {version}</Badge>
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">RAKUSHIRU Cloud 操作ガイド</h1>
                                    <p className="mt-3 text-sm leading-7 text-slate-600">
                                        今回の改修で、工数管理、失注/追跡期限、個別キャパ、事業区分分析、見積一覧の運用ルールが変わっています。
                                        このページは、いまの仕様に合わせて「どこで何を判断するか」を整理した最新版です。
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {quickLinks.map((link) => (
                                        <QuickLinkButton key={link.id} {...link} />
                                    ))}
                                </div>
                            </div>

                            <Card className="w-full max-w-sm border-slate-200 bg-slate-50 shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <CircleCheckBig className="h-5 w-5 text-emerald-600" />
                                        先に押さえるルール
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm leading-6 text-slate-700">
                                        {overviewRules.map((rule) => (
                                            <li key={rule} className="flex gap-2">
                                                <span className="mt-2 h-1.5 w-1.5 rounded-full bg-slate-400" />
                                                <span>{rule}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    {highlights.map((item) => (
                        <Card key={item.title} className={`${item.tone} border shadow-none`}>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base text-slate-900">{item.title}</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm leading-6 text-slate-700">
                                {item.description}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                {sectionGroups.map((group) => {
                    const Icon = group.icon;
                    return (
                        <section id={group.id} key={group.id} className="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-center gap-3">
                                <div className="rounded-2xl bg-slate-100 p-3 text-slate-700">
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="text-2xl font-semibold text-slate-900">{group.title}</h2>
                                    <p className="text-sm text-slate-500">クリックして詳細を開きます</p>
                                </div>
                            </div>

                            <Accordion type="multiple" className="rounded-2xl border border-slate-200 bg-slate-50/60 px-4">
                                {group.sections.map((section, index) => (
                                    <AccordionItem key={section.title} value={`${group.id}-${index}`}>
                                        <AccordionTrigger className="text-left text-base font-semibold text-slate-900 hover:no-underline">
                                            {section.title}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <div className="rounded-2xl bg-white p-4">
                                                <ul className="space-y-3 text-sm leading-7 text-slate-700">
                                                    {section.items.map((item) => (
                                                        <li key={item} className="flex gap-3">
                                                            <span className="mt-2 h-1.5 w-1.5 rounded-full bg-slate-400" />
                                                            <span>{item}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                ))}
                            </Accordion>
                        </section>
                    );
                })}

                <section className="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    <div className="flex items-start gap-3">
                        <TriangleAlert className="mt-0.5 h-5 w-5 text-amber-700" />
                        <div className="space-y-2 text-sm leading-7 text-amber-900">
                            <div className="font-semibold">運用上の注意</div>
                            <ul className="space-y-2">
                                <li>・第1種を工数として扱わないこと。ここを間違えると空き工数と過負荷判定が崩れます。</li>
                                <li>・担当者按分は見積保存を止めませんが、未設定のままではダッシュボードと見積シミュレーションの精度が落ちます。</li>
                                <li>・見積一覧の期限超過は、失注か追跡継続かを整理するための運用機能です。受注済と失注済は対象外です。</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="text-xl font-semibold text-slate-900">困ったときの見方</h2>
                            <p className="text-sm text-slate-500">どの画面を見るべきかを最後にまとめています。</p>
                        </div>
                    </div>
                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader>
                                <CardTitle className="text-base">経営数値を見たい</CardTitle>
                                <CardDescription>売上、粗利、前年比、資金繰り、工数</CardDescription>
                            </CardHeader>
                            <CardContent className="text-sm text-slate-700">ダッシュボードを見ます。事業区分分析もここです。</CardContent>
                        </Card>
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader>
                                <CardTitle className="text-base">案件をさばきたい</CardTitle>
                                <CardDescription>承認待ち、失注、追跡期限、MF未発行</CardDescription>
                            </CardHeader>
                            <CardContent className="text-sm text-slate-700">見積一覧 `/quotes` を見ます。保存ビューと工数注意を使います。</CardContent>
                        </Card>
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader>
                                <CardTitle className="text-base">明細や工数を直したい</CardTitle>
                                <CardDescription>品目、担当者按分、承認ルート</CardDescription>
                            </CardHeader>
                            <CardContent className="text-sm text-slate-700">見積作成/編集画面で修正します。商品や分類は商品管理で直します。</CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
