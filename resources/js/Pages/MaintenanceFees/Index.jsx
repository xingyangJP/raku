import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { RefreshCw } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ResponsiveContainer, LineChart, Line, XAxis, Tooltip } from 'recharts';

const formatCurrency = (value) => `¥${Number(value || 0).toLocaleString()}`;

export default function MaintenanceFeesIndex() {
    const { items, summary, filters, chart } = usePage().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const availableYears = filters?.available_years || [];
    const initialMonth = filters?.selected_month || '';
    const initialYear = initialMonth
        ? initialMonth.split('-')[0]
        : (availableYears[availableYears.length - 1] || '');
    const [year, setYear] = useState(initialYear);
    const [month, setMonth] = useState(initialMonth ? initialMonth.split('-')[1] : '');

    const monthOptions = useMemo(() => {
        const byYear = filters?.months_by_year || {};
        return byYear[year] || [];
    }, [filters?.months_by_year, year]);

    useEffect(() => {
        if (monthOptions.length === 0) return;
        if (!month || !monthOptions.includes(month)) {
            setMonth(monthOptions[monthOptions.length - 1]);
        }
    }, [monthOptions, month]);

    const applyMonthFilter = () => {
        if (!year || !month) return;
        router.get(route('maintenance-fees.index'), { month: `${year}-${month}` }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const filteredItems = useMemo(() => {
        return items.filter((item) => {
            if (search && !item.customer_name.toLowerCase().includes(search.toLowerCase())) {
                return false;
            }
            return true;
        });
    }, [items, search]);

    return (
        <AuthenticatedLayout header="保守売上管理">
            <Head title="保守売上管理" />
            <div className="container mx-auto py-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">保守売上管理</h1>
                    <Button variant="outline" onClick={() => window.location.reload()} className="flex items-center gap-2">
                        <RefreshCw className="h-4 w-4" />
                        リロード
                </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <SummaryCard title="月額保守合計" value={formatCurrency(summary?.total_fee ?? 0)} />
                <SummaryCard title="アクティブ顧客数" value={`${summary?.active_count ?? 0} 社`} />
                <SummaryCard title="平均保守金額" value={summary?.active_count ? formatCurrency(summary?.average_fee ?? 0) : '—'} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>直近6ヶ月の推移</CardTitle>
                </CardHeader>
                <CardContent className="h-64">
                    {Array.isArray(chart) && chart.length > 0 ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={chart}>
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                <Tooltip formatter={(value) => formatCurrency(value)} />
                                <Line type="monotone" dataKey="total" stroke="#2563eb" strokeWidth={2} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    ) : (
                        <p className="text-sm text-slate-500">スナップショットがありません。</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>フィルタ</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="flex gap-2 items-center">
                        <Select value={year} onValueChange={setYear}>
                            <SelectTrigger className="w-28">
                                <SelectValue placeholder="年" />
                            </SelectTrigger>
                            <SelectContent>
                                {(filters?.available_years || []).map((y) => (
                                    <SelectItem key={y} value={y}>{y}年</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={month} onValueChange={setMonth} disabled={!year || monthOptions.length === 0}>
                            <SelectTrigger className="w-24">
                                <SelectValue placeholder="月" />
                            </SelectTrigger>
                            <SelectContent>
                                {monthOptions.map((m) => (
                                    <SelectItem key={m} value={m}>{m}月</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="secondary" onClick={applyMonthFilter} disabled={!year || !month}>月を適用</Button>
                        <Button variant="outline" onClick={() => window.location.reload()} className="flex items-center gap-2">
                            <RefreshCw className="h-4 w-4" />
                            リロード
                        </Button>
                    </div>
                    <Input
                        placeholder="顧客名で検索"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-64"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>月額保守一覧</CardTitle>
                </CardHeader>
                <CardContent>
                    {filteredItems.length === 0 ? (
                        <p className="text-slate-500 text-sm">月額保守料金が設定された顧客がありません。</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>顧客名</TableHead>
                                    <TableHead>サポート種別</TableHead>
                                    <TableHead>ステータス</TableHead>
                                    <TableHead className="text-right">月額保守料金</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredItems.map((item, idx) => (
                                    <TableRow key={`${item.customer_name}-${idx}`}>
                                        <TableCell className="font-medium">{item.customer_name}</TableCell>
                                        <TableCell className="space-x-1">
                                            {Array.isArray(item.support_types) && item.support_types.length > 0 ? (
                                                item.support_types.map((t, i) => (
                                                    <Badge key={`${t}-${i}`} variant="secondary">{t}</Badge>
                                                ))
                                            ) : (
                                                item.support_type
                                                    ? <Badge variant="secondary">{item.support_type}</Badge>
                                                    : <span className="text-slate-400 text-sm">未設定</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {item.status ? (
                                                <Badge variant="outline" className="text-slate-700">
                                                    {item.status}
                                                </Badge>
                                            ) : (
                                                <span className="text-slate-400 text-sm">未設定</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">{formatCurrency(item.maintenance_fee)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function SummaryCard({ title, value }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-slate-600">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-slate-900">{value}</div>
            </CardContent>
        </Card>
    );
}
