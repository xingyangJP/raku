import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/Components/ui/toggle-group';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Activity, BarChart3, Brain, ClipboardList, FileText, Gauge, Info, Landmark, LineChart as LineChartIcon, ListChecks, ShoppingCart, TrendingUp, Users } from 'lucide-react';
import EstimateDetailSheet from '@/Components/EstimateDetailSheet';
import SyncButton from '@/Components/SyncButton';
import { formatCurrency } from '@/lib/utils';

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}年${month}月${day}日`;
};

const formatPersonDays = (value) => `${Number(value || 0).toFixed(1)} 人日`;
const formatHours = (value) => `${Number(value || 0).toFixed(1)} 時間`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;
const formatProductivity = (value) => `${formatCurrency(Number(value || 0))}/人日`;
const formatMonthTick = (label) => {
    if (!label) return '';
    const matched = String(label).match(/(\d{1,2})月/);
    if (matched) {
        return `${matched[1]}月`;
    }

    return String(label);
};
const formatSignedCurrency = (value) => `${value > 0 ? '+' : value < 0 ? '-' : ''}${formatCurrency(Math.abs(Number(value || 0)))}`;
const formatSignedPercent = (value) => `${value > 0 ? '+' : value < 0 ? '-' : ''}${Math.abs(Number(value || 0)).toFixed(1)}%`;

const varianceTone = (value) => {
    if (value > 0) return 'text-emerald-600';
    if (value < 0) return 'text-rose-600';
    return 'text-slate-500';
};

const insightTone = (tone) => {
    if (tone === 'positive') return 'border-emerald-200 bg-emerald-50';
    if (tone === 'negative') return 'border-rose-200 bg-rose-50';
    return 'border-slate-200 bg-slate-50';
};

const sectionThemes = {
    overall: {
        icon: BarChart3,
        tab: 'data-[state=active]:border-slate-900 data-[state=active]:bg-slate-900 data-[state=active]:text-white',
        pill: 'bg-slate-100 text-slate-700',
        surface: 'from-slate-50 to-white',
    },
    development: {
        icon: Activity,
        tab: 'data-[state=active]:border-indigo-700 data-[state=active]:bg-indigo-700 data-[state=active]:text-white',
        pill: 'bg-indigo-50 text-indigo-700',
        surface: 'from-indigo-50 to-white',
    },
    sales: {
        icon: ShoppingCart,
        tab: 'data-[state=active]:border-amber-600 data-[state=active]:bg-amber-500 data-[state=active]:text-slate-950',
        pill: 'bg-amber-50 text-amber-700',
        surface: 'from-amber-50 to-white',
    },
    maintenance: {
        icon: Landmark,
        tab: 'data-[state=active]:border-emerald-700 data-[state=active]:bg-emerald-600 data-[state=active]:text-white',
        pill: 'bg-emerald-50 text-emerald-700',
        surface: 'from-emerald-50 to-white',
    },
};

function EmptyChartState({ message = 'グラフ用データがありません。' }) {
    return (
        <div className="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-500">
            {message}
        </div>
    );
}

function DashboardChartCard({ title, description, children }) {
    return (
        <Card className="border-slate-200">
            <CardHeader className="pb-3">
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="h-[300px]">
                {children}
            </CardContent>
        </Card>
    );
}

function ChartLegend({ items = [] }) {
    return (
        <div className="mb-3 flex flex-wrap gap-2 text-xs text-slate-700">
            {items.map((item) => (
                <div key={item.label} className="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1.5">
                    {item.type === 'line' ? (
                        <svg width="26" height="12" viewBox="0 0 26 12" aria-hidden="true" className="shrink-0">
                            <line x1="2" y1="6" x2="24" y2="6" stroke={item.color} strokeWidth="3" strokeLinecap="round" />
                            <circle cx="13" cy="6" r="3.5" fill={item.color} stroke="#ffffff" strokeWidth="1" />
                        </svg>
                    ) : (
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" className="shrink-0">
                            <rect x="1" y="1" width="12" height="12" rx="3" fill={item.color} stroke="#cbd5e1" />
                        </svg>
                    )}
                    <span className="text-slate-500">{item.type === 'line' ? '線' : '棒'}</span>
                    <span className="font-medium text-slate-800">{item.label}</span>
                </div>
            ))}
        </div>
    );
}

function LatestValueSummary({ monthLabel, items = [] }) {
    if (!monthLabel || items.length === 0) {
        return null;
    }

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span className="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">
                最新 {formatMonthTick(monthLabel)}
            </span>
            {items.map((item) => (
                <div key={`${item.type}-${item.label}`} className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-700">
                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                    <span className="text-slate-500">{item.label}</span>
                    <span className="font-semibold text-slate-900">{item.value}</span>
                </div>
            ))}
        </div>
    );
}

function SimpleComboChart({
    data = [],
    xKey = 'month',
    bars = [],
    lines = [],
    height = 220,
    valueFormatter = formatCurrency,
}) {
    if (!Array.isArray(data) || data.length === 0) {
        return <EmptyChartState />;
    }

    const width = 860;
    const padding = { top: 20, right: 20, bottom: 34, left: 20 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const barSeries = bars.filter((bar) => data.some((row) => Number(row?.[bar.key] ?? 0) !== 0));
    const lineSeries = lines.filter((line) => data.some((row) => Number(row?.[line.key] ?? 0) !== 0));
    const allSeries = [...barSeries, ...lineSeries];

    if (allSeries.length === 0) {
        return <EmptyChartState />;
    }

    const maxValue = Math.max(
        1,
        ...data.flatMap((row) => allSeries.map((series) => Number(row?.[series.key] ?? 0)))
    );

    const groupWidth = chartWidth / Math.max(data.length, 1);
    const innerBarWidth = barSeries.length > 0 ? Math.min(28, (groupWidth * 0.7) / barSeries.length) : 0;
    const latestRow = data[data.length - 1] ?? null;
    const latestValueItems = latestRow ? [
        ...barSeries.map((item) => ({
            type: 'bar',
            label: item.label,
            color: item.color,
            value: (item.formatter ?? valueFormatter)(latestRow?.[item.key] ?? 0),
        })),
        ...lineSeries.map((item) => ({
            type: 'line',
            label: item.label,
            color: item.color,
            value: (item.formatter ?? valueFormatter)(latestRow?.[item.key] ?? 0),
        })),
    ] : [];

    const toY = (value) => padding.top + chartHeight - (Math.max(0, Number(value || 0)) / maxValue) * chartHeight;
    const linePath = (key) => data.map((row, index) => {
        const x = padding.left + groupWidth * index + groupWidth / 2;
        const y = toY(row?.[key] ?? 0);
        return `${index === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');

    return (
        <div className="h-full w-full">
            <ChartLegend items={[
                ...barSeries.map((item) => ({ label: item.label, color: item.color, type: 'bar' })),
                ...lineSeries.map((item) => ({ label: item.label, color: item.color, type: 'line' })),
            ]} />
            <LatestValueSummary monthLabel={latestRow?.[xKey]} items={latestValueItems} />
            <svg viewBox={`0 0 ${width} ${height}`} className="h-[220px] w-full">
                <line x1={padding.left} y1={padding.top + chartHeight} x2={width - padding.right} y2={padding.top + chartHeight} stroke="#cbd5e1" strokeWidth="1" />
                {data.map((row, index) => {
                    const centerX = padding.left + groupWidth * index + groupWidth / 2;
                    return (
                        <g key={`${row?.[xKey] ?? index}`}>
                            {barSeries.map((bar, barIndex) => {
                                const value = Number(row?.[bar.key] ?? 0);
                                const x = centerX - (barSeries.length * innerBarWidth) / 2 + barIndex * innerBarWidth;
                                const y = toY(value);
                                const h = Math.max(0, padding.top + chartHeight - y);
                                return (
                                    <rect
                                        key={bar.key}
                                        x={x}
                                        y={y}
                                        width={Math.max(10, innerBarWidth - 4)}
                                        height={h}
                                        rx="4"
                                        fill={bar.color}
                                    />
                                );
                            })}
                            <text
                                x={centerX}
                                y={height - 10}
                                textAnchor="middle"
                                className="fill-slate-500 text-[11px]"
                            >
                                {formatMonthTick(row?.[xKey])}
                            </text>
                        </g>
                    );
                })}
                {lineSeries.map((line) => (
                    <path
                        key={line.key}
                        d={linePath(line.key)}
                        fill="none"
                        stroke={line.color}
                        strokeWidth="3"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                ))}
                {lineSeries.flatMap((line) => data.map((row, index) => {
                    const cx = padding.left + groupWidth * index + groupWidth / 2;
                    const cy = toY(row?.[line.key] ?? 0);
                    return (
                        <circle
                            key={`${line.key}-${row?.[xKey] ?? index}`}
                            cx={cx}
                            cy={cy}
                            r="3"
                            fill={line.color}
                        />
                    );
                }))}
            </svg>
            <div className="mt-1 flex justify-end text-xs text-slate-500">
                最大値: {valueFormatter(maxValue)}
            </div>
        </div>
    );
}

