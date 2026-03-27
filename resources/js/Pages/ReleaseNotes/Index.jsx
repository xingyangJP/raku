import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Bell, BookOpen } from 'lucide-react';

export default function ReleaseNotesIndex() {
    const { releaseNotes } = usePage().props;
    const releaseHistory = releaseNotes?.history ?? [];
    const latestVersion = releaseNotes?.latest?.version ?? '最新版';

    return (
        <AuthenticatedLayout header={<h2 className="text-2xl font-semibold text-slate-800">更新履歴</h2>}>
            <Head title="更新履歴" />

            <div className="space-y-8">
                <section className="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-[1px] shadow-xl">
                    <div className="rounded-3xl bg-white p-8">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div className="max-w-4xl space-y-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge className="bg-slate-950 text-white">最新更新</Badge>
                                    <Badge variant="outline">{latestVersion}</Badge>
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">更新履歴</h1>
                                    <p className="mt-3 text-sm leading-7 text-slate-600">
                                        最新の変更点を時系列で確認するページです。業務フローに影響する大型更新は、ここで内容を確認したうえで
                                        <Link href={route('help.index')} className="mx-1 font-semibold text-slate-900 underline underline-offset-4">
                                            ヘルプ
                                        </Link>
                                        も必ず読んでください。
                                    </p>
                                </div>
                            </div>

                            <Card className="w-full max-w-md border-slate-200 bg-slate-50 shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <BookOpen className="h-5 w-5 text-slate-700" />
                                        確認のしかた
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm leading-6 text-slate-700">
                                    <p>大型アップデートは、最初にこのページで概要を確認します。</p>
                                    <p>その後、運用ルールと操作詳細はヘルプ画面で確認してください。</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    {releaseHistory.map((entry, index) => (
                        <Card key={entry.version} className="border-slate-200 shadow-none">
                            <CardHeader className="pb-3">
                                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {index === 0 && (
                                                <Badge className="bg-slate-950 text-white">
                                                    <Bell className="mr-1 h-3.5 w-3.5" />
                                                    最新
                                                </Badge>
                                            )}
                                            <Badge variant="outline">{entry.version}</Badge>
                                            <Badge variant="outline" className="border-slate-200 bg-slate-50 text-slate-600">
                                                {entry.released_at}
                                            </Badge>
                                        </div>
                                        <CardTitle className="text-xl text-slate-900">{entry.title}</CardTitle>
                                        <CardDescription className="text-sm leading-6">{entry.summary}</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3 text-sm leading-7 text-slate-700">
                                    {(entry.items ?? []).map((item) => (
                                        <li key={`${entry.version}-${item}`} className="flex gap-3">
                                            <span className="mt-2 h-1.5 w-1.5 rounded-full bg-slate-400" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
