
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table"

const invoices = [
    {
        invoice: "U-000123",
        customer: "株式会社A",
        billingTo: "株式会社A",
        date: "2025-08-23",
        total: "¥250,000",
        profit: "¥50,000",
    },
    {
        invoice: "U-000124",
        customer: "B商事",
        billingTo: "B商事",
        date: "2025-08-23",
        total: "¥150,000",
        profit: "¥30,000",
    },
    {
        invoice: "U-000125",
        customer: "株式会社C",
        billingTo: "株式会社C",
        date: "2025-08-22",
        total: "¥350,000",
        profit: "¥75,000",
    },
    {
        invoice: "U-000126",
        customer: "D工業",
        billingTo: "D工業",
        date: "2025-08-21",
        total: "¥450,000",
        profit: "¥90,000",
    },
];

export default function SalesIndex() {
    return (
        <AuthenticatedLayout
            header="売上一覧"
        >
            <Head title="売上一覧" />

            <div className="flex justify-end mb-4">
                <Button>新規登録</Button>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>売上番号</TableHead>
                            <TableHead>日付</TableHead>
                            <TableHead>請求先</TableHead>
                            <TableHead>得意先</TableHead>
                            <TableHead className="text-right">合計金額</TableHead>
                            <TableHead className="text-right">粗利</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {invoices.map((invoice) => (
                            <TableRow key={invoice.invoice}>
                                <TableCell className="font-medium">{invoice.invoice}</TableCell>
                                <TableCell>{invoice.date}</TableCell>
                                <TableCell>{invoice.billingTo}</TableCell>
                                <TableCell>{invoice.customer}</TableCell>
                                <TableCell className="text-right">{invoice.total}</TableCell>
                                <TableCell className="text-right">{invoice.profit}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AuthenticatedLayout>
    );
}