function YearOverYearSummary({ currentPeriodLabel, previousYearCurrentLabel, yoyCurrent, yoyYtd }) {
    const summaryItems = [
        {
            key: 'sales',
            label: '売上',
            currentDelta: yoyCurrent?.sales?.delta ?? 0,
            ytdDelta: yoyYtd?.sales?.delta ?? 0,
        },
        {
            key: 'gross',
            label: '粗利',
            currentDelta: yoyCurrent?.gross_profit?.delta ?? 0,
            ytdDelta: yoyYtd?.gross_profit?.delta ?? 0,
        },
        {
            key: 'margin',
            label: '粗利率',
            currentDelta: yoyCurrent?.gross_margin?.delta ?? 0,
            ytdDelta: yoyYtd?.gross_margin?.delta ?? 0,
            formatter: formatSignedPercent,
        },
        {
            key: 'effort',
            label: '工数',
            currentDelta: yoyCurrent?.effort?.delta ?? 0,
            ytdDelta: yoyYtd?.effort?.delta ?? 0,
            formatter: (value) => `${value > 0 ? '+' : value < 0 ? '-' : ''}${formatPersonDays(Math.abs(Number(value || 0)))}`,
        },
    ];

    return (
        <Card className="border-slate-200 bg-slate-50/70">
            <CardContent className="grid gap-3 p-4 md:grid-cols-4">
                {summaryItems.map((item) => {
                    const formatter = item.formatter ?? formatSignedCurrency;
                    return (
                        <div key={item.key} className="rounded-xl border border-white bg-white px-4 py-3 shadow-sm">
                            <div className="text-xs text-slate-500">{item.label} 前年トレンド</div>
                            <div className={`mt-2 text-base font-semibold ${varianceTone(item.currentDelta)}`}>
                                {formatter(item.currentDelta)}
                            </div>
                            <div className="mt-1 text-xs text-slate-500">{currentPeriodLabel} vs {previousYearCurrentLabel}</div>
                            <div className={`mt-3 text-sm font-medium ${varianceTone(item.ytdDelta)}`}>
                                累計 {formatter(item.ytdDelta)}
                            </div>
                            <div className="text-[11px] text-slate-500">年初来前年差</div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    auth,
    toDoEstimates = [],
    partnerSyncFlash = {},
    partnerSyncMeta = null,
    dashboardMetrics = null,
    businessDivisionReport = null,
    salesRanking = [],
}) {
    const { flash } = usePage().props;
    const partnerFlashMessage = partnerSyncFlash?.message;
    const partnerFlashIsError = partnerSyncFlash?.status === 'error';

    const periods = dashboardMetrics?.periods ?? {};
    const basis = dashboardMetrics?.basis ?? {};
    const capacity = dashboardMetrics?.capacity ?? {};
    const filterOptions = dashboardMetrics?.filters ?? {};
    const sections = dashboardMetrics?.sections ?? {};
    const sectionOrder = ['overall', 'development', 'sales', 'maintenance'].filter((key) => sections[key]);
    const defaultSection = dashboardMetrics?.default_section && sections[dashboardMetrics.default_section]
        ? dashboardMetrics.default_section
        : sectionOrder[0] ?? 'overall';
    const overallAnalysis = Array.isArray(dashboardMetrics?.analysis) ? dashboardMetrics.analysis : [];
    const currentPeriodLabel = periods?.current?.label ?? '今月';
    const previousPeriodLabel = periods?.previous?.label ?? '先月';
    const previousYearCurrentLabel = periods?.previous_year_current?.label ?? '前年同月';
    const currentYearLabel = periods?.current_year?.label ?? '今年';
    const previousYearLabel = periods?.previous_year?.label ?? '前年';
    const selectedYear = filterOptions?.selected_year ?? new Date().getFullYear();
    const selectedMonth = filterOptions?.selected_month ?? new Date().getMonth() + 1;
    const availableYears = Array.isArray(filterOptions?.available_years) ? filterOptions.available_years : [selectedYear];
    const availableMonths = Array.isArray(filterOptions?.available_months) ? filterOptions.available_months : Array.from({ length: 12 }, (_, index) => ({
        value: index + 1,
        label: `${index + 1}月`,
    }));
    const businessDivisionLabels = businessDivisionReport?.division_labels ?? {};
    const businessDivisionTotals = businessDivisionReport?.division_totals ?? {};
    const businessDivisionGrandTotal = Number(businessDivisionReport?.grand_total ?? 0);
    const businessDivisionFocusMonthLabel = businessDivisionReport?.period?.focus_month_label ?? currentPeriodLabel;
    const businessDivisionMonthlyData = Array.isArray(businessDivisionReport?.monthly_data) ? businessDivisionReport.monthly_data : [];
    const businessDivisionDetailRows = Array.isArray(businessDivisionReport?.detail_rows) ? businessDivisionReport.detail_rows : [];
    const businessDivisionBasis = businessDivisionReport?.basis ?? {};
    const businessDivisionCards = Object.entries(businessDivisionLabels)
        .map(([key, label]) => {
            const amount = Number(businessDivisionTotals?.[key] ?? 0);

            return {
                key,
                label,
                amount,
                share: businessDivisionGrandTotal > 0 ? (amount / businessDivisionGrandTotal) * 100 : 0,
            };
        })
        .filter((card) => card.amount > 0)
        .sort((a, b) => b.amount - a.amount);
    const businessDivisionChartSeries = businessDivisionCards.slice(0, 4).map((card, index) => ({
        key: card.key,
        label: card.label,
        color: ['#0f172a', '#2563eb', '#16a34a', '#f59e0b'][index] ?? '#64748b',
    }));
    const businessDivisionChartRows = businessDivisionMonthlyData.map((row) => ({
        month: row.label,
        ...Object.fromEntries(Object.entries(row.divisions ?? {}).map(([key, value]) => [key, Number(value ?? 0)])),
    }));

    const [activeSection, setActiveSection] = useState(defaultSection);
    const [filter, setFilter] = useState('all');
    const [openSheet, setOpenSheet] = useState(false);
    const [selectedEstimate, setSelectedEstimate] = useState(null);
    const [selectedBusinessDivision, setSelectedBusinessDivision] = useState('all');

    const selected = sections[activeSection] ?? sections[defaultSection] ?? {};
    const budgetCurrent = selected?.budget?.current ?? {};
    const budgetPrevious = selected?.budget?.previous ?? {};
    const actualCurrent = selected?.actual?.current ?? {};
    const actualPrevious = selected?.actual?.previous ?? {};
    const effortCurrent = selected?.effort?.current ?? {};
    const effortSummary = selected?.effort?.summary ?? {};
    const cashCurrent = selected?.cash_flow?.current ?? {};
    const forecastMonths = Array.isArray(selected?.forecast?.months) ? selected.forecast.months : [];
    const yoyCurrent = selected?.year_over_year?.current ?? {};
    const yoyYtd = selected?.year_over_year?.ytd ?? {};
    const yoyChartRows = Array.isArray(selected?.year_over_year?.chart) ? selected.year_over_year.chart : [];
    const overallSection = sections?.overall ?? {};
    const overallForecastMonths = Array.isArray(overallSection?.forecast?.months) ? overallSection.forecast.months : [];

    const hasSalesRanking = Array.isArray(salesRanking) && salesRanking.length > 0;
    const filteredTasks = useMemo(() => {
        if (filter === 'mine') {
            return (toDoEstimates || []).filter((task) => task.is_current_user_in_flow);
        }

        return toDoEstimates || [];
    }, [filter, toDoEstimates]);
    const filteredBusinessDivisionDetails = useMemo(() => {
        if (selectedBusinessDivision === 'all') {
            return businessDivisionDetailRows;
        }

        return businessDivisionDetailRows.filter((row) => row.division_key === selectedBusinessDivision);
    }, [businessDivisionDetailRows, selectedBusinessDivision]);

    const summaryCards = [
        {
            key: 'sales',
            title: '売上',
            icon: <FileText className="h-4 w-4 text-sky-900" />,
            accent: 'from-sky-50 to-sky-100',
            budget: budgetCurrent?.sales ?? 0,
            actual: actualCurrent?.sales ?? 0,
            previousBudget: budgetPrevious?.sales ?? 0,
            previousActual: actualPrevious?.sales ?? 0,
        },
        {
            key: 'gross',
            title: '粗利',
            icon: <TrendingUp className="h-4 w-4 text-emerald-900" />,
            accent: 'from-emerald-50 to-emerald-100',
            budget: budgetCurrent?.gross_profit ?? 0,
            actual: actualCurrent?.gross_profit ?? 0,
            previousBudget: budgetPrevious?.gross_profit ?? 0,
            previousActual: actualPrevious?.gross_profit ?? 0,
        },
        {
            key: 'purchase',
            title: '仕入',
            icon: <ShoppingCart className="h-4 w-4 text-amber-900" />,
            accent: 'from-amber-50 to-amber-100',
            budget: budgetCurrent?.purchase ?? 0,
            actual: actualCurrent?.purchase ?? 0,
            previousBudget: budgetPrevious?.purchase ?? 0,
            previousActual: actualPrevious?.purchase ?? 0,
        },
        {
            key: 'effort',
            title: '工数',
            icon: <Gauge className="h-4 w-4 text-indigo-900" />,
            accent: 'from-indigo-50 to-indigo-100',
            budget: budgetCurrent?.effort ?? 0,
            actual: actualCurrent?.count ?? 0,
            previousBudget: budgetPrevious?.effort ?? 0,
            previousActual: actualPrevious?.count ?? 0,
            formatter: (value) => formatPersonDays(value),
            actualLabel: '実績件数',
            previousLabel: previousPeriodLabel,
        },
    ];

    const chartRows = forecastMonths.map((row) => ({
        month: row.month_label,
        budgetSales: row.budget_sales,
        actualSales: row.actual_sales,
        budgetGross: row.budget_gross_profit,
        actualGross: row.actual_gross_profit,
        budgetCash: row.budget_net_cash,
        actualCash: row.actual_net_cash,
        effort: row.budget_effort,
        utilization: effortCurrent?.capacity > 0 ? (row.budget_effort / effortCurrent.capacity) * 100 : 0,
    }));
    const overallCapacity = overallSection?.effort?.current?.capacity ?? capacity?.monthly_person_days ?? 0;
    const overviewChartRows = overallForecastMonths.map((row) => ({
        month: row.month_label,
        budgetSales: row.budget_sales,
        actualSales: row.actual_sales,
        budgetGross: row.budget_gross_profit,
        actualGross: row.actual_gross_profit,
        budgetCash: row.budget_net_cash,
        actualCash: row.actual_net_cash,
        effort: row.budget_effort,
        utilization: overallCapacity > 0 ? (row.budget_effort / overallCapacity) * 100 : 0,
    }));
    const hasOverviewCharts = overviewChartRows.length > 0;

    const yoyCards = [
        {
            key: 'sales',
            title: '売上 前年比',
            metric: yoyCurrent?.sales ?? {},
            formatter: formatCurrency,
            deltaFormatter: formatSignedCurrency,
        },
        {
            key: 'gross',
            title: '粗利 前年比',
            metric: yoyCurrent?.gross_profit ?? {},
            formatter: formatCurrency,
            deltaFormatter: formatSignedCurrency,
        },
        {
            key: 'margin',
            title: '粗利率 前年差',
            metric: yoyCurrent?.gross_margin ?? {},
            formatter: formatPercent,
            deltaFormatter: formatSignedPercent,
        },
        {
            key: 'effort',
            title: '工数 前年差',
            metric: yoyCurrent?.effort ?? {},
            formatter: formatPersonDays,
            deltaFormatter: (value) => `${value > 0 ? '+' : value < 0 ? '-' : ''}${formatPersonDays(Math.abs(Number(value || 0)))}`,
        },
    ];

    const openDetail = (task) => {
        if (task?.estimate) {
            setSelectedEstimate(task.estimate);
            setOpenSheet(true);
        }
    };

    const handleFetchPartners = () => {
        router.get(route('partners.sync'));
    };

    const handlePeriodChange = (nextYear, nextMonth) => {
        router.get(route('dashboard'), {
            year: nextYear,
            month: nextMonth,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                {flash?.success && (
                    <div className="rounded border border-green-300 bg-green-50 px-4 py-3 text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded border border-red-300 bg-red-50 px-4 py-3 text-red-700">
                        {flash.error}
                    </div>
                )}
                {partnerFlashMessage && (
                    <div className={`${partnerFlashIsError ? 'border-red-300 bg-red-50 text-red-700' : 'border-green-300 bg-green-50 text-green-700'} rounded border px-4 py-3`}>
                        {partnerFlashMessage}
                    </div>
                )}

                <div className="space-y-3">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary" className="px-3 py-1">
                                <Users className="mr-1 h-3.5 w-3.5" />
                                {capacity?.staff_count ?? 0}人
                            </Badge>
                            <Badge variant="secondary" className="px-3 py-1">
                                月間キャパ {formatPersonDays(capacity?.monthly_person_days ?? 0)}
                            </Badge>
                            <Badge variant="secondary" className="px-3 py-1">
                                月間キャパ {formatHours(capacity?.monthly_person_hours ?? 0)}
                            </Badge>
                            {partnerSyncMeta?.is_cooling_down && (
                                <Badge variant="secondary" className="px-3 py-1">
                                    取引先自動同期は{partnerSyncMeta?.cooldown_hours ?? 3}時間クールダウン中
                                </Badge>
                            )}
                        </div>
                        <div className="flex justify-end">
                            <SyncButton onClick={handleFetchPartners}>取引先取得</SyncButton>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-3 xl:max-w-4xl">
                        <div className="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4">
                            <div className="text-[11px] text-slate-500">表示対象</div>
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                <label className="text-xs text-slate-600">
                                    年
                                    <select
                                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900"
                                        value={selectedYear}
                                        onChange={(event) => handlePeriodChange(Number(event.target.value), selectedMonth)}
                                    >
                                        {availableYears.map((year) => (
                                            <option key={year} value={year}>{year}年</option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-xs text-slate-600">
                                    月
                                    <select
                                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900"
                                        value={selectedMonth}
                                        onChange={(event) => handlePeriodChange(selectedYear, Number(event.target.value))}
                                    >
                                        {availableMonths.map((monthOption) => (
                                            <option key={monthOption.value} value={monthOption.value}>{monthOption.label}</option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div className="text-[11px] text-slate-500">最終取引先同期</div>
                            <div className="mt-1 text-sm font-semibold text-slate-900">
                                {partnerSyncMeta?.last_synced_at_label ?? '未実行'}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div className="text-[11px] text-slate-500">次回自動同期可能</div>
                            <div className="mt-1 text-sm font-semibold text-slate-900">
                                {partnerSyncMeta?.next_auto_sync_available_at_label ?? '今すぐ'}
                            </div>
                        </div>
                    </div>
                </div>

                <Card className="border-slate-200">
                    <CardHeader className="pb-2">
                        <div>
                            <CardTitle>経営ダッシュボード</CardTitle>
                            <CardDescription className="mt-2">
                                見積を予算、受注確定を実績として扱う経営ビュー
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-0">
                        <div className="grid gap-3 lg:grid-cols-[1fr]">
                            <div className="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4">
                                <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <ClipboardList className="h-4 w-4 text-indigo-500" />
                                    集計定義
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <div className="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-indigo-100">
                                        予算 = {basis.budget ?? '見積'}
                                    </div>
                                    <div className="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-indigo-100">
                                        実績 = {basis.actual ?? '注文'}
                                    </div>
                                    <div className="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-indigo-100">
                                        発生月 = {basis.recognition ?? '納期ベース'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
                            <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <Info className="h-4 w-4 text-amber-500" />
                                集計ルールと補足
                            </div>
                            <div className="grid gap-2 text-sm text-slate-600 md:grid-cols-4">
                                <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-amber-100">
                                    {basis.recognition_fallback ?? ''}
                                </div>
                                <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-amber-100">
                                    {basis.effort_rule ?? ''}
                                </div>
                                <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-amber-100">
                                    {basis.maintenance_rule ?? ''}
                                </div>
                                <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-amber-100">
                                    {basis.cash_rule ?? ''}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-2">
                    <DashboardChartCard
                        title="全社 売上・粗利推移"
                        description="予算と実績の月次推移を全社視点で確認"
                    >
                        {hasOverviewCharts ? (
                            <SimpleComboChart
                                data={overviewChartRows}
                                bars={[
                                    { key: 'budgetSales', label: '売上予算', color: '#cbd5e1' },
                                    { key: 'actualSales', label: '売上実績', color: '#0f172a' },
                                ]}
                                lines={[
                                    { key: 'budgetGross', label: '粗利予算', color: '#4ade80' },
                                    { key: 'actualGross', label: '粗利実績', color: '#15803d' },
                                ]}
                            />
                        ) : (
                            <EmptyChartState />
                        )}
                    </DashboardChartCard>

                    <DashboardChartCard
                        title="全社 資金繰り・工数推移"
                        description="ネットCFと計画工数の両方を確認"
                    >
                        {hasOverviewCharts ? (
                            <SimpleComboChart
                                data={overviewChartRows}
                                bars={[
                                    { key: 'budgetCash', label: 'ネット予定', color: '#fde68a' },
                                ]}
                                lines={[
                                    { key: 'actualCash', label: 'ネット実績', color: '#f59e0b' },
                                    { key: 'effort', label: '計画工数', color: '#4f46e5', formatter: formatPersonDays },
                                ]}
                            />
                        ) : (
                            <EmptyChartState />
                        )}
                    </DashboardChartCard>
                </div>

                <Tabs value={activeSection} onValueChange={setActiveSection}>
                    <TabsList className="grid h-auto w-full grid-cols-2 gap-2 bg-transparent p-0 md:grid-cols-4">
                        {sectionOrder.map((key) => (
                            (() => {
                                const theme = sectionThemes[key] ?? sectionThemes.overall;
                                const Icon = theme.icon;

                                return (
                                    <TabsTrigger
                                        key={key}
                                        value={key}
                                        className={`justify-start rounded-xl border border-slate-200 bg-white px-4 py-4 text-left shadow-sm transition ${theme.tab}`}
                                    >
                                        <div className="flex w-full items-center gap-3">
                                            <div className="rounded-lg bg-slate-100 p-2 text-slate-700 data-[state=active]:bg-white/20 data-[state=active]:text-current">
                                                <Icon className="h-4 w-4" />
                                            </div>
                                            <div className="flex flex-col items-start">
                                                <span className="text-sm font-semibold">{sections[key]?.label ?? key}</span>
                                                <span className="text-xs opacity-70">{sections[key]?.description ?? ''}</span>
                                            </div>
                                        </div>
                                    </TabsTrigger>
                                );
                            })()
                        ))}
                    </TabsList>

                    {sectionOrder.map((key) => {
                        const section = sections[key];
                        const theme = sectionThemes[key] ?? sectionThemes.overall;
                        const SectionIcon = theme.icon;
                        const currentBudget = section?.budget?.current ?? {};
                        const currentActual = section?.actual?.current ?? {};
                        const currentEffort = section?.effort?.current ?? {};
                        const currentCash = section?.cash_flow?.current ?? {};
                        const currentForecast = Array.isArray(section?.forecast?.months) ? section.forecast.months : [];
                        const currentAlerts = Array.isArray(section?.alerts) ? section.alerts : [];
                        const currentAnalysis = Array.isArray(section?.analysis)
                            ? section.analysis
                            : (key === 'overall' ? overallAnalysis : []);
                        const customerRanking = Array.isArray(section?.rankings?.customers) ? section.rankings.customers : [];
                        const staffRanking = Array.isArray(section?.rankings?.staff) ? section.rankings.staff : [];
                        const peoplePayload = section?.people ?? {};
                        const peopleSummary = peoplePayload?.summary ?? {};
                        const peopleRows = Array.isArray(peoplePayload?.rows) ? peoplePayload.rows : [];
                        const peopleTopAvailable = Array.isArray(peoplePayload?.top_available) ? peoplePayload.top_available : [];
                        const peopleTopLoad = Array.isArray(peoplePayload?.top_load) ? peoplePayload.top_load : [];
                        const currentRows = currentForecast.map((row) => ({
                            month: row.month_label,
                            budgetSales: row.budget_sales,
                            actualSales: row.actual_sales,
                            budgetGross: row.budget_gross_profit,
                            actualGross: row.actual_gross_profit,
                            budgetCash: row.budget_net_cash,
                            actualCash: row.actual_net_cash,
                            effort: row.budget_effort,
                            utilization: currentEffort?.capacity > 0 ? (row.budget_effort / currentEffort.capacity) * 100 : 0,
                        }));

                        return (
                            <TabsContent key={key} value={key} className="space-y-6">
                                <Card className={`border-slate-200 bg-gradient-to-br ${theme.surface}`}>
                                    <CardContent className="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
                                        <div className="space-y-2">
                                            <div className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ${theme.pill}`}>
                                                <SectionIcon className="h-3.5 w-3.5" />
                                                現在の表示: {section?.label}
                                            </div>
                                            <div className="text-2xl font-semibold text-slate-900">{section?.label}の経営ビュー</div>
                                            <div className="max-w-3xl text-sm text-slate-600">{section?.description}</div>
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-3">
                                            <div className="rounded-xl border border-white/70 bg-white/80 px-4 py-3">
                                                <div className="text-xs text-slate-500">売上達成率</div>
                                                <div className={`mt-1 text-lg font-semibold ${varianceTone((currentActual?.sales ?? 0) - (currentBudget?.sales ?? 0))}`}>
                                                    {formatPercent(currentBudget?.sales > 0 ? ((currentActual?.sales ?? 0) / currentBudget.sales) * 100 : 0)}
                                                </div>
                                            </div>
                                            <div className="rounded-xl border border-white/70 bg-white/80 px-4 py-3">
                                                <div className="text-xs text-slate-500">粗利率</div>
                                                <div className="mt-1 text-lg font-semibold text-slate-900">{formatPercent(section?.highlights?.gross_margin_current ?? 0)}</div>
                                            </div>
                                            <div className="rounded-xl border border-white/70 bg-white/80 px-4 py-3">
                                                <div className="text-xs text-slate-500">工数充足率</div>
                                                <div className="mt-1 text-lg font-semibold text-slate-900">{formatPercent(currentEffort?.planned_fill_rate ?? 0)}</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <YearOverYearSummary
                                    currentPeriodLabel={currentPeriodLabel}
                                    previousYearCurrentLabel={previousYearCurrentLabel}
                                    yoyCurrent={yoyCurrent}
                                    yoyYtd={yoyYtd}
                                />

                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    {yoyCards.map((card) => (
                                        <Card key={`${key}-yoy-${card.key}`} className="border-slate-200 bg-white">
                                            <CardHeader className="space-y-1 pb-2">
                                                <CardTitle className="text-sm font-medium text-slate-700">{card.title}</CardTitle>
                                                <CardDescription>{currentPeriodLabel} と {previousYearCurrentLabel} の比較</CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-2">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-slate-500">{currentPeriodLabel}</span>
                                                    <span className="font-semibold text-slate-900">{card.formatter(card.metric?.current ?? 0)}</span>
                                                </div>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-slate-500">{previousYearCurrentLabel}</span>
                                                    <span className="font-semibold text-slate-700">{card.formatter(card.metric?.previous ?? 0)}</span>
                                                </div>
                                                <div className="flex items-center justify-between border-t pt-2 text-sm">
                                                    <span className="text-slate-500">前年差</span>
                                                    <span className={`font-bold ${varianceTone(card.metric?.delta ?? 0)}`}>{card.deltaFormatter(card.metric?.delta ?? 0)}</span>
                                                </div>
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="text-slate-500">前年比率</span>
                                                    <span className={varianceTone(card.metric?.rate ?? 0)}>{formatSignedPercent(card.metric?.rate ?? 0)}</span>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}

                                    {summaryCards.map((card) => {
                                        const budgetValue = card.key === 'sales'
                                            ? currentBudget?.sales ?? 0
                                            : card.key === 'gross'
                                                ? currentBudget?.gross_profit ?? 0
                                                : card.key === 'purchase'
                                                    ? currentBudget?.purchase ?? 0
                                                    : currentBudget?.effort ?? 0;
                                        const actualValue = card.key === 'sales'
                                            ? currentActual?.sales ?? 0
                                            : card.key === 'gross'
                                                ? currentActual?.gross_profit ?? 0
                                                : card.key === 'purchase'
                                                    ? currentActual?.purchase ?? 0
                                                    : currentEffort?.planned ?? 0;
                                        const variance = actualValue - budgetValue;
                                        const formatter = card.formatter ?? formatCurrency;
                                        const actualLabel = card.key === 'effort' ? '計画実行中' : (card.actualLabel ?? '実績');

                                        return (
                                            <Card key={`${key}-${card.key}`} className={`overflow-hidden border-0 bg-gradient-to-br ${card.accent} shadow-sm`}>
                                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                                    <div>
                                                        <CardTitle className="text-sm font-medium text-slate-700">{card.title}</CardTitle>
                                                        <p className="mt-1 text-xs text-slate-500">{section?.label} / {currentPeriodLabel}</p>
                                                    </div>
                                                    <div className="rounded-full bg-white/70 p-2">
                                                        {card.icon}
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="space-y-1 text-sm">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-600">予算</span>
                                                        <span className="font-semibold">{formatter(budgetValue)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-600">{actualLabel}</span>
                                                        <span className="font-semibold">{formatter(actualValue)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-600">差異</span>
                                                        <span className={`font-bold ${varianceTone(variance)}`}>{formatter(variance)}</span>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                </div>

                                <div className="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <TrendingUp className="h-4 w-4" />
                                                前年同月比 / 年初来累計
                                            </CardTitle>
                                            <CardDescription>{currentYearLabel} と {previousYearLabel} の比較</CardDescription>
                                        </CardHeader>
                                        <CardContent className="grid gap-4 md:grid-cols-2">
                                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <div className="text-sm font-semibold text-slate-900">当月比較</div>
                                                <div className="mt-3 space-y-2 text-sm">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">売上前年差</span>
                                                        <span className={varianceTone(yoyCurrent?.sales?.delta ?? 0)}>{formatSignedCurrency(yoyCurrent?.sales?.delta ?? 0)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">粗利前年差</span>
                                                        <span className={varianceTone(yoyCurrent?.gross_profit?.delta ?? 0)}>{formatSignedCurrency(yoyCurrent?.gross_profit?.delta ?? 0)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">粗利率前年差</span>
                                                        <span className={varianceTone(yoyCurrent?.gross_margin?.delta ?? 0)}>{formatSignedPercent(yoyCurrent?.gross_margin?.delta ?? 0)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">ネットCF前年差</span>
                                                        <span className={varianceTone(yoyCurrent?.net_cash?.delta ?? 0)}>{formatSignedCurrency(yoyCurrent?.net_cash?.delta ?? 0)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <div className="text-sm font-semibold text-slate-900">年初来累計</div>
                                                <div className="mt-3 space-y-2 text-sm">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">売上前年差</span>
                                                        <span className={varianceTone(yoyYtd?.sales?.delta ?? 0)}>{formatSignedCurrency(yoyYtd?.sales?.delta ?? 0)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">粗利前年差</span>
                                                        <span className={varianceTone(yoyYtd?.gross_profit?.delta ?? 0)}>{formatSignedCurrency(yoyYtd?.gross_profit?.delta ?? 0)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">工数前年差</span>
                                                        <span className={varianceTone(yoyYtd?.effort?.delta ?? 0)}>{`${yoyYtd?.effort?.delta > 0 ? '+' : yoyYtd?.effort?.delta < 0 ? '-' : ''}${formatPersonDays(Math.abs(Number(yoyYtd?.effort?.delta ?? 0)))}`}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-slate-500">YTD 粗利率前年差</span>
                                                        <span className={varianceTone(yoyYtd?.gross_margin?.delta ?? 0)}>{formatSignedPercent(yoyYtd?.gross_margin?.delta ?? 0)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <BarChart3 className="h-4 w-4" />
                                                売上・粗利推移
                                            </CardTitle>
                                            <CardDescription>{section?.description}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="h-[320px]">
                                            {currentRows.length > 0 ? (
                                                <SimpleComboChart
                                                    data={currentRows}
                                                    bars={[
                                                        { key: 'budgetSales', label: '売上予算', color: '#cbd5e1' },
                                                        { key: 'actualSales', label: '売上実績', color: '#0f172a' },
                                                    ]}
                                                    lines={[
                                                        { key: 'budgetGross', label: '粗利予算', color: '#22c55e' },
                                                        { key: 'actualGross', label: '粗利実績', color: '#16a34a' },
                                                    ]}
                                                />
                                            ) : (
                                                <EmptyChartState />
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <LineChartIcon className="h-4 w-4" />
                                                前年同月比較チャート
                                            </CardTitle>
                                            <CardDescription>{currentYearLabel} 実績と {previousYearLabel} 実績の比較</CardDescription>
                                        </CardHeader>
                                        <CardContent className="h-[320px]">
                                            {yoyChartRows.length > 0 ? (
                                                <SimpleComboChart
                                                    data={yoyChartRows.map((row) => ({ ...row, month: row.month_label }))}
                                                    bars={[
                                                        { key: 'current_actual_sales', label: `${currentYearLabel} 売上`, color: '#111827' },
                                                        { key: 'last_year_actual_sales', label: `${previousYearLabel} 売上`, color: '#cbd5e1' },
                                                    ]}
                                                    lines={[
                                                        { key: 'current_actual_gross', label: `${currentYearLabel} 粗利`, color: '#16a34a' },
                                                        { key: 'last_year_actual_gross', label: `${previousYearLabel} 粗利`, color: '#86efac' },
                                                    ]}
                                                />
                                            ) : (
                                                <EmptyChartState message="前年比較用データがありません。" />
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Brain className="h-4 w-4" />
                                                経営分析 / アラート
                                            </CardTitle>
                                            <CardDescription>先月比と予算差異をもとにしたコメントと注意点</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {currentAnalysis.map((item, index) => (
                                                <div key={`${item.title}-${index}`} className={`rounded-lg border p-3 ${insightTone(item.tone)}`}>
                                                    <div className="text-sm font-semibold text-slate-900">{item.title}</div>
                                                    <div className="mt-1 text-sm text-slate-700">{item.body}</div>
                                                </div>
                                            ))}
                                            {currentAlerts.map((item, index) => (
                                                <div key={`${item.title}-alert-${index}`} className={`rounded-lg border p-3 ${insightTone(item.tone)}`}>
                                                    <div className="text-sm font-semibold text-slate-900">{item.title}</div>
                                                    <div className="mt-1 text-sm text-slate-700">{item.detail}</div>
                                                </div>
                                            ))}
                                            {currentAnalysis.length === 0 && currentAlerts.length === 0 && (
                                                <div className="text-sm text-slate-500">現時点で強いアラートはありません。</div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-2">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <LineChartIcon className="h-4 w-4" />
                                                資金繰り推移
                                            </CardTitle>
                                            <CardDescription>支払と回収のネット推移</CardDescription>
                                        </CardHeader>
                                        <CardContent className="h-[280px]">
                                            {currentRows.length > 0 ? (
                                                <SimpleComboChart
                                                    data={currentRows}
                                                    bars={[
                                                        { key: 'budgetCash', label: 'ネット予定', color: '#fde68a' },
                                                    ]}
                                                    lines={[
                                                        { key: 'actualCash', label: 'ネット実績', color: '#f59e0b' },
                                                    ]}
                                                />
                                            ) : (
                                                <EmptyChartState />
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Activity className="h-4 w-4" />
                                                工数推移
                                            </CardTitle>
                                            <CardDescription>計画工数と稼働率の月次推移</CardDescription>
                                        </CardHeader>
                                        <CardContent className="h-[280px]">
                                            {currentRows.length > 0 ? (
                                                <SimpleComboChart
                                                    data={currentRows}
                                                    bars={[
                                                        { key: 'effort', label: '計画工数', color: '#818cf8' },
                                                    ]}
                                                    lines={[
                                                        { key: 'utilization', label: '稼働率', color: '#4f46e5', formatter: formatPercent },
                                                    ]}
                                                    valueFormatter={formatPersonDays}
                                                />
                                            ) : (
                                                <EmptyChartState />
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <Card className="border-slate-200">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm flex items-center"><Gauge className="mr-2 h-4 w-4" />計画工数（当月）</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <div>キャパ: {formatPersonDays(currentEffort?.capacity ?? 0)}</div>
                                            <div>計画工数: {formatPersonDays(currentEffort?.planned ?? 0)}</div>
                                            <div>稼働率: {formatPercent(currentEffort?.planned_fill_rate ?? 0)}</div>
                                            <div>空き工数: {formatPersonDays(currentEffort?.planned_remaining ?? 0)}</div>
                                            {key === 'overall' && <div className="text-amber-700">未配賦: {formatPersonDays(effortSummary?.unscheduled_total ?? 0)}</div>}
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm flex items-center"><Landmark className="mr-2 h-4 w-4" />資金繰り（当月）</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <div className="flex items-center justify-between"><span>支払予定</span><span className="font-semibold text-rose-600">{formatCurrency(currentCash?.purchase_outflow_budget ?? 0)}</span></div>
                                            <div className="flex items-center justify-between"><span>回収予定</span><span className="font-semibold text-emerald-700">{formatCurrency(currentCash?.collection_inflow_budget ?? 0)}</span></div>
                                            <div className="flex items-center justify-between"><span>回収実績</span><span className="font-semibold">{formatCurrency(currentCash?.collection_inflow_actual ?? 0)}</span></div>
                                            <div className="border-t pt-2 flex items-center justify-between"><span>ネット予定</span><span className={`font-bold ${varianceTone(currentCash?.net_budget ?? 0)}`}>{formatCurrency(currentCash?.net_budget ?? 0)}</span></div>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm flex items-center"><Users className="mr-2 h-4 w-4" />件数・生産性</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <div>予算件数: {currentBudget?.count ?? 0}件</div>
                                            <div>実績件数: {currentActual?.count ?? 0}件</div>
                                            <div>計画生産性: {formatProductivity(currentBudget?.productivity ?? 0)}</div>
                                            <div>粗利率: {formatPercent(section?.highlights?.gross_margin_current ?? 0)}</div>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Users className="h-4 w-4" />
                                                担当者別の空き状況
                                            </CardTitle>
                                            <CardDescription>担当者按分が入力された明細だけを対象に、当月の予定工数を集計</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                    <div className="text-xs text-slate-500">集計対象担当者</div>
                                                    <div className="mt-2 text-2xl font-semibold text-slate-900">{peopleSummary?.tracked_people_count ?? 0}人</div>
                                                    <div className="mt-1 text-xs text-slate-500">個別キャパ設定のある担当者を含めて集計</div>
                                                </div>
                                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                    <div className="text-xs text-slate-500">未割当工数</div>
                                                    <div className="mt-2 text-2xl font-semibold text-amber-600">{formatPersonDays(peopleSummary?.unassigned_person_days ?? 0)}</div>
                                                    <div className="mt-1 text-xs text-slate-500">担当者未設定の見積明細</div>
                                                </div>
                                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                    <div className="text-xs text-slate-500">高稼働人数</div>
                                                    <div className="mt-2 text-2xl font-semibold text-rose-600">{peopleSummary?.high_load_count ?? 0}人</div>
                                                    <div className="mt-1 text-xs text-slate-500">稼働率 85%以上</div>
                                                </div>
                                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                    <div className="text-xs text-slate-500">まだ空きがある担当者</div>
                                                    <div className="mt-2 text-2xl font-semibold text-emerald-600">{peopleSummary?.available_people_count ?? 0}人</div>
                                                    <div className="mt-1 text-xs text-slate-500">個別キャパに対して残余あり</div>
                                                </div>
                                            </div>
                                            <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                                <div className="flex items-center justify-between">
                                                    <span>集計済み計画工数</span>
                                                    <span className="font-semibold text-slate-900">{formatPersonDays(peopleSummary?.planned_person_days ?? 0)}</span>
                                                </div>
                                                <div className="mt-2 flex items-center justify-between">
                                                    <span>追跡済み担当者の残余合計</span>
                                                    <span className={`font-semibold ${varianceTone(peopleSummary?.available_person_days ?? 0)}`}>{formatPersonDays(peopleSummary?.available_person_days ?? 0)}</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle>担当者別稼働一覧</CardTitle>
                                            <CardDescription>空きの多い順と高稼働順を並べて確認</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {peopleRows.length === 0 ? (
                                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                                    当月は担当者按分データがありません。見積明細の「担当者按分」を入力すると表示されます。
                                                </div>
                                            ) : (
                                                <>
                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                            <div className="text-sm font-semibold text-slate-900">空きが多い順</div>
                                                            <div className="mt-3 space-y-3">
                                                                {peopleTopAvailable.map((row) => (
                                                                    <div key={`${key}-available-${row.rank}-${row.name}`} className="flex items-start justify-between gap-3 rounded-lg bg-white px-3 py-2">
                                                                        <div>
                                                                            <div className="font-medium text-slate-900">{row.name}</div>
                                                                            <div className="text-xs text-slate-500">予定 {formatPersonDays(row.planned_person_days)} / 稼働率 {formatPercent(row.utilization_rate)}</div>
                                                                        </div>
                                                                        <div className={`text-sm font-semibold ${varianceTone(row.available_person_days)}`}>
                                                                            {formatPersonDays(row.available_person_days)}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                            <div className="text-sm font-semibold text-slate-900">高稼働順</div>
                                                            <div className="mt-3 space-y-3">
                                                                {peopleTopLoad.map((row) => (
                                                                    <div key={`${key}-load-${row.rank}-${row.name}`} className="flex items-start justify-between gap-3 rounded-lg bg-white px-3 py-2">
                                                                        <div>
                                                                            <div className="font-medium text-slate-900">{row.name}</div>
                                                                            <div className="text-xs text-slate-500">残余 {formatPersonDays(row.available_person_days)} / 案件 {row.estimate_count}件</div>
                                                                        </div>
                                                                        <div className={`text-sm font-semibold ${varianceTone(100 - row.utilization_rate)}`}>
                                                                            {formatPercent(row.utilization_rate)}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead className="w-[56px]">順位</TableHead>
                                                                <TableHead>担当者</TableHead>
                                                                <TableHead className="text-right">予定工数</TableHead>
                                                                <TableHead className="text-right">残余</TableHead>
                                                                <TableHead className="text-right">稼働率</TableHead>
                                                                <TableHead className="text-right">案件数</TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {peopleRows.map((row) => (
                                                                <TableRow key={`${key}-people-${row.rank}-${row.name}`}>
                                                                    <TableCell>{row.rank}</TableCell>
                                                                    <TableCell>
                                                                        <div className="font-medium text-slate-900">{row.name}</div>
                                                                        {Array.isArray(row.latest_titles) && row.latest_titles.length > 0 && (
                                                                            <div className="mt-1 text-[11px] text-slate-500">
                                                                                {row.latest_titles.slice(0, 2).join(' / ')}
                                                                            </div>
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="text-right">{formatPersonDays(row.planned_person_days)}</TableCell>
                                                                    <TableCell className={`text-right ${varianceTone(row.available_person_days)}`}>{formatPersonDays(row.available_person_days)}</TableCell>
                                                                    <TableCell className={`text-right ${varianceTone(100 - row.utilization_rate)}`}>{formatPercent(row.utilization_rate)}</TableCell>
                                                                    <TableCell className="text-right">{row.estimate_count}件</TableCell>
                                                                </TableRow>
                                                            ))}
                                                        </TableBody>
                                                    </Table>
                                                </>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-2">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle>上位顧客</CardTitle>
                                            <CardDescription>{section?.label} の当月売上上位</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead className="w-[56px]">順位</TableHead>
                                                        <TableHead>顧客</TableHead>
                                                        <TableHead className="text-right">売上</TableHead>
                                                        <TableHead className="text-right">粗利</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {customerRanking.length === 0 && (
                                                        <TableRow>
                                                            <TableCell colSpan={4} className="text-slate-500">当月データがありません。</TableCell>
                                                        </TableRow>
                                                    )}
                                                    {customerRanking.map((row) => (
                                                        <TableRow key={`${key}-customer-${row.rank}-${row.name}`}>
                                                            <TableCell>{row.rank}</TableCell>
                                                            <TableCell>{row.name}</TableCell>
                                                            <TableCell className="text-right">{formatCurrency(row.sales)}</TableCell>
                                                            <TableCell className="text-right">{formatCurrency(row.gross_profit)}</TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle>{key === 'maintenance' ? 'サポート種別' : '担当者ランキング'}</CardTitle>
                                            <CardDescription>{section?.label} の当月売上上位</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead className="w-[56px]">順位</TableHead>
                                                        <TableHead>{key === 'maintenance' ? '種別' : '担当'}</TableHead>
                                                        <TableHead className="text-right">売上</TableHead>
                                                        <TableHead className="text-right">粗利</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {staffRanking.length === 0 && (
                                                        <TableRow>
                                                            <TableCell colSpan={4} className="text-slate-500">当月データがありません。</TableCell>
                                                        </TableRow>
                                                    )}
                                                    {staffRanking.map((row) => (
                                                        <TableRow key={`${key}-staff-${row.rank}-${row.name}`}>
                                                            <TableCell>{row.rank}</TableCell>
                                                            <TableCell>{row.name}</TableCell>
                                                            <TableCell className="text-right">{formatCurrency(row.sales)}</TableCell>
                                                            <TableCell className="text-right">{formatCurrency(row.gross_profit)}</TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                </div>

                                <Card className="border-slate-200">
                                    <CardHeader>
                                        <CardTitle>{section?.label} 月次予実一覧</CardTitle>
                                        <CardDescription>{section?.description}</CardDescription>
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
                                                {currentForecast.length === 0 && (
                                                    <TableRow>
                                                        <TableCell colSpan={8} className="text-slate-500">予実データがありません。</TableCell>
                                                    </TableRow>
                                                )}
                                                {currentForecast.map((row) => (
                                                    <TableRow key={`${key}-${row.month_key}`}>
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
                            </TabsContent>
                        );
                    })}
                </Tabs>

                <Card className="border-slate-200">
                    <CardHeader className="space-y-3">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="h-4 w-4" />
                                    事業区分分析
                                </CardTitle>
                                <CardDescription className="mt-2">
                                    {businessDivisionBasis.label ?? '請求実績ベース'}で事業区分ごとの請求構成を確認
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="border-slate-200 bg-slate-50">
                                    対象: {businessDivisionReport?.period?.label ?? `${selectedYear}年`}
                                </Badge>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={route('products.index')}>商品管理で区分を修正</Link>
                                </Button>
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <div className="font-medium text-slate-900">集計根拠</div>
                            <div className="mt-1">{businessDivisionBasis.detail ?? '請求データと商品マスタの事業区分設定を使います。'}</div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {businessDivisionCards.length > 0 ? (
                            <>
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    {businessDivisionCards.map((card) => (
                                        <div key={card.key} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <div className="text-xs text-slate-500">{card.label}</div>
                                            <div className="mt-2 text-xl font-semibold text-slate-900">{formatCurrency(card.amount)}</div>
                                            <div className="mt-1 text-xs text-slate-500">構成比 {formatPercent(card.share)}</div>
                                        </div>
                                    ))}
                                </div>

                                <Card className="border-slate-200">
                                    <CardHeader>
                                        <CardTitle className="text-base">月次推移グラフ</CardTitle>
                                        <CardDescription>上位事業区分の請求実績を月別に比較</CardDescription>
                                    </CardHeader>
                                    <CardContent className="h-[320px]">
                                        {businessDivisionChartSeries.length > 0 ? (
                                            <SimpleComboChart
                                                data={businessDivisionChartRows}
                                                bars={businessDivisionChartSeries}
                                            />
                                        ) : (
                                            <EmptyChartState message="グラフ化できる事業区分データがありません。" />
                                        )}
                                    </CardContent>
                                </Card>

                                <div className="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="text-base">月別推移</CardTitle>
                                            <CardDescription>{businessDivisionReport?.period?.label ?? `${selectedYear}年`} の請求実績</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="overflow-x-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead className="min-w-[100px]">月</TableHead>
                                                            {Object.entries(businessDivisionLabels).map(([key, label]) => (
                                                                <TableHead key={key} className="min-w-[120px] text-right">{label}</TableHead>
                                                            ))}
                                                            <TableHead className="min-w-[120px] text-right">合計</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {businessDivisionMonthlyData.map((row) => (
                                                            <TableRow key={row.month}>
                                                                <TableCell className="font-medium">{row.label}</TableCell>
                                                                {Object.keys(businessDivisionLabels).map((key) => (
                                                                    <TableCell key={key} className="text-right">{formatCurrency(row.divisions?.[key] ?? 0)}</TableCell>
                                                                ))}
                                                                <TableCell className="text-right font-semibold">{formatCurrency(row.total ?? 0)}</TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-slate-200">
                                        <CardHeader>
                                            <CardTitle className="text-base">{businessDivisionFocusMonthLabel} の請求明細</CardTitle>
                                            <CardDescription>事業区分の中身を明細で確認</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={selectedBusinessDivision === 'all' ? 'default' : 'outline'}
                                                    onClick={() => setSelectedBusinessDivision('all')}
                                                >
                                                    全て
                                                </Button>
                                                {businessDivisionCards.map((card) => (
                                                    <Button
                                                        key={card.key}
                                                        type="button"
                                                        size="sm"
                                                        variant={selectedBusinessDivision === card.key ? 'default' : 'outline'}
                                                        onClick={() => setSelectedBusinessDivision(card.key)}
                                                    >
                                                        {card.label}
                                                    </Button>
                                                ))}
                                            </div>
                                            <div className="max-h-[360px] overflow-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>顧客</TableHead>
                                                            <TableHead>品目</TableHead>
                                                            <TableHead className="text-right">売上</TableHead>
                                                            <TableHead className="text-right">粗利</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {filteredBusinessDivisionDetails.length === 0 && (
                                                            <TableRow>
                                                                <TableCell colSpan={4} className="text-slate-500">対象明細はありません。</TableCell>
                                                            </TableRow>
                                                        )}
                                                        {filteredBusinessDivisionDetails.map((row, index) => (
                                                            <TableRow key={`${row.customer_name}-${row.item_name}-${index}`}>
                                                                <TableCell>
                                                                    <div className="font-medium">{row.customer_name}</div>
                                                                    <div className="text-xs text-slate-500">{row.division_label}</div>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="font-medium">{row.item_name}</div>
                                                                    {row.detail && <div className="text-xs text-slate-500">{row.detail}</div>}
                                                                </TableCell>
                                                                <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                                                <TableCell className="text-right">{formatCurrency(row.gross_profit)}</TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </>
                        ) : (
                            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                請求実績がまだ無いため、事業区分分析は表示できません。
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center"><BarChart3 className="mr-2 h-5 w-5" />売上ランキング</CardTitle>
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
                                    <CardTitle className="flex items-center"><ListChecks className="mr-2 h-5 w-5" />やることリスト</CardTitle>
                                    <CardDescription>承認タスク（申請日降順）</CardDescription>
                                </div>
                                <ToggleGroup type="single" value={filter} onValueChange={(value) => value && setFilter(value)} className="gap-1">
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
