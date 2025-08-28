
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetTrigger, SheetFooter } from "@/Components/ui/sheet";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/Components/ui/dropdown-menu";
import { DotsHorizontalIcon } from "@radix-ui/react-icons";
import { FileText, PlusCircle, Search } from 'lucide-react';

export default function QuoteIndex({ auth }) {
    const quotes = [
        {
            id: 'QT-001', title: 'Webサイトリニューアル', customer: '株式会社A', total: 1320000, cost: 950000, margin: 370000, marginRate: 28.0,
            status: '承認済', expiryDate: '2025-09-30', rep: '山田 太郎', updated: '2025-08-20'
        },
        {
            id: 'QT-002', title: '基幹システム保守', customer: '株式会社B', total: 660000, cost: 240000, margin: 420000, marginRate: 63.6,
            status: '受注化済', expiryDate: '2025-09-15', rep: '鈴木 花子', updated: '2025-08-22'
        },
        {
            id: 'QT-003', title: 'マーケティング支援', customer: '株式会社C', total: 550000, cost: 450000, margin: 100000, marginRate: 18.2,
            status: '承認待ち', expiryDate: '2025-10-10', rep: '山田 太郎', updated: '2025-08-23'
        },
        {
            id: 'QT-004', title: 'ハードウェア購入', customer: '株式会社D', total: 2200000, cost: 1980000, margin: 220000, marginRate: 10.0,
            status: '失注', expiryDate: '2025-08-31', rep: '佐藤 次郎', updated: '2025-08-15'
        },
        {
            id: 'QT-005', title: 'デザイン制作', customer: '株式会社E', total: 198000, cost: 150000, margin: 48000, marginRate: 24.2,
            status: 'ドラフト', expiryDate: '2025-09-25', rep: '山田 太郎', updated: '2025-08-24'
        },
    ];

    const getStatusBadge = (status) => {
        switch (status) {
            case '受注化済': return <Badge className="bg-green-500 text-white">受注化済</Badge>;
            case '承認済': return <Badge className="bg-blue-500 text-white">承認済</Badge>;
            case '承認待ち': return <Badge className="bg-yellow-500 text-white">承認待ち</Badge>;
            case 'ドラフト': return <Badge variant="secondary">ドラフト</Badge>;
            case '失注': return <Badge variant="outline">失注</Badge>;
            default: return <Badge>{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">見積管理</h2>}>
            <Head title="Quote Management" />
            <div className="space-y-6">
                <Accordion type="single" collapsible defaultValue="filters">
                    <AccordionItem value="filters">
                        <AccordionTrigger></AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="flex gap-4 items-start">
                                    <Input placeholder="見積番号, 件名, 顧客名..." className="max-w-xs" />
                                    <Button variant="outline">詳細フィルタ</Button>
                                    <Button className="flex items-center gap-2"><Search className="h-4 w-4"/>検索</Button>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div><CardTitle>見積一覧</CardTitle><CardDescription>全 {quotes.length} 件</CardDescription></div>
                        <div className="flex gap-2">
                            <Link href={route('estimates.create')}>
                                <Button><PlusCircle className="h-4 w-4 mr-2"/>新規見積</Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader><TableRow><TableHead>見積番号</TableHead><TableHead>件名</TableHead><TableHead>顧客名</TableHead><TableHead>税込合計</TableHead><TableHead>粗利率</TableHead><TableHead>ステータス</TableHead><TableHead>有効期限</TableHead><TableHead className="w-[50px]"></TableHead></TableRow></TableHeader>
                            <TableBody>
                                {quotes.map((quote) => (
                                    <Sheet key={quote.id}>
                                        <TableRow>
                                            <TableCell className="font-medium">{quote.id}</TableCell>
                                            <TableCell>{quote.title}</TableCell>
                                            <TableCell>{quote.customer}</TableCell>
                                            <TableCell>¥{quote.total.toLocaleString()}</TableCell>
                                            <TableCell className={`${quote.marginRate < 20 ? 'text-red-600' : ''}`}>{quote.marginRate.toFixed(1)}%</TableCell>
                                            <TableCell>{getStatusBadge(quote.status)}</TableCell>
                                            <TableCell>{quote.expiryDate}</TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild><Button variant="ghost" className="h-8 w-8 p-0"><DotsHorizontalIcon className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <SheetTrigger asChild><DropdownMenuItem>詳細を見る</DropdownMenuItem></SheetTrigger>
                                                        <DropdownMenuItem>編集</DropdownMenuItem>
                                                        <DropdownMenuItem>複製</DropdownMenuItem>
                                                        <DropdownMenuItem>PDFプレビュー</DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                        <SheetContent className="w-[640px] sm:max-w-none">
                                            <SheetHeader><SheetTitle>{quote.title}</SheetTitle><SheetDescription>{quote.id} / {quote.customer}</SheetDescription></SheetHeader>
                                            <Tabs defaultValue="overview" className="mt-4">
                                                <TabsList><TabsTrigger value="overview">概要</TabsTrigger><TabsTrigger value="items">明細</TabsTrigger><TabsTrigger value="profit">内訳</TabsTrigger><TabsTrigger value="approval">承認履歴</TabsTrigger></TabsList>
                                                <TabsContent value="overview" className="py-4">ここに基本情報を表示します。</TabsContent>
                                                <TabsContent value="items" className="py-4">ここに見積明細を表示します。</TabsContent>
                                                <TabsContent value="profit" className="py-4">ここに原価・粗利情報を表示します。</TabsContent>
                                                <TabsContent value="approval" className="py-4">ここに承認履歴を表示します。</TabsContent>
                                            </Tabs>
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
