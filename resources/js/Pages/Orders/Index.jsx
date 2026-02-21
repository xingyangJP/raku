import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Search, RefreshCw, FileText, Calendar, TrendingUp, ClipboardCheck, X } from 'lucide-react';

const formatCurrency = (value) => `¥${Number(value || 0).toLocaleString()}`;
const formatPersonDays = (value) => `${Number(value || 0).toFixed(1)} 人日`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;
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

export default function OrdersIndex({ auth, orders = [], summary = {}, cashflow = {}, filters = {} }) {
    const [form, setForm] = useState({
        keyword: filters.keyword ?? '',
        customer: filters.customer ?? '',
        staff: filters.staff ?? '',
        delivery_from: filters.delivery_from ?? currentMonth(),
        delivery_to: filters.delivery_to ?? currentMonth(),
        sort: filters.sort ?? 'delivery_desc',
    });

    const stats = useMemo(() => {
        const avg = summary?.count > 0 ? Number(summary.total_amount || 0) / Number(summary.count) : 0;
        return {
            count: Number(summary.count || 0),
            total: Number(summary.total_amount || 0),
            gross: Number(summary.total_gross || 0),
            avg,
            thisMonthCount: Number(summary.current_month_count || 0),
            thisMonthTotal: Number(summary.current_month_total || 0),
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
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm flex items-center"><ClipboardCheck className="h-4 w-4 mr-2" />受注件数</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{stats.count}件</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm flex items-center"><FileText className="h-4 w-4 mr-2" />受注総額</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{formatCurrency(stats.total)}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm flex items-center"><TrendingUp className="h-4 w-4 mr-2" />粗利総額</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{formatCurrency(stats.gross)}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm">平均受注単価</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{formatCurrency(stats.avg)}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm flex items-center"><Calendar className="h-4 w-4 mr-2" />当月納期</CardTitle></CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold">{stats.thisMonthCount}件</div>
                            <div className="text-xs text-slate-600 mt-1">{formatCurrency(stats.thisMonthTotal)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm">計画工数</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{formatPersonDays(summary.total_effort)}</div></CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>検索・絞り込み</CardTitle>
                        <CardDescription>キーワード、顧客、担当者、納期月で高速フィルタ</CardDescription>
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
                            <Button variant="secondary" onClick={apply}><Search className="h-4 w-4 mr-2" />検索</Button>
                            <Button variant="outline" onClick={setThisMonth}>今月</Button>
                            <Button variant="outline" onClick={setLast3Months}>直近3ヶ月</Button>
                            <Button variant="outline" onClick={reset}><RefreshCw className="h-4 w-4 mr-2" />リセット</Button>
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
                        <CardTitle>資金繰りダッシュボード（ハードウェア / 変動仕入）</CardTitle>
                        <CardDescription>{hardwareCashflow.assumption}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">注文月支出 合計</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(hardwareCashflow.summary.outflow_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">回収入金 合計</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(hardwareCashflow.summary.inflow_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">資金収支（入金-支出）</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(hardwareCashflow.summary.net_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">納期月売上</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(hardwareCashflow.summary.revenue_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">納期月粗利</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(hardwareCashflow.summary.gross_total)}</div></CardContent></Card>
                        </div>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>月</TableHead>
                                        <TableHead className="text-right">注文月支出</TableHead>
                                        <TableHead className="text-right">回収入金</TableHead>
                                        <TableHead className="text-right">資金収支（入金-支出）</TableHead>
                                        <TableHead className="text-right">納期月売上</TableHead>
                                        <TableHead className="text-right">納期月粗利</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {hardwareCashflow.rows.length === 0 && (
                                        <TableRow><TableCell colSpan={6}>データがありません。</TableCell></TableRow>
                                    )}
                                    {hardwareCashflow.rows.map((row) => (
                                        <TableRow key={`hardware-${row.month}`}>
                                            <TableCell>{formatMonth(row.month)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.outflow)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.inflow)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.net)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.revenue)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.gross)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>資金繰りダッシュボード（人件費 / 固定費）</CardTitle>
                        <CardDescription>{laborCashflow.assumption}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">固定人件費（月額）</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(laborCashflow.summary.fixed_cost_per_month)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">固定人件費（期間合計）</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(laborCashflow.summary.fixed_cost_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">回収入金 合計</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(laborCashflow.summary.inflow_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">資金収支（回収-固定費）</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatCurrency(laborCashflow.summary.net_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">計画工数 合計</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatPersonDays(laborCashflow.summary.planned_effort_total)}</div></CardContent></Card>
                            <Card><CardHeader className="pb-2"><CardTitle className="text-xs">月間キャパ</CardTitle></CardHeader><CardContent><div className="text-xl font-bold">{formatPersonDays(laborCashflow.summary.capacity_per_month)}</div></CardContent></Card>
                        </div>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>月</TableHead>
                                        <TableHead className="text-right">計画工数</TableHead>
                                        <TableHead className="text-right">稼働率</TableHead>
                                        <TableHead className="text-right">納期月売上</TableHead>
                                        <TableHead className="text-right">回収入金</TableHead>
                                        <TableHead className="text-right">固定人件費</TableHead>
                                        <TableHead className="text-right">資金収支（回収-固定費）</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {laborCashflow.rows.length === 0 && (
                                        <TableRow><TableCell colSpan={7}>データがありません。</TableCell></TableRow>
                                    )}
                                    {laborCashflow.rows.map((row) => (
                                        <TableRow key={`labor-${row.month}`}>
                                            <TableCell>{formatMonth(row.month)}</TableCell>
                                            <TableCell className="text-right">{formatPersonDays(row.planned_effort)}</TableCell>
                                            <TableCell className="text-right">{formatPercent(row.utilization_rate)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.revenue)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.inflow)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.fixed_cost)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(row.net)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>注文書一覧</CardTitle>
                        <CardDescription>受注確定済みデータのみ表示（{orders.length}件）</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>見積番号</TableHead>
                                    <TableHead>顧客名</TableHead>
                                    <TableHead>件名</TableHead>
                                    <TableHead>担当者</TableHead>
                                    <TableHead>納期</TableHead>
                                    <TableHead className="text-right">受注額</TableHead>
                                    <TableHead className="text-right">粗利</TableHead>
                                    <TableHead className="text-right">工数</TableHead>
                                    <TableHead className="text-right">操作</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={9} className="text-slate-500">条件に一致する注文書がありません。</TableCell>
                                    </TableRow>
                                )}
                                {orders.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell className="font-medium">{order.estimate_number}</TableCell>
                                        <TableCell>{order.customer_name || '—'}</TableCell>
                                        <TableCell className="max-w-[280px] truncate" title={order.title}>{order.title || '—'}</TableCell>
                                        <TableCell>{order.staff_name || '—'}</TableCell>
                                        <TableCell>
                                            {formatDate(order.recognized_date)}
                                            <div className="text-[11px] text-slate-500">発行 {formatDate(order.issue_date)}</div>
                                        </TableCell>
                                        <TableCell className="text-right">{formatCurrency(order.total_amount)}</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(order.gross_amount)}
                                            <div className="mt-1"><Badge variant="secondary">確定</Badge></div>
                                        </TableCell>
                                        <TableCell className="text-right">{formatPersonDays(order.effort_person_days)}</TableCell>
                                        <TableCell className="text-right space-x-2">
                                            <Link href={route('estimates.edit', { estimate: order.id })} className="text-indigo-600 hover:text-indigo-800 text-sm">詳細</Link>
                                            <a href={route('estimates.purchaseOrder.preview', { estimate: order.id })} target="_blank" rel="noreferrer" className="text-indigo-600 hover:text-indigo-800 text-sm">注文書</a>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
