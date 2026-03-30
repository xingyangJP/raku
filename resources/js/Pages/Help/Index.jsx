import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    BarChart3,
    BookOpen,
    Boxes,
    Brain,
    CircleCheckBig,
    ClipboardList,
    FileText,
    Link as LinkIcon,
    Package,
    RefreshCw,
    Settings2,
    ShoppingCart,
    TriangleAlert,
    Users,
    Workflow,
} from 'lucide-react';

const navigationGroups = [
    { id: 'overview', label: 'まずここ', icon: BookOpen },
    { id: 'dashboard', label: 'ダッシュボード', icon: BarChart3 },
    { id: 'estimates', label: '見積作成・編集', icon: FileText },
    { id: 'quotes', label: '見積一覧 /quotes', icon: ClipboardList },
    { id: 'orders', label: '注文書一覧 /orders', icon: ShoppingCart },
    { id: 'products', label: '商品管理', icon: Package },
    { id: 'settings', label: '設定 / キャパ', icon: Settings2 },
    { id: 'sync', label: 'MF連携', icon: RefreshCw },
    { id: 'faq', label: '困ったとき', icon: Workflow },
];

const quickGuides = [
    {
        title: '経営数値を見る',
        subtitle: '売上・粗利・工数・資金繰り・AI総評',
        destination: 'ダッシュボード',
        anchor: '#dashboard',
        icon: Brain,
        tone: 'border-slate-200 bg-slate-50',
    },
    {
        title: '見積を作る / 直す',
        subtitle: '品目、承認、担当者按分、負荷確認',
        destination: '見積作成・編集',
        anchor: '#estimates',
        icon: FileText,
        tone: 'border-sky-200 bg-sky-50',
    },
    {
        title: '営業案件をさばく',
        subtitle: '承認待ち、失注、追跡期限、MF未発行',
        destination: '見積一覧 /quotes',
        anchor: '#quotes',
        icon: ClipboardList,
        tone: 'border-amber-200 bg-amber-50',
    },
    {
        title: '受注後を回す',
        subtitle: '納期、回収予定、担当、次アクション',
        destination: '注文書一覧 /orders',
        anchor: '#orders',
        icon: ShoppingCart,
        tone: 'border-emerald-200 bg-emerald-50',
    },
];

const highlights = [
    {
        title: '総合タブだけ AI 経営総評',
        description: 'ダッシュボードは大幅更新済みです。最上部の総評は対象年月ごとに日次保存した AI 分析で、section 別の分析/アラートはルールベースです。',
        tone: 'border-violet-200 bg-violet-50',
    },
    {
        title: '担当者割り当ては必須運用',
        description: '見積編集画面は大幅更新済みです。第5種明細の担当者割り当てを前提に、負荷シミュレーションと空き状況を判断します。',
        tone: 'border-rose-200 bg-rose-50',
    },
    {
        title: '個別キャパ設定が必須',
        description: '工数管理は設定画面でユーザー別の実質稼働可能工数を必ず入れてください。未設定だと空き状況判断が実態とずれます。',
        tone: 'border-sky-200 bg-sky-50',
    },
    {
        title: '一覧の役割を分けた',
        description: '見積一覧は営業運用、注文書一覧は受注後の実行/回収管理、経営分析はダッシュボードへ寄せています。',
        tone: 'border-emerald-200 bg-emerald-50',
    },
];

const coreRules = [
    '第1種 = 仕入れ販売。金額管理対象ですが、工数計画・担当者按分の対象外です。',
    '第5種 = 開発/設計など。担当者按分、空き工数、負荷シミュレーションの対象です。担当者割り当ては必須運用として扱ってください。',
    '一式表示は表示切替だけです。工数対象かどうかの判定には使いません。',
    '総合タブの経営総評だけ AI 日次分析です。開発/販売/保守タブの分析とアラートはルールベースです。',
    '見積一覧の期限超過モーダルは、受注済・失注済を除いた未受注案件だけを対象にします。',
    '要件定義書からの AI 自動見積を使うときも、生成結果をそのまま確定せず必ずヘルプに沿って見直してください。',
];

