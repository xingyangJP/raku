
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { DollarSign, ShoppingCart, Users, ListChecks, BarChart3 } from 'lucide-react';

export default function Dashboard({ auth }) {
    // Mock data based on requirements
    const summaryData = {
        receivables: { title: "当月売掛サマリ", amount: "¥1,250,000", change: "+5.2%", icon: <DollarSign className="h-4 w-4 text-muted-foreground" /> },
        purchases: { title: "当月仕入サマリ", amount: "¥750,000", change: "+2.1%", icon: <ShoppingCart className="h-4 w-4 text-muted-foreground" /> },
        unpaid: { title: "未入金サマリ", amount: "¥85,000", change: "-1.5%", icon: <Users className="h-4 w-4 text-muted-foreground" /> },
    };

    const salesRanking = [
        { rank: 1, customer: "株式会社A", amount: "¥320,000" },
        { rank: 2, customer: "株式会社B", amount: "¥280,000" },
        { rank: 3, customer: "株式会社C", amount: "¥250,000" },
        { rank: 4, customer: "株式会社D", amount: "¥180,000" },
        { rank: 5, customer: "株式会社E", amount: "¥150,000" },
    ];

    const todoList = [
        { task: "株式会社Bへの請求書発行", priority: "High" },
        { task: "商品XYZの在庫確認", priority: "Medium" },
        { task: "XX月次レポート作成", priority: "Low" },
        { task: "株式会社Cとの打ち合わせ", priority: "High" },
    ];

    const getPriorityBadge = (priority) => {
        switch (priority) {
            case 'High':
                return <Badge variant="destructive">高</Badge>;
            case 'Medium':
                return <Badge variant="secondary">中</Badge>;
            case 'Low':
                return <Badge>低</Badge>;
            default:
                return <Badge>{priority}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Object.values(summaryData).map((item, index) => (
                        <Card key={index}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{item.title}</CardTitle>
                                {item.icon}
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{item.amount}</div>
                                <p className={`text-xs ${item.change.startsWith('-') ? 'text-red-500' : 'text-green-500'}`}>
                                    前月比 {item.change}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className='flex items-center'><BarChart3 className="h-5 w-5 mr-2"/>売上ランキング</CardTitle>
                            <CardDescription>今月の得意先別売上トップ5</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[50px]">順位</TableHead>
                                        <TableHead>得意先</TableHead>
                                        <TableHead className="text-right">金額</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {salesRanking.map((item) => (
                                        <TableRow key={item.rank}>
                                            <TableCell className="font-medium">{item.rank}</TableCell>
                                            <TableCell>{item.customer}</TableCell>
                                            <TableCell className="text-right">{item.amount}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className='flex items-center'><ListChecks className="h-5 w-5 mr-2"/>やることリスト</CardTitle>
                            <CardDescription>対応が必要なタスク一覧</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>タスク</TableHead>
                                        <TableHead className="w-[80px]">優先度</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {todoList.map((item, index) => (
                                        <TableRow key={index}>
                                            <TableCell className="font-medium">{item.task}</TableCell>
                                            <TableCell>{getPriorityBadge(item.priority)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
