import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, DropdownMenuCheckboxItem, DropdownMenuLabel, DropdownMenuSeparator } from "@/Components/ui/dropdown-menu";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogTrigger } from "@/Components/ui/dialog";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { DollarSign, Users, CreditCard, Activity, ListFilter, PlusCircle, FileDown, Search } from 'lucide-react';

export default function SalesIndex({ auth }) {
    // Mock data based on detailed requirements
    const salesData = [
        {
            id: 1, salesDate: '2025-08-23', customerName: '株式会社A', invoiceId: 'INV-00123', title: 'Webサイト制作プロジェクト',
            service: 'デザイン、コーディング', quantity: 1, unitPrice: 1200000, amount: 1200000, tax: 120000, totalAmount: 1320000,
            salesRep: '山田 太郎', status: '請求済', dueDate: '2025-09-30', paymentStatus: '未入金', billedDate: '2025-08-25'
        },
        {
            id: 2, salesDate: '2025-08-22', customerName: '株式会社B', invoiceId: 'INV-00124', title: '保守契約（月次）',
            service: 'サーバー保守、サポート', quantity: 1, unitPrice: 50000, amount: 50000, tax: 5000, totalAmount: 55000,
            salesRep: '鈴木 花子', status: '入金済', dueDate: '2025-08-31', paymentStatus: '完了', billedDate: '2025-08-01'
        },
        {
            id: 3, salesDate: '2025-08-21', customerName: '株式会社C', invoiceId: 'INV-00125', title: 'コンサルティング',
            service: 'マーケティング戦略', quantity: 10, unitPrice: 15000, amount: 150000, tax: 15000, totalAmount: 165000,
            salesRep: '山田 太郎', status: '未請求', dueDate: '2025-09-20', paymentStatus: '未入金', billedDate: null
        },
    ];

    const getStatusBadge = (status) => {
        switch (status) {
            case '入金済': return <Badge variant="default" className="bg-green-500 text-white">入金済</Badge>;
            case '請求済': return <Badge variant="secondary">請求済</Badge>;
            case '未請求': return <Badge variant="destructive">未請求</Badge>;
            default: return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">売上管理</h2>}>
            <Head title="Sales Management" />

            <div className="space-y-6">
                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">今月の売上合計 (税込)</CardTitle><DollarSign className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">¥1,540,000</div><p className="text-xs text-muted-foreground">前月比 +15.2%</p></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">未請求額</CardTitle><CreditCard className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">¥165,000</div><p className="text-xs text-muted-foreground">1案件</p></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">未入金額</CardTitle><Users className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">¥1,320,000</div><p className="text-xs text-muted-foreground">1案件</p></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">担当別売上 (今月)</CardTitle><Activity className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-sm">1. 山田 太郎: ¥1,365,000</div><div className="text-sm">2. 鈴木 花子: ¥55,000</div></CardContent></Card>
                </div>

                {/* Filters Accordion */}
                <Accordion type="single" collapsible defaultValue="filters" className="w-full">
                    <AccordionItem value="filters">
                        <AccordionTrigger>
                        </AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                    <Input placeholder="顧客名, 番号..." />
                                    <Input type="date" placeholder="請求日(開始)" />
                                    <Input type="date" placeholder="請求日(終了)" />
                                    <Button className="flex items-center gap-2"><Search className="h-4 w-4"/>検索</Button>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                {/* Main Table Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>売上一覧</CardTitle>
                            <CardDescription>全 {salesData.length} 件</CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" className="flex items-center gap-2"><FileDown className="h-4 w-4"/>エクスポート</Button>
                            <Button className="flex items-center gap-2"><PlusCircle className="h-4 w-4"/>新規売上登録</Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>売上日</TableHead>
                                    <TableHead>顧客名 / 案件名</TableHead>
                                    <TableHead>担当者</TableHead>
                                    <TableHead>金額 (税込)</TableHead>
                                    <TableHead>ステータス</TableHead>
                                    <TableHead>請求日</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {salesData.map((sale) => (
                                    <Dialog key={sale.id}>
                                        <DialogTrigger asChild>
                                            <TableRow className="cursor-pointer">
                                                <TableCell>{sale.salesDate}</TableCell>
                                                <TableCell><div className="font-medium">{sale.customerName}</div><div className="text-sm text-muted-foreground">{sale.title}</div></TableCell>
                                                <TableCell>{sale.salesRep}</TableCell>
                                                <TableCell className="font-semibold">¥{sale.totalAmount.toLocaleString()}</TableCell>
                                                <TableCell>{getStatusBadge(sale.status)}</TableCell>
                                                <TableCell>{sale.billedDate || '-'}</TableCell>
                                            </TableRow>
                                        </DialogTrigger>
                                        <DialogContent className="sm:max-w-2xl">
                                            <DialogHeader><DialogTitle>売上詳細: {sale.invoiceId}</DialogTitle><DialogDescription>{sale.customerName} - {sale.title}</DialogDescription></DialogHeader>
                                            <div className="grid grid-cols-2 gap-4 py-4">
                                                <div><h3 className="font-semibold">請求情報</h3><p>売上金額(税抜): ¥{sale.amount.toLocaleString()}</p><p>消費税: ¥{sale.tax.toLocaleString()}</p><p>合計金額(税込): ¥{sale.totalAmount.toLocaleString()}</p></div>
                                                <div><h3 className="font-semibold">ステータス情報</h3><p>請求ステータス: {sale.status}</p><p>入金ステータス: {sale.paymentStatus}</p><p>入金予定日: {sale.dueDate}</p></div>
                                                <div><h3 className="font-semibold">商品・サービス</h3><p>{sale.service}</p></div>
                                                <div><h3 className="font-semibold">担当者</h3><p>{sale.salesRep}</p></div>
                                            </div>
                                            <DialogFooter><Button>入金管理画面へ</Button><Button variant="secondary">請求書PDF</Button></DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
