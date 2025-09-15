import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { DollarSign, CreditCard } from 'lucide-react';

export default function SalesIndex({ auth, summary, items }) {
    const fmt = (n) => `¥${(n ?? 0).toLocaleString()}`;

    const overdue = (items || []).filter(i => i.category === '期日超過売掛');
    const current = (items || []).filter(i => i.category === '売掛');

    return (
        <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">売掛</h2>}>
            <Head title="売掛" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">売掛（期日未到来）</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{fmt(summary?.current_total)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">期日超過売掛</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{fmt(summary?.overdue_total)}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>期日超過一覧</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>顧客名</TableHead>
                                    <TableHead>件名</TableHead>
                                    <TableHead>請求番号</TableHead>
                                    <TableHead>支払期日</TableHead>
                                    <TableHead className="text-right">金額(税込)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {overdue.map(row => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.partner_name}</TableCell>
                                        <TableCell>{row.title}</TableCell>
                                        <TableCell>{row.billing_number || '-'}</TableCell>
                                        <TableCell>{row.due_date || '-'}</TableCell>
                                        <TableCell className="text-right">{fmt(row.total_price)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>売掛（期日未到来）一覧</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>顧客名</TableHead>
                                    <TableHead>件名</TableHead>
                                    <TableHead>請求番号</TableHead>
                                    <TableHead>支払期日</TableHead>
                                    <TableHead className="text-right">金額(税込)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {current.map(row => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.partner_name}</TableCell>
                                        <TableCell>{row.title}</TableCell>
                                        <TableCell>{row.billing_number || '-'}</TableCell>
                                        <TableCell>{row.due_date || '-'}</TableCell>
                                        <TableCell className="text-right">{fmt(row.total_price)}</TableCell>
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
