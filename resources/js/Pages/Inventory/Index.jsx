import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { AlertCircle, Boxes, FileDown, PackagePlus, Search, Warehouse } from 'lucide-react';

const mockInventory = [
    { id: 'PROD-001', name: '高性能ノートPC 14インチ', type: '物理', warehouse: 'メイン倉庫', location: 'A-01-01', onHand: 50, reserved: 10, available: 40, minQty: 10, lastActivity: '2025-08-20', status: 'OK' },
    { id: 'PROD-002', name: 'ビジネスソフトウェア ライセンス', type: 'ライセンス', warehouse: 'デジタル', location: 'N/A', onHand: 200, reserved: 50, available: 150, minQty: 20, lastActivity: '2025-08-22', status: 'OK' },
    { id: 'PROD-003', name: 'USB-C ハブ', type: '物理', warehouse: 'メイン倉庫', location: 'B-03-05', onHand: 5, reserved: 2, available: 3, minQty: 10, lastActivity: '2025-08-15', status: '不足' },
    { id: 'PROD-004', name: 'クラウドストレージ 1TBプラン', type: 'サービス', warehouse: 'デジタル', location: 'N/A', onHand: 999, reserved: 120, available: 879, minQty: 100, lastActivity: '2025-08-23', status: 'OK' },
    { id: 'PROD-005', name: 'ゲーミングマウス', type: '物理', warehouse: 'サテライト倉庫', location: 'S-01-02', onHand: 25, reserved: 5, available: 20, minQty: 5, lastActivity: '2025-08-19', status: '予約あり' },
];

function getStatusBadge(item) {
    if (item.status === '不足') {
        return (
            <Badge variant="destructive" className="flex items-center gap-1">
                <AlertCircle className="h-3 w-3" />
                不足
            </Badge>
        );
    }

    if (item.status === '予約あり') {
        return <Badge className="bg-blue-500 text-white">予約あり</Badge>;
    }

    return <Badge variant="outline">OK</Badge>;
}

export default function InventoryIndex({ auth }) {
    const shortageCount = mockInventory.filter((item) => item.status === '不足').length;
    const reservedCount = mockInventory.filter((item) => item.status === '予約あり').length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">在庫管理</h2>}
        >
            <Head title="Inventory Management" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900 shadow-sm">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-none" />
                        <div className="space-y-1">
                            <p className="text-sm font-semibold">この画面は現在 mock 表示です</p>
                            <p className="text-sm leading-6">
                                実データ連携前の UI モックを表示しています。在庫数や履歴は業務判断に使わず、画面確認用として扱ってください。
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>表示中の品目</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <Boxes className="h-6 w-6" />
                                {mockInventory.length}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>不足アラート</CardDescription>
                            <CardTitle className="text-3xl text-red-600">{shortageCount}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>予約あり</CardDescription>
                            <CardTitle className="text-3xl text-blue-600">{reservedCount}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <CardTitle>在庫一覧</CardTitle>
                            <CardDescription>mock データで UI を確認できます。</CardDescription>
                        </div>
                        <div className="flex flex-col gap-2 md:flex-row">
                            <Input placeholder="商品コード, 名称..." className="w-full md:w-72" />
                            <Button variant="outline" className="justify-center">
                                <Search className="mr-2 h-4 w-4" />
                                検索
                            </Button>
                            <Button variant="outline" className="justify-center">
                                <PackagePlus className="mr-2 h-4 w-4" />
                                新規受入
                            </Button>
                            <Button variant="outline" className="justify-center">
                                <FileDown className="mr-2 h-4 w-4" />
                                CSV出力
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>商品コード</TableHead>
                                    <TableHead>名称</TableHead>
                                    <TableHead>種別</TableHead>
                                    <TableHead>倉庫/ロケ</TableHead>
                                    <TableHead className="text-right">在庫</TableHead>
                                    <TableHead className="text-right">予約</TableHead>
                                    <TableHead className="text-right">利用可能</TableHead>
                                    <TableHead>最終入出庫日</TableHead>
                                    <TableHead>状態</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {mockInventory.map((item) => (
                                    <Sheet key={item.id}>
                                        <SheetTrigger asChild>
                                            <TableRow className="cursor-pointer hover:bg-muted/50">
                                                <TableCell className={`font-medium ${item.status === '不足' ? 'border-l-4 border-red-500 pl-3' : ''}`}>{item.id}</TableCell>
                                                <TableCell>{item.name}</TableCell>
                                                <TableCell>{item.type}</TableCell>
                                                <TableCell>{item.warehouse} / {item.location}</TableCell>
                                                <TableCell className={`text-right ${item.status === '不足' ? 'text-red-600' : ''}`}>{item.onHand}</TableCell>
                                                <TableCell className="text-right">{item.reserved}</TableCell>
                                                <TableCell className="text-right font-semibold">{item.available}</TableCell>
                                                <TableCell>{item.lastActivity}</TableCell>
                                                <TableCell>{getStatusBadge(item)}</TableCell>
                                            </TableRow>
                                        </SheetTrigger>
                                        <SheetContent className="w-[560px] sm:max-w-none">
                                            <SheetHeader>
                                                <SheetTitle>{item.name}</SheetTitle>
                                                <SheetDescription>{item.id}</SheetDescription>
                                            </SheetHeader>
                                            <div className="mt-6 space-y-4 text-sm text-slate-700">
                                                <div className="rounded-xl border bg-slate-50 p-4">
                                                    <p className="font-semibold text-slate-900">現在も mock 表示です</p>
                                                    <p className="mt-2 leading-6">
                                                        明細・履歴・シリアル情報は仮データです。実在庫やライセンス情報とは一致しません。
                                                    </p>
                                                </div>
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    <div className="rounded-xl border p-4">
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">倉庫</p>
                                                        <p className="mt-2 flex items-center gap-2 text-base font-semibold text-slate-900">
                                                            <Warehouse className="h-4 w-4" />
                                                            {item.warehouse}
                                                        </p>
                                                        <p className="mt-1 text-sm text-slate-600">ロケーション: {item.location}</p>
                                                    </div>
                                                    <div className="rounded-xl border p-4">
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">在庫サマリ</p>
                                                        <p className="mt-2 text-base font-semibold text-slate-900">在庫 {item.onHand} / 予約 {item.reserved}</p>
                                                        <p className="mt-1 text-sm text-slate-600">利用可能 {item.available} / 最低在庫 {item.minQty}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <SheetFooter className="mt-6">
                                                <Button disabled>在庫移動</Button>
                                                <Button variant="secondary" disabled>ラベル印刷</Button>
                                            </SheetFooter>
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
