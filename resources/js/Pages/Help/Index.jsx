import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { RefreshCw, Plug, Info, ShieldCheck, Workflow, AlertTriangle, Flag, CheckCircle, PenSquare, Send, FileCheck2 } from 'lucide-react';

const syncTimings = [
    {
        title: 'ダッシュボード',
        description: '画面表示時に Money Forward の取引先同期を自動実行します。完了すると最新の取引先が ToDo や選択肢に反映されます。',
        color: 'bg-gradient-to-r from-sky-50 to-sky-100 border-sky-200',
    },
    {
        title: '見積管理',
        description: '一覧を開くたびに最新の Money Forward 見積を自動取得。右上の「MF同期」ボタンで手動差分同期も可能です。',
        color: 'bg-gradient-to-r from-amber-50 to-amber-100 border-amber-200',
    },
    {
        title: '請求・売掛管理',
        description: 'ページ読み込みで Money Forward 請求一覧を同期し、ローカル請求書とマージ表示します。画面上部の「MF同期」を押すと再取得します。',
        color: 'bg-gradient-to-r from-emerald-50 to-emerald-100 border-emerald-200',
    },
    {
        title: '商品管理',
        description: '一覧表示時にローカル商品と Money Forward 品目を突き合わせて同期。ボタンから一括／個別同期を実行できます。',
        color: 'bg-gradient-to-r from-purple-50 to-purple-100 border-purple-200',
    },
];

const screenTips = [
    {
        name: 'ダッシュボード',
        points: [
            '自動同期された取引先をもとに ToDo や通知が更新されます。',
            '同期エラー時は画面右上に警告が表示されるので、再同期または後述のアカウント連携を実施してください。',
        ],
    },
    {
        name: '見積管理（一覧）',
        points: [
            '「MF同期」ボタンは Money Forward API のレート制限に配慮した差分同期です。更新・削除した見積が 1～2 分で反映されます。',
            'ローカルで削除すると一覧から非表示になりますが、Money Forward 上の見積は残ります。MF 側の削除が必要な場合は Money Forward 管理画面で実施してください。',
            '金額編集や承認ステータス変更後は自動的にローカル DB へ保存され、必要に応じて MF 送信メニューから送信します。',
            '見積書発行は Money Forward 側でのみ可能です。ローカルで承認登録した後、「MFで見積書発行」ボタンから Money Forward に遷移して作成します。',
        ],
    },
    {
        name: '見積編集・承認フロー',
        points: [
            '見積作成手順：①基礎情報・明細を入力 → ②「下書き保存」で草稿確定 → ③承認ルートを設定 → ④「承認申請」で次承認者へ回付します。',
            '承認申請を送るとローカルの承認状態が更新され、担当者の ToDo に反映されます。申請ステータスは見積番号横のバッジで確認できます。',
            '承認担当者は詳細画面の「承認する」ボタンから承認／差戻しを選択します。差戻し時はコメントを必ず入力してください。',
            '承認済み見積を編集するとステータスが自動的に「承認待ち（再申請）」へ戻り、全承認者の再承認が必要になります。緊急修正時はチャット等で関係者に通知してください。',
            '承認取消は見積詳細の「承認申請を取り消す」から実行できます。取消時は承認履歴に記録され、再申請が可能になります。',
            '「MFで見積書発行」ボタンで Money Forward に見積を作成します。発行後の修正はローカルと MF の両方で行う必要があります。',
            '見積を請求に変換すると、ローカルの請求草稿が生成され、必要に応じて Money Forward 請求へ送信できます。変換後に明細を修正した場合は再度同期してください。',
        ],
    },
    {
        name: '請求・売掛管理',
        points: [
            'ローカル請求を編集すると自動で差分が保存されます。「MFへ送信」から Money Forward 請求に変換すると、同期フラグが更新されます。',
            'Money Forward 側で行った変更は次回同期時に反映されます。未同期のローカル請求にはフラグが表示されます。',
            'ローカルで削除した請求は Money Forward へ削除依頼は送信しません。連携済み請求の削除は MF 管理画面で行ってください。',
        ],
    },
    {
        name: '商品管理',
        points: [
            '商品を新規作成すると `<商品分類コード>-XXX` でSKUが採番され、同時に Money Forward 同期対象に登録されます。',
            '同期時に「第1種/第5種事業」などの事業区分が Money Forward へも付帯情報として送信されます（API 側で保持されない場合でもログに残ります）。',
            '商品を削除するとローカル登録が非活性化され、次回同期で Money Forward 側も非表示にするリクエストを送ります。',
        ],
    },
];

