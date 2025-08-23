import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetTrigger, SheetFooter } from "@/Components/ui/sheet";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { DollarSign, Users, CreditCard, AlertTriangle, BarChart, FileDown, PlusCircle, Search } from 'lucide-react';

export default function BillingIndex({ auth }) {
    const invoices = [
        {
            id: 'INV-00123', customerName: '株式会社A', title: 'Webサイト制作プロジェクト', billedDate: '2025-08-25', dueDate: '2025-09-30',
            amount: 1200000, tax: 120000, totalAmount: 1320000, paidAmount: 0, balance: 1320000,
            status: '請求済', salesRep: '山田 太郎', paymentTerms: '月末締め翌月末払い', issueStatus: '発行済', deliveryStatus: 'メール送付済'
        },
        {
            id: 'INV-00122', customerName: '株式会社B', title: '保守契約（8月）', billedDate: '2025-08-01', dueDate: '2025-08-31',
            amount: 50000, tax: 5000, totalAmount: 55000, paidAmount: 55000, balance: 0,
            status: '入金済', salesRep: '鈴木 花子', paymentTerms: '月末締め翌月末払い', issueStatus: '発行済', deliveryStatus: 'メール送付済'
        },
        {
            id: 'INV-00121', customerName: '株式会社D', title: '追加開発', billedDate: '2025-07-25', dueDate: '2025-08-31',
            amount: 310000, tax: 31000, totalAmount: 341000, paidAmount: 100000, balance: 241000,
            status: '一部入金', salesRep: '山田 太郎', paymentTerms: '月末締め翌月末払い', issueStatus: '発行済', deliveryStatus: '郵送済'
        },
        {
            id: 'INV-00120', customerName: '株式会社E', title: 'デザイン制作', billedDate: '2025-06-25', dueDate: '2025-07-31',
            amount: 180000, tax: 18000, totalAmount: 198000, paidAmount: 0, balance: 198000,
            status: '入金遅延', salesRep: '佐藤 次郎', paymentTerms: '月末締め翌月末払い', issueStatus: '再発行', deliveryStatus: 'メール送付済'
        },
    ];

    const getStatusBadge = (status) => {
        switch (status) {
            case '入金済': return <Badge className="bg-green-500 text-white">入金済</Badge>;
            case '一部入金': return <Badge className="bg-blue-500 text-white">一部入金</Badge>;
            case '請求済': return <Badge variant="secondary">請求済</Badge>;
            case '入金遅延': return <Badge variant="destructive">入金遅延</Badge>;
            default: return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">請求・売掛管理</h2>}>
            <Head title="Billing Management" />
            <div className="space-y-6">
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

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">合計未入金額</CardTitle><DollarSign className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">¥1,759,000</div></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">期日超過</CardTitle><AlertTriangle className="h-4 w-4 text-red-500" /></CardHeader><CardContent><div className="text-2xl font-bold">1件</div></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">今月請求額</CardTitle><CreditCard className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">¥1,661,000</div></CardContent></Card>
                    <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">担当別売上TOP</CardTitle><BarChart className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-sm">1. 山田 太郎</div></CardContent></Card>
                </div>

                {/* Main Table Card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div><CardTitle>請求書一覧</CardTitle><CardDescription>全 {invoices.length} 件</CardDescription></div>
                        <div className="flex gap-2"><Button variant="outline"><FileDown className="h-4 w-4 mr-2"/>エクスポート</Button><Button><PlusCircle className="h-4 w-4 mr-2"/>新規作成</Button></div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader><TableRow><TableHead>請求書番号</TableHead><TableHead>顧客名</TableHead><TableHead>請求日</TableHead><TableHead>支払期日</TableHead><TableHead>合計金額</TableHead><TableHead>残高</TableHead><TableHead>ステータス</TableHead></TableRow></TableHeader>
                            <TableBody>
                                        {invoices.map((invoice) => (
                                            <Sheet key={invoice.id}>
                                                <SheetTrigger asChild>
                                                    <TableRow className="cursor-pointer hover:bg-muted/50">
                                                        <TableCell className={`font-medium ${invoice.status === '入金遅延' ? 'border-l-4 border-red-500' : ''}`}>{invoice.id}</TableCell>
                                                        <TableCell>{invoice.customerName}</TableCell>
                                                        <TableCell>{invoice.billedDate}</TableCell>
                                                        <TableCell>{invoice.dueDate}</TableCell>
                                                        <TableCell>¥{invoice.totalAmount.toLocaleString()}</TableCell>
                                                        <TableCell className={`font-semibold ${invoice.balance > 0 ? 'text-red-600' : ''}`}>¥{invoice.balance.toLocaleString()}</TableCell>
                                                        <TableCell>{getStatusBadge(invoice.status)}</TableCell>
                                                    </TableRow>
                                                </SheetTrigger>
                                                <SheetContent className="sm:max-w-2xl">
                                                    <SheetHeader><SheetTitle>{invoice.id}: {invoice.title}</SheetTitle><SheetDescription>{invoice.customerName}</SheetDescription></SheetHeader>
                                                    <div className="py-4 space-y-4">{/* Details here */}<p>詳細情報をここに表示します。</p></div>
                                                    <SheetFooter><Button>入金登録</Button><Button variant="secondary">PDFプレビュー</Button></SheetFooter>
                                                </SheetContent>
                                            </Sheet>
                                        ))}
                                    </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}