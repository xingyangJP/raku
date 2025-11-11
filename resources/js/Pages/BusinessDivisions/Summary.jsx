import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

const currencyFormatter = new Intl.NumberFormat('ja-JP', {
    style: 'currency',
    currency: 'JPY',
    maximumFractionDigits: 0,
});

export default function BusinessDivisionSummary({ auth, filters, divisionLabels, monthlyData, divisionTotals, grandTotal, detailRows, businessDivisionOptions = {} }) {
    const [fromMonth, setFromMonth] = useState(filters.from);
    const [toMonth, setToMonth] = useState(filters.to);
    const [selectedDivision, setSelectedDivision] = useState(null);

    const divisionEntries = useMemo(() => Object.entries(divisionLabels), [divisionLabels]);
    const visibleDivisionEntries = useMemo(() => {
        return divisionEntries.filter(([key]) => Number(divisionTotals[key] || 0) !== 0);
    }, [divisionEntries, divisionTotals]);
    const cardDivisionEntries = visibleDivisionEntries.length > 0 ? visibleDivisionEntries : divisionEntries;
    const tableDivisionEntries = cardDivisionEntries;
    const businessDivisionOptionsArray = useMemo(() => Object.entries(businessDivisionOptions).map(([value, meta]) => ({ value, label: meta.label })), [businessDivisionOptions]);

    const filteredDetails = useMemo(() => {
        if (!selectedDivision) {
            return detailRows;
        }
        return detailRows.filter((row) => row.division_key === selectedDivision);
    }, [detailRows, selectedDivision]);

    const handleApplyFilters = () => {
        router.get(route('businessDivisions.summary'), { from: fromMonth, to: toMonth }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleReset = () => {
        setFromMonth(filters.from);
        setToMonth(filters.to);
        router.get(route('businessDivisions.summary'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const formatCurrency = (value) => currencyFormatter.format(value || 0);

    const handleDivisionChange = (productId, value) => {
        router.post(route('businessDivisions.updateProduct', productId), {
            business_division: value,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold">事業区分集計</h2>}
        >
            <Head title="事業区分集計" />

            <Card>
                <CardHeader>
                    <CardTitle>検索・フィルタ</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="text-sm text-muted-foreground">対象期間 From</label>
                            <input
                                type="month"
                                className="mt-1 w-full rounded-md border border-input px-3 py-2"
                                value={fromMonth}
                                onChange={(e) => setFromMonth(e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-sm text-muted-foreground">対象期間 To</label>
                            <input
                                type="month"
                                className="mt-1 w-full rounded-md border border-input px-3 py-2"
                                value={toMonth}
                                onChange={(e) => setToMonth(e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="mt-4 flex gap-3 justify-end">
                        <Button variant="outline" type="button" onClick={handleReset}>リセット</Button>
                        <Button type="button" onClick={handleApplyFilters}>検索</Button>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {cardDivisionEntries.map(([key, label]) => (
                    <Card
                        key={key}
                        className={`cursor-pointer transition-border ${selectedDivision === key ? 'ring-2 ring-primary' : ''}`}
                        onClick={() => setSelectedDivision((prev) => (prev === key ? null : key))}
                    >
                        <CardHeader>
                            <CardTitle className="text-base">{label}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">{formatCurrency(divisionTotals[key])}</p>
                        </CardContent>
                    </Card>
                ))}
                <Card className="md:col-span-2 lg:col-span-3">
                    <CardHeader>
                        <CardTitle className="text-base">全体合計</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-bold text-primary">{formatCurrency(grandTotal)}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>月別内訳</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>月</TableHead>
                                {tableDivisionEntries.map(([key, label]) => (
                                    <TableHead key={key} className="text-right">{label}</TableHead>
                                ))}
                                <TableHead className="text-right">合計</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {monthlyData.map((row) => (
                                <TableRow key={row.month}>
                                    <TableCell>{row.label}</TableCell>
                                    {tableDivisionEntries.map(([key]) => (
                                        <TableCell key={key} className="text-right">
                                            {formatCurrency(row.divisions[key])}
                                        </TableCell>
                                    ))}
                                    <TableCell className="text-right font-semibold">{formatCurrency(row.total)}</TableCell>
                                </TableRow>
                            ))}
                            <TableRow className="bg-muted/50 font-semibold">
                                <TableCell>合計</TableCell>
                                {tableDivisionEntries.map(([key]) => (
                                    <TableCell key={key} className="text-right">
                                        {formatCurrency(divisionTotals[key])}
                                    </TableCell>
                                ))}
                                <TableCell className="text-right">{formatCurrency(grandTotal)}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        詳細一覧
                        {selectedDivision && (
                            <span className="ml-2 text-sm text-muted-foreground">{divisionLabels[selectedDivision]}</span>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    {filteredDetails.length === 0 ? (
                        <p className="text-sm text-muted-foreground">対象データがありません。</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>月</TableHead>
                                    <TableHead>事業区分</TableHead>
                                    <TableHead>品目名</TableHead>
                                    <TableHead>顧客名</TableHead>
                                    <TableHead>詳細</TableHead>
                                    <TableHead className="text-right">数量</TableHead>
                                    <TableHead className="text-right">金額</TableHead>
                                    <TableHead className="text-right">粗利</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredDetails.map((row, index) => (
                                    <TableRow key={`${row.month}-${index}-${row.item_name}`}>
                                        <TableCell>{row.month_label}</TableCell>
                                        <TableCell>
                                            {row.division_key === 'unclassified' && row.product_id ? (
                                                <Select onValueChange={(value) => handleDivisionChange(row.product_id, value)}>
                                                    <SelectTrigger className="w-40">
                                                        <SelectValue placeholder="未設定" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {businessDivisionOptionsArray.map((option) => (
                                                            <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                row.division_number || row.division_label
                                            )}
                                        </TableCell>
                                        <TableCell>{row.item_name}</TableCell>
                                        <TableCell>{row.customer_name}</TableCell>
                                        <TableCell className="max-w-[300px] truncate" title={row.detail}>{row.detail}</TableCell>
                                        <TableCell className="text-right">{row.quantity}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.amount)}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(row.gross_profit)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

        </AuthenticatedLayout>
    );
}
