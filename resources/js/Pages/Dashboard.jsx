
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { FileText, TrendingUp, CreditCard, ListChecks, BarChart3 } from 'lucide-react';
import EstimateDetailSheet from '@/Components/EstimateDetailSheet';
import { useState, useMemo } from 'react';
import { formatCurrency } from '@/lib/utils';
import SyncButton from '@/Components/SyncButton';

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}年${month}月${day}日`;
};

export default function Dashboard({
    auth,
    toDoEstimates = [],
    partnerSyncFlash = {},
    dashboardMetrics = null,
    salesRanking = [],
}) {
    const { flash } = usePage().props;
    const partnerFlash = partnerSyncFlash || {};
    const partnerFlashMessage = partnerFlash?.message;
    const partnerFlashIsError = partnerFlash?.status === 'error';

    const periods = dashboardMetrics?.periods ?? {};
    const currentPeriodLabel = periods?.current?.label ?? '今月';
    const previousPeriodLabel = periods?.previous?.label ?? '先月';

    const summaryCards = [
        {
            key: 'estimates',
            title: '当月の見積サマリ',
            icon: <FileText className="h-4 w-4 text-blue-900" />,
            iconWrap: 'bg-blue-500',
            accent: 'from-blue-50 to-blue-100',
            current: dashboardMetrics?.estimates?.current ?? 0,
            previous: dashboardMetrics?.estimates?.previous ?? 0,
            subtitle: null,
        },
        {
            key: 'grossProfit',
            title: '当月の粗利サマリ',
            subtitle: '請求書に変換済みの見積ベース',
            icon: <TrendingUp className="h-4 w-4 text-emerald-900" />,
            iconWrap: 'bg-emerald-500',
            accent: 'from-emerald-50 to-emerald-100',
            current: dashboardMetrics?.gross_profit?.current ?? 0,
            previous: dashboardMetrics?.gross_profit?.previous ?? 0,
        },
        {
            key: 'sales',
            title: '当月の売上サマリ',
            subtitle: '請求書ベース',
            icon: <CreditCard className="h-4 w-4 text-purple-900" />,
            iconWrap: 'bg-purple-500',
            accent: 'from-purple-50 to-purple-100',
            current: dashboardMetrics?.sales?.current ?? 0,
            previous: dashboardMetrics?.sales?.previous ?? 0,
        },
    ];

    const hasSalesRanking = Array.isArray(salesRanking) && salesRanking.length > 0;

    // Filter state: 'all' | 'mine'
    const [filter, setFilter] = useState('all');
    const [openSheet, setOpenSheet] = useState(false);
    const [selectedEstimate, setSelectedEstimate] = useState(null);

    const filteredTasks = useMemo(() => {
        if (filter === 'mine') {
            return (toDoEstimates || []).filter(task => task.is_current_user_in_flow);
        }
        return toDoEstimates || [];
    }, [filter, toDoEstimates]);

    const openDetail = (task) => {
        if (task && task.estimate) {
            setSelectedEstimate(task.estimate);
            setOpenSheet(true);
        }
    };

    const handleFetchPartners = () => {
        router.get(route('partners.sync'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                {flash?.success && (
                    <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span className="block sm:inline">{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span className="block sm:inline">{flash.error}</span>
                    </div>
                )}
                {partnerFlashMessage && (
                    <div
                        className={`${partnerFlashIsError ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'} px-4 py-3 rounded relative`}
                        role="alert"
                    >
                        <span className="block sm:inline">{partnerFlashMessage}</span>
                    </div>
                )}
                <div className="flex justify-end">
                    <SyncButton onClick={handleFetchPartners}>取引先取得</SyncButton>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map((card) => (
                        <Card key={card.key} className={`relative overflow-hidden border-0 bg-gradient-to-br ${card.accent} shadow-lg`}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div>
                                    <CardTitle className="text-sm font-medium text-slate-700">{card.title}</CardTitle>
                                    {card.subtitle && <p className="text-xs text-slate-500 mt-1">{card.subtitle}</p>}
                                </div>
                                <div className={`rounded-full ${card.iconWrap} p-2`}>
                                    {card.icon}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-xs text-slate-500">{currentPeriodLabel}</p>
                                        <div className="text-2xl font-bold text-slate-900">
                                            {formatCurrency(card.current)}
                                        </div>
                                    </div>
                                    <div className="rounded-lg border border-white/50 bg-white/60 p-3">
                                        <p className="text-xs text-slate-500">{previousPeriodLabel}</p>
                                        <p className="text-sm font-semibold text-slate-800">
                                            {formatCurrency(card.previous)}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                            <div className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-white opacity-20" />
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className='flex items-center'><BarChart3 className="h-5 w-5 mr-2"/>売上ランキング</CardTitle>
                            <CardDescription>今月の得意先別売上トップ5</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {hasSalesRanking ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[50px]">順位</TableHead>
                                            <TableHead>得意先</TableHead>
                                            <TableHead className="text-right">金額</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {salesRanking.map((item) => (
                                            <TableRow key={item.rank}>
                                                <TableCell className="font-medium">{item.rank}</TableCell>
                                                <TableCell>{item.customer_name}</TableCell>
                                                <TableCell className="text-right">{formatCurrency(item.amount)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <div className="text-sm text-slate-500">今月の売上データがありません。</div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className='flex items-center'><ListChecks className="h-5 w-5 mr-2"/>やることリスト</CardTitle>
                                    <CardDescription>承認タスク（申請日降順）</CardDescription>
                                </div>
                                <ToggleGroup type="single" value={filter} onValueChange={(v) => v && setFilter(v)} className="gap-1">
                                    <ToggleGroupItem value="all" aria-label="全て">全て</ToggleGroupItem>
                                    <ToggleGroupItem value="mine" aria-label="自分のみ">自分のみ</ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[120px]">申請日</TableHead>
                                        <TableHead>件名</TableHead>
                                        <TableHead className="w-[160px]">状態/操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredTasks.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={3} className="text-slate-500">対象のタスクはありません。</TableCell>
                                        </TableRow>
                                    )}
                                    {filteredTasks.map((task) => (
                                        <TableRow key={task.id} className="hover:bg-slate-50">
                                            <TableCell className="font-medium">{formatDate(task.issue_date)}</TableCell>
                                            <TableCell className="max-w-[260px] truncate">{task.title}</TableCell>
                                            <TableCell>
                                                {task.status_for_dashboard === '確認して承認' ? (
                                                    <Button size="sm" onClick={() => openDetail(task)}>確認して承認</Button>
                                                ) : (
                                                    <Badge variant="secondary">{task.status_for_dashboard}</Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                {/* Detail Sheet (reuse existing modal) */}
                <EstimateDetailSheet
                    estimate={selectedEstimate}
                    isOpen={openSheet}
                    onClose={() => setOpenSheet(false)}
                />
            </div>
        </AuthenticatedLayout>
    );
}