const estimateFlow = [
    {
        icon: Flag,
        title: '1. 下書き作成',
        description: '顧客・部門・案件名を入力し、明細を追加。保存前でも途中保存が可能です。',
        detail: '「下書き保存」でSKU採番・発番が完了し、ドラフトとして一覧に表示されます。',
        accent: 'from-blue-50 to-blue-100 border-blue-200',
    },
    {
        icon: PenSquare,
        title: '2. 承認ルート設定',
        description: '承認者シーケンスを設定し、必要ならコメントや添付資料を追加。',
        detail: '部署標準フローを読み込むか、個別に承認者を並べ替えてください。',
        accent: 'from-indigo-50 to-indigo-100 border-indigo-200',
    },
    {
        icon: Send,
        title: '3. 承認申請',
        description: '「承認申請」ボタンで次承認者に通知。ToDo とメール通知で連絡されます。',
        detail: '申請中はローカルの編集が制限されます。差し戻し・取消で再編集が可能です。',
        accent: 'from-amber-50 to-amber-100 border-amber-200',
    },
    {
        icon: CheckCircle,
        title: '4. 承認処理',
        description: '承認担当者は詳細画面から承認／差戻し／取消を選択。コメントが履歴に残ります。',
        detail: '全員承認でステータスが「承認済」に変わり、下部のMoney Forward操作が有効化。',
        accent: 'from-emerald-50 to-emerald-100 border-emerald-200',
    },
    {
        icon: FileCheck2,
        title: '5. Money Forward 発行',
        description: '「MFで見積書発行」を押して Money Forward に遷移し、見積書を発行します。',
        detail: 'ローカルでの発行はできません。修正が必要な場合は再承認後に再発行し、不要になったMF見積は手動で無効化してください。',
        accent: 'from-purple-50 to-purple-100 border-purple-200',
    },
    {
        icon: AlertTriangle,
        title: '6. 修正・再申請',
        description: '承認後に編集するとステータスが「承認待ち（再申請）」へ自動で戻ります。',
        detail: '緊急修正時はチャット等で関係者に周知し、必要に応じて Money Forward 側の見積も更新してください。',
        accent: 'from-rose-50 to-rose-100 border-rose-200',
    },
];

const accountLinks = [
    {
        title: 'Money Forward アカウント連携が必要な理由',
        description: '各モジュールで Money Forward API を利用するためには、利用者ごとに OAuth 連携を完了させる必要があります。アクセストークンは 24 時間で更新、90 日で再認証が必要です。',
        items: [
            '初回連携：画面の「MF同期」や「Money Forwardへ送信」ボタンを押すと、認可画面が開きます。承認後は自動的に元の画面に戻ります。',
            'トークン失効時：自動同期が失敗し、画面上部に再認証メッセージが表示されます。案内に従い再度認証してください。',
            '権限スコープ：見積（read/write）、請求（read/write）、商品（read/write）、取引先（read/write）が必要です。申請時にまとめて許可してください。',
        ],
        color: 'bg-white border border-orange-300 shadow-[0_0_20px_rgba(249,115,22,0.2)]',
        icon: ShieldCheck,
    },
    {
        title: '連携モジュール一覧',
        description: '各画面で必要となる Money Forward の連携ポイントです。操作前に対象スコープが許可済みか確認してください。',
        items: [
            'ダッシュボード：取引先（Partners）同期。初回に「MF取引先連携」を求められます。',
            '見積管理：見積 read/write。MF発行・請求変換・PDF表示で必要。',
            '請求・売掛管理：請求 read/write。ローカル請求→MF送信やPDF取得時に required。',
            '商品管理：品目 read/write。自動・手動同期の双方で必要。',
        ],
        color: 'bg-white border border-sky-300 shadow-[0_0_20px_rgba(14,165,233,0.25)]',
        icon: Plug,
    },
];

