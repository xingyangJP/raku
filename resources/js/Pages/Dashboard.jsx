import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { FileText, TrendingUp, ShoppingCart, ListChecks, BarChart3, Gauge, Activity, Landmark } from 'lucide-react';
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

const formatPersonDays = (value) => `${Number(value || 0).toFixed(1)} 人日`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;
const formatProductivity = (value) => `${formatCurrency(value || 0)} / 人日`;

const varianceTone = (value) => {
    if (value > 0) return 'text-emerald-600';
    if (value < 0) return 'text-rose-600';
    return 'text-slate-500';
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
    const basis = dashboardMetrics?.basis ?? {};
    const currentPeriodLabel = periods?.current?.label ?? '今月';
    const previousPeriodLabel = periods?.previous?.label ?? '先月';

    const budgetCurrent = dashboardMetrics?.budget?.current ?? {};
    const budgetPrevious = dashboardMetrics?.budget?.previous ?? {};
    const actualCurrent = dashboardMetrics?.actual?.current ?? {};
    const actualPrevious = dashboardMetrics?.actual?.previous ?? {};
    const effortCurrent = dashboardMetrics?.effort?.current ?? {};
    const effortSource = dashboardMetrics?.effort?.source ?? {};
    const effortSummary = dashboardMetrics?.effort?.summary ?? {};
    const cashCurrent = dashboardMetrics?.cash_flow?.current ?? {};

    const forecastMonths = Array.isArray(dashboardMetrics?.forecast?.months)
        ? dashboardMetrics.forecast.months
        : [];

    const summaryCards = [
        {
            key: 'sales',
            title: '売上（納期ベース）',
            icon: <FileText className="h-4 w-4 text-blue-900" />,
            iconWrap: 'bg-blue-500',
            accent: 'from-blue-50 to-blue-100',
            budget: budgetCurrent?.sales ?? 0,
            actual: actualCurrent?.sales ?? 0,
            previousBudget: budgetPrevious?.sales ?? 0,
            previousActual: actualPrevious?.sales ?? 0,
        },
        {
            key: 'grossProfit',
            title: '粗利（売上-仕入）',
            icon: <TrendingUp className="h-4 w-4 text-emerald-900" />,
            iconWrap: 'bg-emerald-500',
            accent: 'from-emerald-50 to-emerald-100',
            budget: budgetCurrent?.gross_profit ?? 0,
            actual: actualCurrent?.gross_profit ?? 0,
            previousBudget: budgetPrevious?.gross_profit ?? 0,
            previousActual: actualPrevious?.gross_profit ?? 0,
        },
        {
            key: 'purchase',
            title: '仕入（原価）',
            icon: <ShoppingCart className="h-4 w-4 text-amber-900" />,
            iconWrap: 'bg-amber-500',
            accent: 'from-amber-50 to-amber-100',
            budget: budgetCurrent?.purchase ?? 0,
            actual: actualCurrent?.purchase ?? 0,
            previousBudget: budgetPrevious?.purchase ?? 0,
            previousActual: actualPrevious?.purchase ?? 0,
        },
    ];

    const hasSalesRanking = Array.isArray(salesRanking) && salesRanking.length > 0;

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

                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle>集計基準</CardTitle>
                                <CardDescription>
                                    予算={basis.budget ?? '見積'} / 実績={basis.actual ?? '注文'} / 発生月={basis.recognition ?? '納期ベース'}
                                </CardDescription>
                            </div>
                            <SyncButton onClick={handleFetchPartners}>取引先取得</SyncButton>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-0 text-xs text-slate-500">
                        <div>{basis.recognition_fallback ?? ''}</div>
                        <div className="mt-1">{basis.effort_rule ?? ''}</div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map((card) => {
                        const variance = (card.actual ?? 0) - (card.budget ?? 0);
                        return (
                            <Card key={card.key} className={`relative overflow-hidden border-0 bg-gradient-to-br ${card.accent} shadow-lg`}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div>
                                        <CardTitle className="text-sm font-medium text-slate-700">{card.title}</CardTitle>
                                        <p className="text-xs text-slate-500 mt-1">{currentPeriodLabel}</p>
                                    </div>
                                    <div className={`rounded-full ${card.iconWrap} p-2`}>
                                        {card.icon}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-1 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-600">予算</span>
                                            <span className="font-semibold">{formatCurrency(card.budget)}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-600">実績</span>
                                            <span className="font-semibold">{formatCurrency(card.actual)}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-600">差異</span>
                                            <span className={`font-bold ${varianceTone(variance)}`}>{formatCurrency(variance)}</span>
                                        </div>
                                    </div>
                                    <div className="mt-3 rounded-lg border border-white/50 bg-white/60 p-2 text-[11px] text-slate-600">
                                        {previousPeriodLabel}: 予算 {formatCurrency(card.previousBudget)} / 実績 {formatCurrency(card.previousActual)}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card className="border-slate-200">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm flex items-center"><Gauge className="h-4 w-4 mr-2" />計画工数（当月）</CardTitle>
                            <CardDescription>{effortSource?.label ?? '計画工数（見積ベース）'}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="text-xs text-slate-500">キャパ {formatPersonDays(effortCurrent?.capacity ?? 0)}</div>
                            <div className="text-sm">計画: {formatPercent(effortCurrent?.planned_fill_rate ?? 0)} ({formatPersonDays(effortCurrent?.planned ?? 0)})</div>
                            <div className="text-xs text-slate-600">空き工数（計画）: {formatPersonDays(effortCurrent?.planned_remaining ?? 0)}</div>
                            <div className="text-xs text-amber-700">未配賦（納期未設定）: {formatPersonDays(effortSummary?.unscheduled_total ?? 0)}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm flex items-center"><Activity className="h-4 w-4 mr-2" />計画生産性（粗利/計画工数）</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="text-sm">計画: {formatProductivity(budgetCurrent?.productivity ?? 0)}</div>
                            <div className="text-xs text-slate-600">計画工数: {formatPersonDays(budgetCurrent?.effort ?? 0)}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">件数（当月）</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>予算案件: {budgetCurrent?.count ?? 0}件</div>
                            <div>実績案件: {actualCurrent?.count ?? 0}件</div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="border-slate-200">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">仕入内訳（当月）</CardTitle>
                            <CardDescription>物品仕入 + 工数原価</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span>予算 物品仕入</span>
                                <span className="font-semibold">{formatCurrency(budgetCurrent?.purchase_material ?? 0)}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span>予算 工数原価</span>
                                <span className="font-semibold">{formatCurrency(budgetCurrent?.purchase_labor ?? 0)}</span>
                            </div>
                            <div className="pt-2 border-t flex items-center justify-between text-sm">
                                <span>予算 合計仕入</span>
                                <span className="font-bold">{formatCurrency(budgetCurrent?.purchase ?? 0)}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span>実績 合計仕入</span>
                                <span className="font-semibold">{formatCurrency(actualCurrent?.purchase ?? 0)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm flex items-center"><Landmark className="h-4 w-4 mr-2" />資金繰り（当月）</CardTitle>
                            <CardDescription>仕入先行を前提に、支払と回収を比較</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span>支払予定（仕入）</span>
                                <span className="font-semibold text-rose-600">{formatCurrency(cashCurrent?.purchase_outflow_budget ?? 0)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>回収予定（請求/入金見込み）</span>
                                <span className="font-semibold text-emerald-700">{formatCurrency(cashCurrent?.collection_inflow_budget ?? 0)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>回収実績（入金済）</span>
                                <span className="font-semibold">{formatCurrency(cashCurrent?.collection_inflow_actual ?? 0)}</span>
                            </div>
                            <div className="pt-2 border-t flex items-center justify-between">
                                <span>ネットCF（予定）</span>
                                <span className={`font-bold ${varianceTone(cashCurrent?.net_budget ?? 0)}`}>{formatCurrency(cashCurrent?.net_budget ?? 0)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>ネットCF（実績）</span>
                                <span className={`font-bold ${varianceTone(cashCurrent?.net_actual ?? 0)}`}>{formatCurrency(cashCurrent?.net_actual ?? 0)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                        <CardHeader>
                            <CardTitle>月次 予実一覧（納期ベース）</CardTitle>
                        <CardDescription>売上・粗利・仕入は納期ベース。計画工数は納期設定された見積のみを月配賦</CardDescription>
                        </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>月</TableHead>
                                    <TableHead className="text-right">売上 予算</TableHead>
                                    <TableHead className="text-right">売上 実績</TableHead>
                                    <TableHead className="text-right">粗利 予算</TableHead>
                                    <TableHead className="text-right">粗利 実績</TableHead>
                                    <TableHead className="text-right">仕入 予算</TableHead>
                                    <TableHead className="text-right">仕入 実績</TableHead>
                                    <TableHead className="text-right">計画工数</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {forecastMonths.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-slate-500">予実データがありません。</TableCell>
                                    </TableRow>
                                )}
                                {forecastMonths.map((row) => (
                                    <TableRow key={row.month_key}>
                                        <TableCell className="font-medium">{row.month_label}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.budget_sales)}</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(row.actual_sales)}
                                            <div className={`text-[11px] ${varianceTone(row.sales_variance)}`}>{formatCurrency(row.sales_variance)}</div>
                                        </TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.budget_gross_profit)}</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(row.actual_gross_profit)}
                                            <div className={`text-[11px] ${varianceTone(row.gross_profit_variance)}`}>{formatCurrency(row.gross_profit_variance)}</div>
                                        </TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.budget_purchase)}</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(row.actual_purchase)}
                                            <div className={`text-[11px] ${varianceTone(row.purchase_variance)}`}>{formatCurrency(row.purchase_variance)}</div>
                                        </TableCell>
                                        <TableCell className="text-right">{formatPersonDays(row.budget_effort)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>月次キャッシュフロー（支払/回収）</CardTitle>
                        <CardDescription>仕入支払と回収予定・回収実績を並べて資金繰りを可視化</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>月</TableHead>
                                    <TableHead className="text-right">支払予定</TableHead>
                                    <TableHead className="text-right">回収予定</TableHead>
                                    <TableHead className="text-right">回収実績</TableHead>
                                    <TableHead className="text-right">ネット予定</TableHead>
                                    <TableHead className="text-right">ネット実績</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {forecastMonths.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-slate-500">キャッシュフロー予測データがありません。</TableCell>
                                    </TableRow>
                                )}
                                {forecastMonths.map((row) => (
                                    <TableRow key={`${row.month_key}-cash`}>
                                        <TableCell className="font-medium">{row.month_label}</TableCell>
                                        <TableCell className="text-right text-rose-600">{formatCurrency(row.budget_purchase_outflow)}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.budget_collection_inflow)}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.actual_collection_inflow)}</TableCell>
                                        <TableCell className={`text-right font-semibold ${varianceTone(row.budget_net_cash)}`}>{formatCurrency(row.budget_net_cash)}</TableCell>
                                        <TableCell className={`text-right font-semibold ${varianceTone(row.actual_net_cash)}`}>{formatCurrency(row.actual_net_cash)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className='flex items-center'><BarChart3 className="h-5 w-5 mr-2" />売上ランキング</CardTitle>
                            <CardDescription>当月の受注（納期ベース）トップ5</CardDescription>
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
                                <div className="text-sm text-slate-500">当月の受注データがありません。</div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className='flex items-center'><ListChecks className="h-5 w-5 mr-2" />やることリスト</CardTitle>
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

                <EstimateDetailSheet
                    estimate={selectedEstimate}
                    isOpen={openSheet}
                    onClose={() => setOpenSheet(false)}
                />
            </div>
        </AuthenticatedLayout>
    );
}
