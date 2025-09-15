
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
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { AlertCircle, Boxes, FileDown, Filter, PackagePlus, Search, SlidersHorizontal, Warehouse } from 'lucide-react';

export default function InventoryIndex({ auth }) {
    const mockInventory = [
    { code: 'PROD-001', name: '高性能ノートPC 14インチ', type: '物理', warehouse: 'メイン倉庫', location: 'A-01-01', onHand: 50, reserved: 10, available: 40, minQty: 10, lastActivity: '2025-08-20', status: 'OK' },
    { code: 'PROD-002', name: 'ビジネスソフトウェア ライセンス', type: 'ライセンス', warehouse: 'デジタル', location: 'N/A', onHand: 200, reserved: 50, available: 150, minQty: 20, lastActivity: '2025-08-22', status: 'OK' },
    { code: 'PROD-003', name: 'USB-C ハブ', type: '物理', warehouse: 'メイン倉庫', location: 'B-03-05', onHand: 5, reserved: 2, available: 3, minQty: 10, lastActivity: '2025-08-15', status: '不足' },
    { code: 'PROD-004', name: 'クラウドストレージ 1TBプラン', type: 'サービス', warehouse: 'デジタル', location: 'N/A', onHand: 999, reserved: 120, available: 879, minQty: 100, lastActivity: '2025-08-23', status: 'OK' },
    { code: 'PROD-005', name: 'ゲーミングマウス', type: '物理', warehouse: 'サテライト倉庫', location: 'S-01-02', onHand: 25, reserved: 5, available: 20, minQty: 5, lastActivity: '2025-08-19', status: '予約あり' },
];

    const getStatusBadge = (item) => {
        if (item.status === '不足') return <Badge variant="destructive" className="flex items-center gap-1"><AlertCircle className="h-3 w-3"/>不足</Badge>;
        if (item.status === '予約あり') return <Badge className="bg-blue-500 text-white">予約あり</Badge>;
        return <Badge variant="outline">OK</Badge>;
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">在庫管理</h2>}>
            <Head title="Inventory Management" />
            <div className="space-y-4">
                <Accordion type="single" collapsible defaultValue="filters">
                    <AccordionItem value="filters">
                        <AccordionTrigger>
                        </AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="flex gap-4 items-start">
                                    <Input placeholder="商品コード, 名称..." className="max-w-xs" />
                                    <ToggleGroup type="multiple" variant="outline">
                                        <ToggleGroupItem value="shortage">不足</ToggleGroupItem>
                                        <ToggleGroupItem value="reserved">予約あり</ToggleGroupItem>
                                        <ToggleGroupItem value="defective">不良</ToggleGroupItem>
                                    </ToggleGroup>
                                    <Button variant="outline" className="flex items-center gap-2"><SlidersHorizontal className="h-4 w-4"/>詳細フィルタ</Button>
                                    <Button className="flex items-center gap-2"><Search className="h-4 w-4"/>適用</Button>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div><CardTitle>在庫一覧</CardTitle><CardDescription>全 {inventory.length} 品目</CardDescription></div>
                        <div className="flex gap-2">
                            <Button variant="outline"><PackagePlus className="h-4 w-4 mr-2"/>新規受入</Button>
                            <Button variant="outline"><FileDown className="h-4 w-4 mr-2"/>CSV出力</Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>                                <TableHeader><TableRow><TableHead>商品コード</TableHead><TableHead>名称</TableHead><TableHead>種別</TableHead><TableHead>倉庫/ロケ</TableHead><TableHead>在庫</TableHead><TableHead>予約</TableHead><TableHead>利用可能</TableHead><TableHead>最終入出庫日</TableHead><TableHead>状態</TableHead></TableRow></TableHeader></TableHeader>
                            <TableBody>
                                {inventory.map((item) => (
                                    <Sheet key={item.id}>
                                        <SheetTrigger asChild>
                                            <TableRow className="cursor-pointer hover:bg-muted/50">
                                                <TableCell className={`font-medium ${item.status === '不足' ? 'border-l-4 border-red-500' : ''}`}>{item.id}</TableCell>
                                                <TableCell>{item.name}</TableCell>
                                                <TableCell>{item.type}</TableCell>
                                                <TableCell>{item.warehouse} / {item.location}</TableCell>
                                                <TableCell className={`${item.status === '不足' ? 'text-red-600' : ''}`}>{item.onHand}</TableCell>
                                                <TableCell>{item.reserved}</TableCell>
                                                <TableCell className="font-semibold">{item.available}</TableCell>
                                                <TableCell>{item.lastActivity}</TableCell>
                                                <TableCell>{getStatusBadge(item)}</TableCell>
                                            </TableRow>
                                        </SheetTrigger>
                                        <SheetContent className="w-[560px] sm:max-w-none">
                                            <SheetHeader><SheetTitle>{item.name}</SheetTitle><SheetDescription>{item.id}</SheetDescription></SheetHeader>
                                            <Tabs defaultValue="overview" className="mt-4">
                                                <TabsList><TabsTrigger value="overview">概要</TabsTrigger><TabsTrigger value="stock">在庫</TabsTrigger><TabsTrigger value="history">履歴</TabsTrigger><TabsTrigger value="details">シリアル/ライセンス</TabsTrigger></TabsList>
                                                <TabsContent value="overview" className="py-4">ここに概要（価格、タグ、メモ等）を表示します。</TabsContent>
                                                <TabsContent value="stock" className="py-4">ここに倉庫別在庫（テーブル）を表示します。</TabsContent>
                                                <TabsContent value="history" className="py-4">ここに履歴（トランザクション）を表示します。</TabsContent>
                                                <TabsContent value="details" className="py-4">ここにシリアル番号またはライセンスキー情報を表示します。</TabsContent>
                                            </Tabs>
                                            <SheetFooter><Button>在庫移動</Button><Button variant="secondary">ラベル印刷</Button></SheetFooter>
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