const sectionGroups = [
    {
        id: 'dashboard',
        title: 'ダッシュボード',
        icon: BarChart3,
        summary: '経営判断用です。総合タブで AI 総評、各タブで売上・粗利・工数・資金繰りを見ます。',
        chips: ['経営判断', 'AI総評', '工数/資金繰り'],
        sections: [
            {
                title: '画面の役割',
                items: [
                    'ダッシュボードは経営判断用です。売上、粗利、前年比、資金繰り、事業区分、空き状況をまとめて見ます。',
                    '営業の案件処理は /quotes、受注後の納期/回収管理は /orders に分けています。',
                ],
            },
            {
                title: 'AI経営総評の見方',
                items: [
                    '経営ダッシュボード最上部の総評は、総合タブだけに出る AI 日次分析です。表示中の年月を切り替えると、その年月の総評に変わります。',
                    'AI は当日の最初のアクセス時だけ生成して保存し、当日中は同じ結果を再利用します。API未設定や失敗時はルール分析へ自動 fallback します。',
                    '総評は「何がポイントか」「何を改善すべきか」を短く読むための要約です。詳細数値の根拠は下のカードやグラフで確認します。',
                ],
            },
            {
                title: 'tab別の分析/アラート',
                items: [
                    '総合以外の tab は、現時点では AI ではなくルールベースです。開発は稼働、仕入れ販売は粗利/回収、保守は継続性を中心に見ます。',
                    'アラートは予算差異、粗利差異、工数充足率、ネットCFなどのしきい値で出ています。',
                ],
            },
            {
                title: '工数の見方',
                items: [
                    '設定画面で個別キャパ設定済みの担当者を母集団に、担当者別の空き状況を集計します。',
                    '着手日と納品日がある案件の工数は、開始月から納品月まで均等配賦します。着手日が無い場合は納期月へ一括、納期未設定は未配賦です。',
                    '未割当工数は、第1種以外で担当者按分が無い明細を、delivery_date → due_date → issue_date の順で当月判定して集計します。',
                    '見積で担当者按分を入れないと、空き状況と負荷シミュレーションは実態より甘く見えます。',
                ],
            },
            {
                title: '資金繰りの見方',
                items: [
                    '受注済み案件は注文書納期を優先し、納期月請求・翌月入金の近似で回収予測を出します。',
                    '未受注案件は見込みなので、確定資金繰りとは分けて読みます。',
                    '実入金日までは持っていないため、現状は予測管理です。',
                ],
            },
            {
                title: '事業区分分析',
                items: [
                    '旧「事業区分集計」はダッシュボードへ統合しました。',
                    '分析はダッシュボード、商品の事業区分修正は商品管理で行います。',
                ],
            },
        ],
    },
    {
        id: 'estimates',
        title: '見積作成・編集',
        icon: FileText,
        summary: '明細、承認、担当者按分、負荷シミュレーションをまとめて扱う画面です。',
        chips: ['見積作成', '担当者按分', '負荷判断'],
        sections: [
            {
                title: '明細入力の基本',
                items: [
                    '明細は商品マスタを選んで作ります。旧見積は product_id が無くても code/name から既存商品へ補完して表示します。',
                    '第1種明細は担当者設定不要、第5種明細は担当者按分の対象です。',
                    '赤い担当者警告は「第1種以外で未設定」のときだけ出ます。',
                ],
            },
            {
                title: '担当者按分',
                items: [
                    '現在の運用では、第5種明細の担当者割り当ては必須前提です。ここが空だと、ダッシュボードと工数判断が崩れます。',
                    '複数人で分担する場合は担当者を追加し、按分率を入力します。保存時に合計100%へ正規化します。',
                    '旧見積でヘッダ担当者しか無い場合は、編集画面で初期表示時に 100% 按分として補完します。',
                    '第1種は工数管理対象外なので、担当者按分の赤警告は出しません。',
                ],
            },
            {
                title: '要件定義書からの AI 自動見積',
                items: [
                    'Google Drive の要件定義書を選ぶと、AI が文書を解析して要件整理、ドラフト明細、備考案を生成します。',
                    'この機能は見積編集画面の大幅アップデートの一部です。生成後は明細、担当者割り当て、納期、備考を必ず人が見直してください。',
                    '要件定義書リンクは添付と AI 解析元を兼用します。重複入力は不要です。',
                ],
            },
            {
                title: '担当者負荷シミュレーション',
                items: [
                    '対象月の合計キャパ、既存余力、この見積の追加工数、未割当工数を確認できます。',
                    '個人ごとの月間キャパ設定をそのまま使うため、営業や総務は小さいキャパで評価されます。',
                    '担当者未設定の明細があると、シミュレーション精度は落ちます。',
                ],
            },
            {
                title: '保存前の見方',
                items: [
                    '未設定警告は保存を止めませんが、ダッシュボードと工数判断に影響します。',
                    '第1種にまで担当者を入れないこと。ここを混ぜると空き工数が壊れます。',
                ],
            },
        ],
    },
    {
        id: 'quotes',
        title: '見積一覧 /quotes',
        icon: ClipboardList,
        summary: '営業・見積運用の作業台です。承認、失注、追跡期限、工数注意をここで処理します。',
        chips: ['営業運用', '保存ビュー', '期限超過'],
        sections: [
            {
                title: '画面の役割',
                items: [
                    '承認待ち、失注、追跡期限、MF未発行、担当未設定などをさばくための一覧です。',
                    '経営分析を見る画面ではありません。経営数値はダッシュボードを見ます。',
                ],
            },
            {
                title: '保存ビュー',
                items: [
                    '保存ビューは見積一覧の直上にあり、一覧に対するフィルタをワンクリックで切り替えます。',
                    '全件 / 承認待ち / 自分担当 / MF未発行 / 期限超過 / 失注 / 要フォローを使います。',
                    '一覧の内容が切り替わったかどうかは、この保存ビューの active 状態で確認します。',
                ],
            },
            {
                title: '工数注意',
                items: [
                    '第1種以外で担当者が無い明細は「担当未割当」、按分が空なら「按分未設定」として優先表示します。',
                    '未設定が無ければ、今月/来月の受注ベース工数から余力あり・逼迫・過負荷を出します。',
                    '第1種は工数管理対象外なので、担当者未設定ラベルは出しません。',
                ],
            },
            {
                title: '期限超過・失注・追跡期限',
                items: [
                    '見積期限を過ぎた未受注案件は、一覧アクセス時に判断モーダルを出します。',
                    '「失注にする」か「まだ追う」を選べます。「まだ追う」は追跡期限を延長して次回確認日として扱います。',
                    '複数件ある場合は古い期限順に連続処理できます。受注済と失注済は対象外です。',
                ],
            },
        ],
    },
    {
        id: 'orders',
        title: '注文書一覧 /orders',
        icon: ShoppingCart,
        summary: '受注後の実行管理・回収管理の画面です。',
        chips: ['受注後運用', '納期', '回収'],
        sections: [
            {
                title: '画面の役割',
                items: [
                    '受注済み案件の納期、回収予定、担当、工数を追う画面です。',
                    '見積一覧 /quotes は受注前運用、注文書一覧 /orders は受注後運用、という役割分担です。',
                ],
            },
            {
                title: '見るべきKPI',
                items: [
                    '今月納期件数、今月納期受注額、今月回収予定額、計画工数を優先して見ます。',
                    '粗利総額や全社資金繰りの深掘りはダッシュボードへ寄せています。',
                ],
            },
            {
                title: '次アクションの考え方',
                items: [
                    '請求準備、回収確認、納期確認、担当設定など、受注後に何を処理するかを一覧で判断します。',
                    '第1種/第5種の混在案件でも、受注後は納期・回収・担当の整合を優先して見ます。',
                ],
            },
        ],
    },
    {
        id: 'products',
        title: '商品管理',
        icon: Boxes,
        summary: '商品マスタ、事業区分、分類を正しく保つ画面です。',
        chips: ['商品マスタ', '事業区分', '分類コード'],
        sections: [
            {
                title: '商品分類と分類コード',
                items: [
                    '商品分類は A〜G の分類コードを持ち、SKU採番の基準になります。',
                    '分類追加時の英字コードは自動採番です。手入力ではありません。',
                ],
            },
            {
                title: '事業区分の意味',
                items: [
                    '第1種は仕入れ販売なので、見積では担当者設定不要です。',
                    '第5種は工数対象なので、見積作成/編集とダッシュボードへ影響します。',
                    '商品管理では、事業区分を正しく保つことが最重要です。',
                ],
            },
        ],
    },
    {
        id: 'settings',
        title: '設定 / キャパ',
        icon: Users,
        summary: 'ユーザー別の月間開発キャパを設定し、工数判断の基準を揃えます。',
        chips: ['個別キャパ', '対象人数', '全社合計'],
        sections: [
            {
                title: '何を設定するか',
                items: [
                    '標準人数設定は使わず、ユーザーごとの月間開発キャパを設定します。',
                    '現在はこの設定が必須前提です。未設定のままだと、空き状況と負荷シミュレーションが現場実態からずれます。',
                    '営業や総務は小さいキャパ、開発中心の人は大きいキャパで設定してください。',
                ],
            },
            {
                title: 'どこに反映されるか',
                items: [
                    'ダッシュボードの担当者別空き状況',
                    '見積作成/編集の担当者負荷シミュレーション',
                    '見積一覧の受注ベース工数の目安',
                ],
            },
        ],
    },
    {
        id: 'sync',
        title: 'Money Forward連携',
        icon: LinkIcon,
        summary: '画面ごとに同期対象が違います。',
        chips: ['取引先', '見積', '請求'],
        sections: [
            {
                title: '同期の考え方',
                items: [
                    'ダッシュボードは取引先、見積一覧は見積、請求管理は請求、商品管理は品目の同期が中心です。',
                    '外部で直接更新したものを取り込みたいときは、対象画面で手動同期を使います。',
                ],
            },
            {
                title: 'ダッシュボードの取引先同期',
                items: [
                    'ダッシュボードの取引先同期はクールダウン付き自動同期です。必要なときだけ手動更新できます。',
                    '最終同期時刻と次回自動同期可能時刻は画面に表示されます。',
                ],
            },
            {
                title: '再認証が必要なとき',
                items: [
                    'トークンが失効すると再認証が必要です。再認証メッセージが出た画面からやり直してください。',
                ],
            },
        ],
    },
];

