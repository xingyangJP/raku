import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Calendar,
    Coins,
    RefreshCw,
    Search,
    TrendingUp,
    UserCog,
    X,
} from 'lucide-react';

const formatCurrency = (value) => `¥${Number(value || 0).toLocaleString()}`;
const formatPersonDays = (value) => `${Number(value || 0).toFixed(1)} 人日`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;

const toMonthKey = (value) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const formatMonth = (value) => {
    if (!value || !/^\d{4}-\d{2}$/.test(value)) return '—';
    const [year, month] = value.split('-');
    return `${year}/${month}`;
};

const formatDate = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}/${m}/${day}`;
};

const currentMonth = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const addMonth = (monthKey, offset = 1) => {
    if (!monthKey || !/^\d{4}-\d{2}$/.test(monthKey)) return null;
    const [year, month] = monthKey.split('-').map(Number);
    const base = new Date(year, month - 1 + offset, 1);
    return `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}`;
};

const combineCashflowRows = (hardwareRows = [], laborRows = []) => {
    const map = new Map();

    hardwareRows.forEach((row) => {
        map.set(row.month, {
            month: row.month,
            outflow: Number(row.outflow || 0),
            hardwareInflow: Number(row.inflow || 0),
            laborInflow: 0,
            plannedEffort: 0,
            utilizationRate: 0,
        });
    });

    laborRows.forEach((row) => {
        const current = map.get(row.month) ?? {
            month: row.month,
            outflow: 0,
            hardwareInflow: 0,
            laborInflow: 0,
            plannedEffort: 0,
            utilizationRate: 0,
        };

        current.laborInflow = Number(row.inflow || 0);
        current.plannedEffort = Number(row.planned_effort || 0);
        current.utilizationRate = Number(row.utilization_rate || 0);
        map.set(row.month, current);
    });

    return Array.from(map.values()).sort((a, b) => a.month.localeCompare(b.month));
};

const buildExecutionStatus = (order, currentMonthKey) => {
    const recognizedMonth = toMonthKey(order.recognized_date);
    const collectionMonth = recognizedMonth ? addMonth(recognizedMonth, 1) : null;
    const hasStaff = Boolean(order.staff_name);
    const effort = Number(order.effort_person_days || 0);

    if (!recognizedMonth) {
        return { label: '納期確認', tone: 'destructive', reason: '納期基準日が未確定です。' };
    }

    if (!hasStaff && effort > 0) {
        return { label: '担当設定', tone: 'destructive', reason: '工数対象なのに担当者がありません。' };
    }

    if (collectionMonth && collectionMonth <= currentMonthKey) {
        return { label: '回収確認', tone: 'warning', reason: '回収予定月に入っています。' };
    }

    if (recognizedMonth <= currentMonthKey) {
        return { label: '請求準備', tone: 'default', reason: '今月納期なので請求準備対象です。' };
    }

    return { label: '納品準備', tone: 'secondary', reason: '次回以降の納期案件です。' };
};

const buildEffortNotice = (order) => {
    if (!order.staff_name && Number(order.effort_person_days || 0) > 0) {
        return { label: '担当未設定', tone: 'destructive' };
    }

    if (Number(order.effort_person_days || 0) >= 20) {
        return { label: '工数大', tone: 'warning' };
    }

    if (Number(order.effort_person_days || 0) > 0) {
        return { label: '工数あり', tone: 'secondary' };
    }

    return { label: '工数小', tone: 'outline' };
};

const badgeClassNames = {
    destructive: 'border-red-200 bg-red-50 text-red-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    default: 'border-sky-200 bg-sky-50 text-sky-700',
    secondary: 'border-slate-200 bg-slate-50 text-slate-700',
    outline: 'border-slate-200 bg-white text-slate-600',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
};

const orderViews = [
    { key: 'all', label: '全件' },
    { key: 'delivery_this_month', label: '今月納期' },
    { key: 'collection_this_month', label: '今月回収' },
    { key: 'staff_missing', label: '担当未設定' },
    { key: 'needs_action', label: '要対応' },
];

export default function OrdersIndex({ auth, orders = [], summary = {}, cashflow = {}, filters = {} }) {
    const [form, setForm] = useState({
        keyword: filters.keyword ?? '',
        customer: filters.customer ?? '',
        staff: filters.staff ?? '',
        delivery_from: filters.delivery_from ?? currentMonth(),
        delivery_to: filters.delivery_to ?? currentMonth(),
        sort: filters.sort ?? 'delivery_desc',
    });
    const [selectedView, setSelectedView] = useState('all');

    const monthKey = currentMonth();
    const nextMonthKey = addMonth(monthKey, 1);

    const stats = useMemo(() => {
        const avg = summary?.count > 0 ? Number(summary.total_amount || 0) / Number(summary.count) : 0;
        return {
            count: Number(summary.count || 0),
            total: Number(summary.total_amount || 0),
            gross: Number(summary.total_gross || 0),
            avg,
            totalEffort: Number(summary.total_effort || 0),
        };
    }, [summary]);

    const activeFilters = useMemo(() => {
        const chips = [];
        if (form.keyword) chips.push({ key: 'keyword', label: `キーワード: ${form.keyword}` });
        if (form.customer) chips.push({ key: 'customer', label: `顧客: ${form.customer}` });
        if (form.staff) chips.push({ key: 'staff', label: `担当: ${form.staff}` });
        if (form.delivery_from) chips.push({ key: 'delivery_from', label: `開始月: ${form.delivery_from}` });
        if (form.delivery_to) chips.push({ key: 'delivery_to', label: `終了月: ${form.delivery_to}` });
        return chips;
    }, [form]);

    const hardwareCashflow = useMemo(() => ({
        summary: cashflow?.hardware?.summary ?? {},
        rows: cashflow?.hardware?.rows ?? [],
        assumption: cashflow?.hardware?.assumption ?? '',
    }), [cashflow]);

    const laborCashflow = useMemo(() => ({
        summary: cashflow?.labor?.summary ?? {},
        rows: cashflow?.labor?.rows ?? [],
        assumption: cashflow?.labor?.assumption ?? '',
    }), [cashflow]);

    const monthlyExecution = useMemo(() => {
        const rows = combineCashflowRows(hardwareCashflow.rows, laborCashflow.rows);
        const current = rows.find((row) => row.month === monthKey) ?? null;
        const next = rows.find((row) => row.month === nextMonthKey) ?? null;
        return { current, next };
    }, [hardwareCashflow.rows, laborCashflow.rows, monthKey, nextMonthKey]);

    const normalizedOrders = useMemo(() => {
        return orders.map((order) => {
            const recognizedMonth = toMonthKey(order.recognized_date);
            const collectionMonth = recognizedMonth ? addMonth(recognizedMonth, 1) : null;
            return {
                ...order,
                recognizedMonth,
                collectionMonth,
                executionStatus: buildExecutionStatus(order, monthKey),
                effortNotice: buildEffortNotice(order),
            };
        });
    }, [orders, monthKey]);

    const viewCounts = useMemo(() => {
        const counts = {
            all: normalizedOrders.length,
            delivery_this_month: 0,
            collection_this_month: 0,
            staff_missing: 0,
            needs_action: 0,
        };

        normalizedOrders.forEach((order) => {
            if (order.recognizedMonth === monthKey) counts.delivery_this_month += 1;
            if (order.collectionMonth === monthKey) counts.collection_this_month += 1;
            if (order.executionStatus.label === '担当設定') counts.staff_missing += 1;
            if (['担当設定', '回収確認', '納期確認'].includes(order.executionStatus.label)) counts.needs_action += 1;
        });

        return counts;
    }, [normalizedOrders, monthKey]);

    const visibleOrders = useMemo(() => {
        switch (selectedView) {
        case 'delivery_this_month':
            return normalizedOrders.filter((order) => order.recognizedMonth === monthKey);
        case 'collection_this_month':
            return normalizedOrders.filter((order) => order.collectionMonth === monthKey);
        case 'staff_missing':
            return normalizedOrders.filter((order) => order.executionStatus.label === '担当設定');
        case 'needs_action':
            return normalizedOrders.filter((order) => ['担当設定', '回収確認', '納期確認'].includes(order.executionStatus.label));
        default:
            return normalizedOrders;
        }
    }, [normalizedOrders, selectedView, monthKey]);

    const orderWorkspaceStats = useMemo(() => {
        const deliveryThisMonth = normalizedOrders.filter((order) => order.recognizedMonth === monthKey);
        const collectionThisMonth = normalizedOrders.filter((order) => order.collectionMonth === monthKey);
        const needsAction = normalizedOrders.filter((order) => ['担当設定', '回収確認', '納期確認'].includes(order.executionStatus.label));
        const missingStaff = normalizedOrders.filter((order) => order.executionStatus.label === '担当設定');

        return {
            deliveryCount: deliveryThisMonth.length,
            deliveryAmount: deliveryThisMonth.reduce((sum, order) => sum + Number(order.total_amount || 0), 0),
            collectionAmount: collectionThisMonth.reduce((sum, order) => sum + Number(order.total_amount || 0), 0),
            needsActionCount: needsAction.length,
            missingStaffCount: missingStaff.length,
            totalEffort: stats.totalEffort,
        };
    }, [normalizedOrders, stats.totalEffort, monthKey]);

    const apply = () => {
        router.get(route('orders.index'), form, { preserveState: true, preserveScroll: true });
    };

    const reset = () => {
        const next = {
            keyword: '',
            customer: '',
            staff: '',
            delivery_from: currentMonth(),
            delivery_to: currentMonth(),
            sort: 'delivery_desc',
        };
        setForm(next);
        router.get(route('orders.index'), next, { preserveState: true, preserveScroll: true });
    };

    const setThisMonth = () => {
        const month = currentMonth();
        const next = { ...form, delivery_from: month, delivery_to: month };
        setForm(next);
        router.get(route('orders.index'), next, { preserveState: true, preserveScroll: true });
    };

    const setLast3Months = () => {
        const now = new Date();
        const from = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        const fromMonth = `${from.getFullYear()}-${String(from.getMonth() + 1).padStart(2, '0')}`;
        const toMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        const next = { ...form, delivery_from: fromMonth, delivery_to: toMonth };
        setForm(next);
        router.get(route('orders.index'), next, { preserveState: true, preserveScroll: true });
    };

    const clearFilter = (key) => {
        const next = { ...form, [key]: '' };
        setForm(next);
        router.get(route('orders.index'), next, { preserveState: true, preserveScroll: true });
    };

    const handleEnterSearch = (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            apply();
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header="注文書一覧">
            <Head title="注文書一覧" />
            <div className="space-y-6">
                <Card className="border-slate-200 shadow-sm">
                    <CardHeader className="space-y-3">
                        <div className="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                            <div className="space-y-2">
                                <CardTitle className="text-2xl">受注後の実行・回収ワークスペース</CardTitle>
                                <CardDescription className="max-w-3xl text-sm leading-6">
                                    `/orders` は、受注確定後の案件を回す画面です。経営分析はダッシュボードで見て、ここでは納期・回収予定・担当・次アクションを優先して見ます。
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2 text-xs text-slate-500">
                                <Badge variant="outline">受注件数 {stats.count}件</Badge>
                                <Badge variant="outline">受注総額 {formatCurrency(stats.total)}</Badge>
                                <Badge variant="outline">平均単価 {formatCurrency(stats.avg)}</Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm"><Calendar className="h-4 w-4" />今月納期</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{orderWorkspaceStats.deliveryCount}件</div>
                                <div className="mt-1 text-sm text-slate-600">{formatCurrency(orderWorkspaceStats.deliveryAmount)}</div>
                            </CardContent>
                        </Card>
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm"><Coins className="h-4 w-4" />今月回収予定</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{formatCurrency(orderWorkspaceStats.collectionAmount)}</div>
                                <div className="mt-1 text-sm text-slate-600">納期翌月入金の近似</div>
                            </CardContent>
                        </Card>
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm"><UserCog className="h-4 w-4" />要対応案件</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{orderWorkspaceStats.needsActionCount}件</div>
                                <div className="mt-1 text-sm text-slate-600">担当未設定 / 回収確認 / 納期確認</div>
                            </CardContent>
                        </Card>
                        <Card className="border-slate-200 shadow-none">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm"><TrendingUp className="h-4 w-4" />計画工数</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{formatPersonDays(orderWorkspaceStats.totalEffort)}</div>
                                <div className="mt-1 text-sm text-slate-600">担当未設定 {orderWorkspaceStats.missingStaffCount}件</div>
                            </CardContent>
                        </Card>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>検索・絞り込み</CardTitle>
                        <CardDescription>キーワード、顧客、担当者、納期月で対象案件を絞ります。</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <Input placeholder="見積番号/件名/顧客名" value={form.keyword} onChange={(e) => setForm((p) => ({ ...p, keyword: e.target.value }))} onKeyDown={handleEnterSearch} />
                            <Input placeholder="顧客名" value={form.customer} onChange={(e) => setForm((p) => ({ ...p, customer: e.target.value }))} onKeyDown={handleEnterSearch} />
                            <Input placeholder="担当者名" value={form.staff} onChange={(e) => setForm((p) => ({ ...p, staff: e.target.value }))} onKeyDown={handleEnterSearch} />
                            <Input type="month" value={form.delivery_from} onChange={(e) => setForm((p) => ({ ...p, delivery_from: e.target.value }))} />
                            <Input type="month" value={form.delivery_to} onChange={(e) => setForm((p) => ({ ...p, delivery_to: e.target.value }))} />
                            <select
                                value={form.sort}
                                onChange={(e) => setForm((p) => ({ ...p, sort: e.target.value }))}
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="delivery_desc">納期が新しい順</option>
                                <option value="delivery_asc">納期が古い順</option>
                                <option value="amount_desc">受注額が大きい順</option>
                                <option value="amount_asc">受注額が小さい順</option>
                                <option value="updated_desc">更新が新しい順</option>
                            </select>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="secondary" onClick={apply}><Search className="mr-2 h-4 w-4" />検索</Button>
                            <Button variant="outline" onClick={setThisMonth}>今月</Button>
                            <Button variant="outline" onClick={setLast3Months}>直近3ヶ月</Button>
                            <Button variant="outline" onClick={reset}><RefreshCw className="mr-2 h-4 w-4" />リセット</Button>
                        </div>
                        {activeFilters.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {activeFilters.map((chip) => (
                                    <Badge key={chip.key} variant="secondary" className="gap-1 pr-1">
                                        {chip.label}
                                        <button type="button" className="rounded p-0.5 hover:bg-slate-200" onClick={() => clearFilter(chip.key)} aria-label={`${chip.label} を削除`}>
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>実行・回収の目安</CardTitle>
                        <CardDescription>受注後の動きだけを簡潔に見ます。詳しい経営分析はダッシュボードへ寄せます。</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        {[{ title: '今月', row: monthlyExecution.current }, { title: '来月', row: monthlyExecution.next }].map(({ title, row }) => (
                            <Card key={title} className="border-slate-200 shadow-none">
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-lg">{title}</CardTitle>
                                            <CardDescription>{row ? formatMonth(row.month) : '—'}</CardDescription>
                                        </div>
                                        <Badge className={row && (row.hardwareInflow + row.laborInflow - row.outflow) >= 0 ? badgeClassNames.success : badgeClassNames.warning}>
                                            {row && (row.hardwareInflow + row.laborInflow - row.outflow) >= 0 ? '資金余力あり' : '資金確認'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {row ? (
                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <div className="rounded-2xl border border-slate-200 p-4">
                                                <div className="text-xs text-slate-500">回収予定</div>
                                                <div className="mt-2 text-xl font-semibold">{formatCurrency(row.hardwareInflow + row.laborInflow)}</div>
                                            </div>
                                            <div className="rounded-2xl border border-slate-200 p-4">
                                                <div className="text-xs text-slate-500">支出予定</div>
                                                <div className="mt-2 text-xl font-semibold">{formatCurrency(row.outflow)}</div>
                                            </div>
                                            <div className="rounded-2xl border border-slate-200 p-4">
                                                <div className="text-xs text-slate-500">計画工数</div>
                                                <div className="mt-2 text-xl font-semibold">{formatPersonDays(row.plannedEffort)}</div>
                                            </div>
                                            <div className="rounded-2xl border border-slate-200 p-4">
                                                <div className="text-xs text-slate-500">稼働率</div>
                                                <div className="mt-2 text-xl font-semibold">{formatPercent(row.utilizationRate)}</div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-slate-500">対象データがありません。</div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>注文書一覧</CardTitle>
                        <CardDescription>受注確定済みデータのみ表示（{visibleOrders.length}件 / 全体 {normalizedOrders.length}件）</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            {orderViews.map((view) => {
                                const active = selectedView === view.key;
                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() => setSelectedView(view.key)}
                                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition ${active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'}`}
                                    >
                                        <span>{view.label}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${active ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'}`}>{viewCounts[view.key]}</span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>見積番号</TableHead>
                                        <TableHead>顧客名 / 件名</TableHead>
                                        <TableHead>担当者</TableHead>
                                        <TableHead>納期</TableHead>
                                        <TableHead>回収予定月</TableHead>
                                        <TableHead>工数注意</TableHead>
                                        <TableHead>次アクション</TableHead>
                                        <TableHead className="text-right">受注額</TableHead>
                                        <TableHead className="text-right">工数</TableHead>
                                        <TableHead className="text-right">操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visibleOrders.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-slate-500">条件に一致する注文書がありません。</TableCell>
                                        </TableRow>
                                    )}
                                    {visibleOrders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-medium">{order.estimate_number}</TableCell>
                                            <TableCell>
                                                <div className="font-medium text-slate-900">{order.customer_name || '—'}</div>
                                                <div className="max-w-[280px] truncate text-sm text-slate-500" title={order.title}>{order.title || '—'}</div>
                                            </TableCell>
                                            <TableCell>{order.staff_name || '—'}</TableCell>
                                            <TableCell>
                                                <div>{formatDate(order.recognized_date)}</div>
                                                <div className="text-[11px] text-slate-500">発行 {formatDate(order.issue_date)}</div>
                                            </TableCell>
                                            <TableCell>{formatMonth(order.collectionMonth)}</TableCell>
                                            <TableCell>
                                                <Badge className={badgeClassNames[order.effortNotice.tone] ?? badgeClassNames.outline}>{order.effortNotice.label}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <Badge className={badgeClassNames[order.executionStatus.tone] ?? badgeClassNames.outline}>{order.executionStatus.label}</Badge>
                                                    <span className="text-xs text-slate-500">{order.executionStatus.reason}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">{formatCurrency(order.total_amount)}</TableCell>
                                            <TableCell className="text-right">{formatPersonDays(order.effort_person_days)}</TableCell>
                                            <TableCell className="text-right space-x-2 whitespace-nowrap">
                                                <Link href={route('estimates.edit', { estimate: order.id })} className="text-sm text-indigo-600 hover:text-indigo-800">詳細</Link>
                                                <a href={route('estimates.purchaseOrder.preview', { estimate: order.id })} target="_blank" rel="noreferrer" className="text-sm text-indigo-600 hover:text-indigo-800">注文書</a>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
