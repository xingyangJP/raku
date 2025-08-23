
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetTrigger, SheetFooter } from "@/Components/ui/sheet";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, DropdownMenuSeparator } from "@/Components/ui/dropdown-menu";
import { DotsHorizontalIcon } from "@radix-ui/react-icons";
import { FileDown, PlusCircle, Search } from 'lucide-react';

export default function ProductIndex({ auth }) {
    const products = [
        {
            id: 'PD-001', name: '高性能ノートPC 14インチ', nameKana: 'コウセイノウノートPC 14インチ', spec: 'Core i7/16GB/512GB SSD', jan: '4512345678901',
            categoryLarge: 'PC', categoryMedium: 'ノートPC', taxRate: '10%', taxClass: '課税', unit: '台', stock: 20, price: 150000, cost: 95000,
            flags: { nameChange: false, stockMgmt: true, arrivalMgmt: true, salesMgmt: true }, rep: '山田 太郎', updated: '2025-08-20'
        },
        {
            id: 'PD-002', name: 'ビジネスソフトウェア', nameKana: 'ビジネスソフトウェア', spec: '永続ライセンス', jan: '4512345678902',
            categoryLarge: 'ソフトウェア', categoryMedium: 'オフィス', taxRate: '10%', taxClass: '課税', unit: 'ライセンス', stock: 999, price: 45000, cost: 12000,
            flags: { nameChange: false, stockMgmt: false, arrivalMgmt: false, salesMgmt: true }, rep: '鈴木 花子', updated: '2025-08-22'
        },
        {
            id: 'PD-003', name: 'USB-C ハブ 7-in-1', nameKana: 'USB-C ハブ 7-IN-1', spec: 'HDMI/USB3.0*3/PD', jan: '4512345678903',
            categoryLarge: '周辺機器', categoryMedium: 'ハブ・ドック', taxRate: '10%', taxClass: '課税', unit: '個', stock: 150, price: 4500, cost: 2200,
            flags: { nameChange: true, stockMgmt: true, arrivalMgmt: true, salesMgmt: true }, rep: '山田 太郎', updated: '2025-08-15'
        },
    ];

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">商品管理</h2>}>
            <Head title="Product Management" />
            <div className="space-y-6">
                <Accordion type="single" collapsible defaultValue="filters">
                    <AccordionItem value="filters">
                        <AccordionTrigger></AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="flex gap-4 items-start">
                                    <Input placeholder="商品CD, 名称, カナ, JAN..." className="max-w-xs" />
                                    <Button variant="outline">詳細フィルタ</Button>
                                    <Button className="flex items-center gap-2"><Search className="h-4 w-4"/>検索</Button>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div><CardTitle>商品一覧</CardTitle><CardDescription>全 {products.length} 件</CardDescription></div>
                        <div className="flex gap-2">
                            <Button variant="outline"><FileDown className="h-4 w-4 mr-2"/>CSV</Button>
                            <Button><PlusCircle className="h-4 w-4 mr-2"/>新規登録</Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader><TableRow><TableHead>商品CD</TableHead><TableHead>商品名</TableHead><TableHead>規格名</TableHead><TableHead>分類</TableHead><TableHead>定価</TableHead><TableHead>更新日</TableHead><TableHead className="w-[50px]"></TableHead></TableRow></TableHeader>
                            <TableBody>
                                {products.map((product) => (
                                    <Sheet key={product.id}>
                                        <TableRow>
                                            <TableCell className="font-medium">{product.id}</TableCell>
                                            <TableCell>{product.name}</TableCell>
                                            <TableCell className="text-muted-foreground">{product.spec}</TableCell>
                                            <TableCell>{product.categoryLarge}/{product.categoryMedium}</TableCell>
                                            <TableCell>¥{product.price.toLocaleString()}</TableCell>
                                            <TableCell>{product.updated}</TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild><Button variant="ghost" className="h-8 w-8 p-0"><DotsHorizontalIcon className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <SheetTrigger asChild><DropdownMenuItem>詳細を見る</DropdownMenuItem></SheetTrigger>
                                                        <DropdownMenuItem>編集</DropdownMenuItem>
                                                        <DropdownMenuItem>複製</DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem>単価メンテへ</DropdownMenuItem>
                                                        <DropdownMenuItem>在庫へ</DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem className="text-red-600">削除</DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                        <SheetContent className="w-[640px] sm:max-w-none">
                                            <SheetHeader><SheetTitle>{product.name}</SheetTitle><SheetDescription>{product.id} / {product.spec}</SheetDescription></SheetHeader>
                                            <Tabs defaultValue="overview" className="mt-4">
                                                <TabsList><TabsTrigger value="overview">概要</TabsTrigger><TabsTrigger value="pricing">価格</TabsTrigger><TabsTrigger value="stock">在庫</TabsTrigger><TabsTrigger value="history">履歴</TabsTrigger></TabsList>
                                                <TabsContent value="overview" className="py-4">ここに基本情報と設定フラグを表示します。</TabsContent>
                                                <TabsContent value="pricing" className="py-4">ここに定価・原価情報を表示します。</TabsContent>
                                                <TabsContent value="stock" className="py-4">ここに倉庫別在庫へのリンク等を表示します。</TabsContent>
                                                <TabsContent value="history" className="py-4">ここに更新履歴を表示します。</TabsContent>
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