const troubleCards = [
    {
        title: '経営数値を見たい',
        description: '売上、粗利、前年比、資金繰り、AI総評',
        answer: 'ダッシュボードを見ます。',
    },
    {
        title: '案件をさばきたい',
        description: '承認待ち、失注、追跡期限、MF未発行',
        answer: '見積一覧 /quotes を見ます。',
    },
    {
        title: '受注後の納期や回収を見たい',
        description: '納期、回収予定、請求準備、担当',
        answer: '注文書一覧 /orders を見ます。',
    },
    {
        title: '明細や工数を直したい',
        description: '品目、担当者按分、負荷シミュレーション',
        answer: '見積作成/編集画面を使います。',
    },
];

function SideNavLink({ id, label, icon: Icon }) {
    return (
        <a
            href={`#${id}`}
            className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
        >
            <Icon className="h-4 w-4 text-slate-500" />
            <span>{label}</span>
        </a>
    );
}

function SectionBlock({ group }) {
    const Icon = group.icon;

    return (
        <section id={group.id} className="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div className="flex items-start gap-4">
                    <div className="rounded-2xl bg-slate-100 p-3 text-slate-700">
                        <Icon className="h-5 w-5" />
                    </div>
                    <div className="space-y-1">
                        <h2 className="text-2xl font-semibold text-slate-900">{group.title}</h2>
                        <p className="text-sm leading-6 text-slate-500">{group.summary}</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {group.chips.map((chip) => (
                        <Badge key={chip} variant="outline" className="rounded-full border-slate-200 bg-slate-50 text-slate-600">
                            {chip}
                        </Badge>
                    ))}
                </div>
            </div>

            <Accordion type="multiple" className="mt-6 rounded-2xl border border-slate-200 bg-slate-50/70 px-4">
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
}

export default function HelpIndex({ appVersion }) {
    const version = appVersion ?? '最新版';

    return (
        <AuthenticatedLayout header={<h2 className="text-2xl font-semibold text-slate-800">ヘルプ</h2>}>
            <Head title="ヘルプ" />

            <div className="space-y-8">
                <section id="overview" className="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-[1px] shadow-xl">
                    <div className="rounded-3xl bg-white p-8">
                        <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                            <div className="max-w-4xl space-y-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge className="bg-slate-900 text-white">最新版ガイド</Badge>
                                    <Badge variant="outline">ver. {version}</Badge>
                                    <Badge variant="outline" className="border-violet-200 bg-violet-50 text-violet-700">2026年3月改定</Badge>
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">RAKUSHIRU Cloud 操作ガイド</h1>
                                    <p className="mt-3 text-sm leading-7 text-slate-600">
                                        今回の大型アップデートで、ダッシュボード、見積編集、工数管理、要件定義書からの AI 自動見積、見積一覧/注文書一覧の判断基準が変わっています。
                                        このページは、現行仕様に合わせて「どこで何を判断するか」を整理した最新版です。更新通知を見た後は、このヘルプを前提に運用してください。
                                    </p>
                                </div>
                            </div>

                            <Card className="w-full max-w-md border-slate-200 bg-slate-50 shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <CircleCheckBig className="h-5 w-5 text-emerald-600" />
                                        先に押さえるルール
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm leading-6 text-slate-700">
                                        {coreRules.map((rule) => (
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

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {quickGuides.map((item) => {
                        const Icon = item.icon;
                        return (
                            <a
                                key={item.title}
                                href={item.anchor}
                                className={`rounded-3xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${item.tone}`}
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div className="space-y-1">
                                        <div className="text-base font-semibold text-slate-900">{item.title}</div>
                                        <div className="text-sm leading-6 text-slate-600">{item.subtitle}</div>
                                    </div>
                                    <div className="rounded-2xl bg-white/80 p-3 text-slate-700 shadow-sm">
                                        <Icon className="h-5 w-5" />
                                    </div>
                                </div>
                                <div className="mt-4 inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                    {item.destination}
                                </div>
                            </a>
                        );
                    })}
                </section>

                <section className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    {highlights.map((item) => (
                        <Card key={item.title} className={`${item.tone} border shadow-none`}>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base text-slate-900">{item.title}</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm leading-6 text-slate-700">{item.description}</CardContent>
                        </Card>
                    ))}
                </section>

                <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="xl:sticky xl:top-24 xl:self-start">
                        <Card className="border-slate-200 shadow-sm">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-lg">目次</CardTitle>
                                <CardDescription>画面別に必要な章へ移動します</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {navigationGroups.map((group) => (
                                    <SideNavLink key={group.id} {...group} />
                                ))}
                            </CardContent>
                        </Card>
                    </aside>

                    <div className="space-y-6">
                        {sectionGroups.map((group) => (
                            <SectionBlock key={group.id} group={group} />
                        ))}

                        <section className="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                            <div className="flex items-start gap-3">
                                <TriangleAlert className="mt-0.5 h-5 w-5 text-amber-700" />
                                <div className="space-y-2 text-sm leading-7 text-amber-900">
                                    <div className="font-semibold">運用上の注意</div>
                                    <ul className="space-y-2">
                                        <li>・第1種を工数として扱わないこと。ここを間違えると空き工数と過負荷判定が崩れます。</li>
                                        <li>・担当者按分は見積保存を止めませんが、未設定のままではダッシュボードと見積判断の精度が落ちます。</li>
                                        <li>・見積一覧の期限超過は、失注か追跡継続かを整理するための運用機能です。受注済と失注済は対象外です。</li>
                                        <li>・総合タブの経営総評だけ AI 日次分析です。その他の分析/アラートは現時点ではルールベースです。</li>
                                    </ul>
                                </div>
                            </div>
                        </section>

                        <section id="faq" className="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="space-y-2">
                                <h2 className="text-2xl font-semibold text-slate-900">困ったときの見方</h2>
                                <p className="text-sm text-slate-500">どの画面を見るべきかを最後にまとめています。</p>
                            </div>
                            <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                {troubleCards.map((card) => (
                                    <Card key={card.title} className="border-slate-200 shadow-none">
                                        <CardHeader>
                                            <CardTitle className="text-base">{card.title}</CardTitle>
                                            <CardDescription>{card.description}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="text-sm leading-6 text-slate-700">{card.answer}</CardContent>
                                    </Card>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
