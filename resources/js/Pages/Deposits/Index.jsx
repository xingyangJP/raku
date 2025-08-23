import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/Components/ui/dropdown-menu";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { DotsHorizontalIcon } from "@radix-ui/react-icons";
import { PlusCircle, Search, Trash2 } from 'lucide-react';

export default function DepositIndex({ auth }) {
    // Mock data for deposit list
    const deposits = [
        { id: 1, date: '2025-08-22', customer: '株式会社A', amount: 150000, fee: 550, method: '銀行振込', summary: '売掛金入金' },
        { id: 2, date: '2025-08-22', customer: '株式会社B', amount: 220000, fee: 770, method: '銀行振込', summary: '前受金' },
        { id: 3, date: '2025-08-21', customer: '株式会社C', amount: 85000, fee: 0, method: '現金', summary: '商品売上' },
        { id: 4, date: '2025-08-20', customer: '株式会社D', amount: 310000, fee: 770, method: '銀行振込', summary: '売掛金入金' },
        { id: 5, date: '2025-08-19', customer: '株式会社A', amount: 75000, fee: 550, method: '銀行振込', summary: '追加入金' },
    ];

    const totalAmount = deposits.reduce((acc, dep) => acc + dep.amount, 0);
    const totalFee = deposits.reduce((acc, dep) => acc + dep.fee, 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">入金管理</h2>}
        >
            <Head title="Deposit Management" />

            <div className="space-y-4">
                {/* Filters Accordion */}
                <Accordion type="single" collapsible defaultValue="filters" className="w-full">
                    <AccordionItem value="filters">
                        <AccordionTrigger>
                        </AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                    <Input placeholder="顧客名、入金番号..." />
                                    <Input type="date" placeholder="入金日(開始)" />
                                    <Input type="date" placeholder="入金日(終了)" />
                                    <Button className="flex items-center gap-2"><Search className="h-4 w-4"/>検索</Button>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>入金一覧</CardTitle>
                            <CardDescription>登録された入金データの一覧です。</CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" className="flex items-center gap-2"><Trash2 className="h-4 w-4"/>選択削除</Button>
                            <Button className="flex items-center gap-2"><PlusCircle className="h-4 w-4"/>新規入金登録</Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>入金日</TableHead>
                                    <TableHead>請求先</TableHead>
                                    <TableHead>入金方法</TableHead>
                                    <TableHead>摘要</TableHead>
                                    <TableHead className="text-right">手数料</TableHead>
                                    <TableHead className="text-right">入金額</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {deposits.map((deposit) => (
                                    <TableRow key={deposit.id}>
                                        <TableCell>{deposit.date}</TableCell>
                                        <TableCell className="font-medium">{deposit.customer}</TableCell>
                                        <TableCell>{deposit.method}</TableCell>
                                        <TableCell className="text-muted-foreground">{deposit.summary}</TableCell>
                                        <TableCell className="text-right">{deposit.fee.toLocaleString()}</TableCell>
                                        <TableCell className="text-right font-semibold">¥{deposit.amount.toLocaleString()}</TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0">
                                                        <DotsHorizontalIcon className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem>編集</DropdownMenuItem>
                                                    <DropdownMenuItem>削除</DropdownMenuItem>
                                                    <DropdownMenuItem>伝票印刷</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <div className="flex justify-end gap-8 mt-4 pr-4 border-t pt-4">
                            <div>
                                <span className="text-sm text-muted-foreground">合計手数料: </span>
                                <span className="font-bold">{totalFee.toLocaleString()}</span>
                            </div>
                            <div>
                                <span className="text-sm text-muted-foreground">合計入金額: </span>
                                <span className="font-bold text-lg">¥{totalAmount.toLocaleString()}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}