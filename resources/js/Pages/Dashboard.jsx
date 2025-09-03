
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { DollarSign, ShoppingCart, Users, ListChecks, BarChart3 } from 'lucide-react';
import EstimateDetailSheet from '@/Components/EstimateDetailSheet';
import { useState, useMemo } from 'react';

export default function Dashboard({ auth, toDoEstimates = [] }) {
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

    // Filter state: 'all' | 'mine'
    const [filter, setFilter] = useState('all');
    const [openSheet, setOpenSheet] = useState(false);
    const [selectedEstimate, setSelectedEstimate] = useState(null);

    const filteredTasks = useMemo(() => {
        if (filter === 'mine') {
            return (toDoEstimates || []).filter(task => task.is_current_user_in_flow);
        }
        return toDoEstimates || [];
    }, [filter, toDoEstimates]);

    const openDetail = (task) => {
        if (task && task.estimate) {
            setSelectedEstimate(task.estimate);
            setOpenSheet(true);
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
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className='flex items-center'><ListChecks className="h-5 w-5 mr-2"/>やることリスト</CardTitle>
                                    <CardDescription>承認タスク（申請日降順）</CardDescription>
                                </div>
                                <ToggleGroup type="single" value={filter} onValueChange={(v) => v && setFilter(v)} className="gap-1">
                                    <ToggleGroupItem value="all" aria-label="全て">全て</ToggleGroupItem>
                                    <ToggleGroupItem value="mine" aria-label="自分のみ">自分のみ</ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[120px]">申請日</TableHead>
                                        <TableHead>件名</TableHead>
                                        <TableHead className="w-[160px]">状態/操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredTasks.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={3} className="text-slate-500">対象のタスクはありません。</TableCell>
                                        </TableRow>
                                    )}
                                    {filteredTasks.map((task) => (
                                        <TableRow key={task.id} className="hover:bg-slate-50">
                                            <TableCell className="font-medium">{task.issue_date}</TableCell>
                                            <TableCell className="max-w-[260px] truncate">{task.title}</TableCell>
                                            <TableCell>
                                                {task.status_for_dashboard === '確認して承認' ? (
                                                    <Button size="sm" onClick={() => openDetail(task)}>確認して承認</Button>
                                                ) : (
                                                    <Badge variant="secondary">{task.status_for_dashboard}</Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                {/* Detail Sheet (reuse existing modal) */}
                <EstimateDetailSheet
                    estimate={selectedEstimate}
                    isOpen={openSheet}
                    onClose={() => setOpenSheet(false)}
                />
            </div>
        </AuthenticatedLayout>
    );
}