export default function HelpIndex({ auth }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-2xl font-semibold text-slate-800">ヘルプ & 操作マニュアル</h2>}
        >
            <Head title="ヘルプ" />
            <div className="space-y-8">
                <section className="rounded-3xl bg-gradient-to-r from-indigo-500 via-sky-500 to-cyan-500 p-[1px] shadow-xl">
                    <div className="rounded-3xl bg-white/95 p-8 backdrop-blur-sm">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div className="flex items-center gap-3">
                                    <Badge className="bg-indigo-600 text-white text-xs uppercase tracking-wider px-3 py-1">社員向けドキュメント</Badge>
                                    <span className="text-sm text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full">ver. {auth?.user?.manual_version ?? '2025.03'}</span>
                                </div>
                                <h1 className="mt-4 text-3xl font-bold text-slate-900">RAKUSHIRU Cloud 運用ガイド</h1>
                                <p className="mt-2 text-slate-600 leading-relaxed">
                                    このページでは Money Forward との同期タイミング・アカウント連携・画面ごとの操作ポイントなど、
                                    社内運用で押さえておきたい情報をまとめています。新入社員・引継ぎ時のトレーニング資料としてご利用ください。
                                </p>
                            </div>
                            <div className="rounded-3xl bg-gradient-to-br from-indigo-500 to-sky-600 text-white p-6 shadow-lg w-full max-w-sm">
                                <div className="flex items-center gap-3">
                                    <RefreshCw className="h-10 w-10" />
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-white/70">同期ステータス</p>
                                        <p className="text-lg font-semibold">すべて正常</p>
                                    </div>
                                </div>
                                <div className="my-4 h-px bg-white/20" />
                                <ul className="space-y-2 text-sm text-white/90">
                                    <li className="flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-300" />取引先同期：自動</li>
                                    <li className="flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-300" />見積同期：画面表示＋手動ボタン</li>
                                    <li className="flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-300" />商品同期：初期表示＋個別同期</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Info className="h-5 w-5 text-slate-500" />
                        <h2 className="text-xl font-semibold text-slate-800">同期タイミングと手動操作</h2>
                    </div>
                    <p className="text-sm text-slate-600">
                        システムは画面ごとに Money Forward との同期タイミングを持っています。自動同期で追いつかないケース（急ぎの反映・失敗後の再試行など）は手動同期を実行してください。
                    </p>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {syncTimings.map((item) => (
                            <Card key={item.title} className={`${item.color} border`}>
                                <CardHeader>
                                    <CardTitle className="text-lg text-slate-800">{item.title}</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-slate-600 leading-relaxed">
                                    {item.description}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <div className="rounded-2xl border border-amber-300 bg-amber-50/70 p-4 text-sm text-amber-700 flex items-start gap-3">
                        <AlertTriangle className="h-5 w-5 mt-0.5 flex-shrink-0" />
                        <div>
                            <p className="font-semibold">手動同期が必要になる代表的なケース</p>
                            <ul className="mt-2 list-disc pl-5 space-y-1">
                                <li>Money Forward 側で直接編集した内容をアプリに即時反映したいとき</li>
                                <li>トークン失効や通信エラーで自動同期が失敗した後にリトライしたいとき</li>
                                <li>承認後すぐに請求へ進めたいなど、リアルタイム性が必要な業務フロー</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Workflow className="h-5 w-5 text-slate-500" />
                        <h2 className="text-xl font-semibold text-slate-800">見積ワークフロー（社内承認 → Money Forward 発行）</h2>
                    </div>
                    <p className="text-sm text-slate-600">以下の順序で操作すると、社内承認と Money Forward 発行がスムーズに行えます。各ステップはステータスバッジとして一覧にも反映されます。</p>
                    <div className="relative mx-auto max-w-5xl">
                        <div className="absolute left-6 top-6 bottom-6 hidden border-l-2 border-dashed border-slate-200 md:block" aria-hidden="true" />
                        <div className="space-y-6">
                            {estimateFlow.map((step, index) => {
                                const Icon = step.icon;
                                return (
                                    <div
                                        key={step.title}
                                        className={`relative border ${step.accent} rounded-2xl p-5 shadow-sm transition hover:shadow-md bg-white/90`}
                                    >
                                        <div className="flex flex-col gap-3 md:flex-row md:items-center">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-inner border border-white/60">
                                                    <Icon className="h-6 w-6 text-slate-700" />
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Step {index + 1}</p>
                                                    <h3 className="text-lg font-semibold text-slate-800">{step.title}</h3>
                                                </div>
                                            </div>
                                            <div className="ml-auto hidden text-sm font-medium text-slate-500 md:block">{step.description}</div>
                                        </div>
                                        <div className="mt-3 text-sm leading-relaxed text-slate-700 md:hidden">{step.description}</div>
                                        <div className="mt-3 rounded-xl bg-white/80 p-4 text-sm text-slate-600">
                                            {step.detail}
                                        </div>
                                        {index < estimateFlow.length - 1 && (
                                            <div className="md:absolute md:left-[22px] md:top-full md:h-6 md:w-4 md:translate-y-1">
                                                <div className="mx-auto hidden h-full w-[2px] bg-gradient-to-b from-slate-200 to-transparent md:block" />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Plug className="h-5 w-5 text-slate-500" />
                        <h2 className="text-xl font-semibold text-slate-800">Money Forward アカウント連携</h2>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {accountLinks.map(({ title, description, items, color, icon: Icon }) => (
                            <Card key={title} className={`${color} rounded-2xl`}>
                                <CardHeader className="flex flex-row items-center gap-3">
                                    <div className="rounded-xl bg-slate-900/10 p-2"><Icon className="h-6 w-6 text-slate-700" /></div>
                                    <div>
                                        <CardTitle className="text-lg text-slate-800">{title}</CardTitle>
                                        <p className="text-xs text-slate-500 mt-1">{description}</p>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm text-slate-700 leading-relaxed list-disc pl-5">
                                        {items.map((point) => (
                                            <li key={point}>{point}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Workflow className="h-5 w-5 text-slate-500" />
                        <h2 className="text-xl font-semibold text-slate-800">画面ごとの基本操作と同期の挙動</h2>
                    </div>
                    <p className="text-sm text-slate-600">
                        各モジュールの代表的な操作と、Money Forward とのデータ同期に関する注意点をまとめています。編集・削除時の扱いも確認してください。
                    </p>
                    <div className="grid gap-4">
                        {screenTips.map((section) => (
                            <Card key={section.name} className="border border-slate-200 shadow-sm">
                                <CardHeader className="bg-slate-50/70">
                                    <CardTitle className="text-slate-800">{section.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm text-slate-700 leading-relaxed list-disc pl-5">
                                        {section.points.map((line) => (
                                            <li key={line}>{line}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                    <h3 className="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5 text-emerald-500" />
                        運用ベストプラクティス
                    </h3>
                    <div className="mt-3 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                        <div className="rounded-xl bg-white p-4 shadow-inner">
                            <p className="font-semibold text-emerald-600">同期ログを定期チェック</p>
                            <p className="mt-2 leading-relaxed">エラー時は `storage/logs/laravel.log` に Money Forward API のレスポンスが記録されます。日次でざっと確認しておくと、トークン失効や権限制限に素早く気付けます。</p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-inner">
                            <p className="font-semibold text-emerald-600">権限管理とアカウント棚卸し</p>
                            <p className="mt-2 leading-relaxed">退職・異動者のアカウントは Money Forward 側の連携解除を忘れずに。システム上のユーザーを無効化しただけではトークンが残るため、半年に一度は棚卸しを実施してください。</p>
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
