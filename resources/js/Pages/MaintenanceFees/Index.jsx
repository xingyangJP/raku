import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ResponsiveContainer, Line, LineChart, Tooltip, XAxis } from 'recharts';
import { Edit2, Plus, RefreshCw, Save, Search, Trash2, TriangleAlert } from 'lucide-react';

const ALL_SUPPORT_TYPES = '__all__';

const formatCurrency = (value) => `¥${Number(value || 0).toLocaleString()}`;

export default function MaintenanceFeesIndex() {
    const { items, summary, filters, chart, flash, api_status: apiStatus } = usePage().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [supportType, setSupportType] = useState(filters?.support_type || ALL_SUPPORT_TYPES);
    const [editingMode, setEditingMode] = useState(false);
    const [localItems, setLocalItems] = useState(items || []);
    const [newRow, setNewRow] = useState({ customer_name: '', maintenance_fee: '', status: '', support_type: '' });

    const availableYears = filters?.available_years || [];
    const initialMonth = filters?.selected_month || '';
    const now = new Date();
    const fallbackYear = now.getFullYear().toString();
    const fallbackMonth = String(now.getMonth() + 1).padStart(2, '0');
    const initialYear = initialMonth
        ? initialMonth.split('-')[0]
        : (availableYears[availableYears.length - 1] || fallbackYear);

    const [year, setYear] = useState(initialYear);
    const [month, setMonth] = useState(initialMonth ? initialMonth.split('-')[1] : fallbackMonth);

    useEffect(() => {
        setLocalItems(items || []);
    }, [items]);

    useEffect(() => {
        setSearch(filters?.search ?? '');
        setSupportType(filters?.support_type || ALL_SUPPORT_TYPES);
    }, [filters?.search, filters?.support_type]);

    const monthOptions = useMemo(() => {
        const byYear = filters?.months_by_year || {};

        return byYear[year] || [];
    }, [filters?.months_by_year, year]);

    useEffect(() => {
        if (monthOptions.length === 0) {
            return;
        }

        if (!month || !monthOptions.includes(month)) {
            setMonth(monthOptions[monthOptions.length - 1]);
        }
    }, [monthOptions, month]);

    const applyFilters = () => {
        if (!year || !month) {
            return;
        }

        router.get(route('maintenance-fees.index'), {
            month: `${year}-${month}`,
            search: search || undefined,
            support_type: supportType === ALL_SUPPORT_TYPES ? undefined : supportType,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleRowChange = (id, field, value) => {
        setLocalItems((prev) => prev.map((item) => (
            item.id === id ? { ...item, [field]: value } : item
        )));
    };

    const handleRowSave = (row) => {
        if (!row.id) {
            return;
        }

        router.patch(route('maintenance-fees.items.update', row.id), {
            customer_name: row.customer_name,
            maintenance_fee: row.maintenance_fee,
            status: row.status,
            support_type: row.support_type,
        }, { preserveScroll: true });
    };

    const handleRowDelete = (row) => {
        if (!row.id) {
            return;
        }
        if (!confirm('この明細を削除しますか？')) {
            return;
        }

        router.delete(route('maintenance-fees.items.delete', row.id), { preserveScroll: true });
    };

    const handleAddRow = () => {
        if (!year || !month) {
            return;
        }
        if (!newRow.customer_name || newRow.maintenance_fee === '') {
            return;
        }

        router.post(route('maintenance-fees.items.store'), {
            month: `${year}-${month}`,
            ...newRow,
        }, {
            preserveScroll: true,
            onSuccess: () => setNewRow({ customer_name: '', maintenance_fee: '', status: '', support_type: '' }),
        });
    };

    return (
        <AuthenticatedLayout header="保守売上管理">
            <Head title="保守売上管理" />

            <div className="container mx-auto space-y-6 py-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold">保守売上管理</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant={editingMode ? 'default' : 'outline'}
                            onClick={() => setEditingMode((value) => !value)}
                            className="flex items-center gap-2"
                        >
                            <Edit2 className="h-4 w-4" />
                            {editingMode ? '編集モード: ON' : '編集モード'}
                        </Button>
                        <Button variant="outline" onClick={() => window.location.reload()} className="flex items-center gap-2">
                            <RefreshCw className="h-4 w-4" />
                            リロード
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.post(route('maintenance-fees.resyncCurrent'), {}, { preserveScroll: true })}
                            className="flex items-center gap-2"
                        >
                            <RefreshCw className="h-4 w-4" />
                            当月を再同期
                        </Button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}

                {apiStatus?.kind === 'error' && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <div className="flex items-start gap-3">
                            <TriangleAlert className="mt-0.5 h-4 w-4 flex-none" />
                            <div className="space-y-1">
                                <p className="font-semibold">API 取得に失敗しました</p>
                                <p>{apiStatus.message}</p>
                            </div>
                        </div>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>フィルタ</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="flex items-center gap-2">
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger className="w-28">
                                        <SelectValue placeholder="年" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(filters?.available_years || []).map((value) => (
                                            <SelectItem key={value} value={value}>{value}年</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={month} onValueChange={setMonth} disabled={!year || monthOptions.length === 0}>
                                    <SelectTrigger className="w-24">
                                        <SelectValue placeholder="月" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthOptions.map((value) => (
                                            <SelectItem key={value} value={value}>{value}月</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Input
                                placeholder="顧客名で検索"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className="w-full sm:w-64"
                            />

                            <Select value={supportType} onValueChange={setSupportType}>
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue placeholder="サポート種別" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_SUPPORT_TYPES}>すべて</SelectItem>
                                    {(filters?.support_type_options || []).map((value) => (
                                        <SelectItem key={value} value={value}>{value}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="secondary" onClick={applyFilters} className="flex items-center gap-2">
                                <Search className="h-4 w-4" />
                                条件を適用
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">数値の状態</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <MetaItem label="対象月" value={summary?.snapshot_month ? summary.snapshot_month.slice(0, 7) : (filters?.selected_month || '未作成')} />
                        <MetaItem label="データソース" value={summary?.meta?.source_label || '未判定'} />
                        <MetaItem label="最終同期" value={summary?.meta?.last_synced_at || '未同期'} />
                        <MetaItem label="手修正件数" value={`${summary?.meta?.manual_edit_count ?? 0} 件`} />
                    </CardContent>
                    <CardContent className="border-t pt-4">
                        <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">適用中条件</div>
                        <div className="mt-2 flex flex-wrap gap-2 text-sm text-slate-700">
                            <Badge variant="outline">年月: {filters?.selected_month || '未選択'}</Badge>
                            <Badge variant="outline">顧客名: {summary?.meta?.applied_filters?.search || '指定なし'}</Badge>
                            <Badge variant="outline">サポート種別: {summary?.meta?.applied_filters?.support_type || 'すべて'}</Badge>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        title="月額保守合計"
                        value={formatCurrency(summary?.displayed_total_fee ?? 0)}
                        sublabel="全体"
                        subvalue={formatCurrency(summary?.overall_total_fee ?? 0)}
                    />
                    <SummaryCard
                        title="アクティブ顧客数"
                        value={`${summary?.displayed_active_count ?? 0} 社`}
                        sublabel="全体"
                        subvalue={`${summary?.overall_active_count ?? 0} 社`}
                    />
                    <SummaryCard
                        title="平均保守金額"
                        value={(summary?.displayed_active_count ?? 0) > 0 ? formatCurrency(summary?.displayed_average_fee ?? 0) : '—'}
                        sublabel="全体"
                        subvalue={(summary?.overall_active_count ?? 0) > 0 ? formatCurrency(summary?.overall_average_fee ?? 0) : '—'}
                    />
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
                        <CardTitle>月額保守一覧</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {editingMode && (
                            <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                                <Input
                                    placeholder="顧客名"
                                    value={newRow.customer_name}
                                    onChange={(event) => setNewRow((row) => ({ ...row, customer_name: event.target.value }))}
                                    className="w-full lg:w-64"
                                />
                                <Input
                                    placeholder="金額"
                                    type="number"
                                    value={newRow.maintenance_fee}
                                    onChange={(event) => setNewRow((row) => ({ ...row, maintenance_fee: event.target.value }))}
                                    className="w-full lg:w-32"
                                />
                                <Input
                                    placeholder="サポート種別"
                                    value={newRow.support_type}
                                    onChange={(event) => setNewRow((row) => ({ ...row, support_type: event.target.value }))}
                                    className="w-full lg:w-40"
                                />
                                <Input
                                    placeholder="ステータス"
                                    value={newRow.status}
                                    onChange={(event) => setNewRow((row) => ({ ...row, status: event.target.value }))}
                                    className="w-full lg:w-32"
                                />
                                <Button onClick={handleAddRow} variant="secondary" className="flex items-center gap-2">
                                    <Plus className="h-4 w-4" />
                                    行を追加
                                </Button>
                            </div>
                        )}

                        {localItems.length === 0 ? (
                            <p className="text-sm text-slate-500">月額保守料金が設定された顧客がありません。</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>顧客名</TableHead>
                                        <TableHead>サポート種別</TableHead>
                                        <TableHead>ステータス</TableHead>
                                        <TableHead>入力元</TableHead>
                                        <TableHead className="text-right">月額保守料金</TableHead>
                                        {editingMode && <TableHead className="w-32 text-center">操作</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {localItems.map((item, index) => (
                                        <TableRow key={`${item.customer_name}-${index}`}>
                                            <TableCell className="font-medium">
                                                {editingMode ? (
                                                    <Input
                                                        value={item.customer_name}
                                                        onChange={(event) => handleRowChange(item.id, 'customer_name', event.target.value)}
                                                    />
                                                ) : item.customer_name}
                                            </TableCell>
                                            <TableCell className="space-x-1">
                                                {editingMode ? (
                                                    <Input
                                                        value={item.support_type || ''}
                                                        onChange={(event) => handleRowChange(item.id, 'support_type', event.target.value)}
                                                    />
                                                ) : (
                                                    Array.isArray(item.support_types) && item.support_types.length > 0
                                                        ? item.support_types.map((value, valueIndex) => (
                                                            <Badge key={`${value}-${valueIndex}`} variant="secondary">{value}</Badge>
                                                        ))
                                                        : <span className="text-sm text-slate-400">未設定</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {editingMode ? (
                                                    <Input
                                                        value={item.status || ''}
                                                        onChange={(event) => handleRowChange(item.id, 'status', event.target.value)}
                                                    />
                                                ) : (
                                                    item.status
                                                        ? <Badge variant="outline" className="text-slate-700">{item.status}</Badge>
                                                        : <span className="text-sm text-slate-400">未設定</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={item.entry_source === 'manual' ? 'default' : 'outline'}>
                                                    {item.entry_source === 'manual' ? '手修正' : 'API'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                {editingMode ? (
                                                    <Input
                                                        type="number"
                                                        value={item.maintenance_fee}
                                                        onChange={(event) => handleRowChange(item.id, 'maintenance_fee', event.target.value)}
                                                        className="text-right"
                                                    />
                                                ) : formatCurrency(item.maintenance_fee)}
                                            </TableCell>
                                            {editingMode && (
                                                <TableCell className="text-center">
                                                    <div className="flex justify-center gap-2">
                                                        <Button size="icon" variant="secondary" onClick={() => handleRowSave(item)}>
                                                            <Save className="h-4 w-4" />
                                                        </Button>
                                                        <Button size="icon" variant="outline" onClick={() => handleRowDelete(item)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            )}
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

function SummaryCard({ title, value, sublabel, subvalue }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-slate-600">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                <div className="text-2xl font-bold text-slate-900">{value}</div>
                <div className="text-xs text-slate-500">{sublabel}: {subvalue}</div>
            </CardContent>
        </Card>
    );
}

function MetaItem({ label, value }) {
    return (
        <div className="rounded-lg border bg-slate-50 px-4 py-3">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-sm font-semibold text-slate-900">{value}</div>
        </div>
    );
}
