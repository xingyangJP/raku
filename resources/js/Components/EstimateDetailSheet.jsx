import React, { useState, useEffect } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from "@/Components/ui/sheet";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Textarea } from "@/Components/ui/textarea";
import {
    FileText,
    TrendingUp,
    Calendar,
    CheckCircle,
    Clock,
    Target,
    XCircle
} from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import { cn } from "@/lib/utils";
import { usePage, router } from '@inertiajs/react'; // Import usePage and router

export default function EstimateDetailSheet({ estimate, isOpen, onClose }) {
    const { auth } = usePage().props; // Get auth user from usePage
    const [isRejecting, setIsRejecting] = useState(false);
    const [rejectReason, setRejectReason] = useState('');

    if (!estimate) {
        return null;
    }

    useEffect(() => {
        if (!isOpen) {
            setIsRejecting(false);
            setRejectReason('');
        }
    }, [isOpen]);

    const getStatusBadge = (status) => {
        const configs = {
            'sent': {
                variant: 'default',
                className: 'bg-blue-500 hover:bg-blue-600 text-white',
                icon: CheckCircle,
                label: '承認済'
            },
            'draft': {
                variant: 'secondary',
                className: 'bg-slate-100 hover:bg-slate-200 text-slate-700',
                icon: FileText, // Changed from Edit to FileText for consistency
                label: 'ドラフト'
            },
            'pending': {
                variant: 'default',
                className: 'bg-amber-500 hover:bg-amber-600 text-white',
                icon: Clock,
                label: '承認待ち'
            },
            'rejected': {
                variant: 'destructive',
                className: 'bg-red-500 hover:bg-red-600 text-white',
                icon: CheckCircle, // Changed from AlertCircle to CheckCircle for consistency
                label: '却下'
            }
        };

        const config = configs[status] || configs['draft'];
        const Icon = config.icon;

        return (
            <Badge className={`flex items-center gap-1 ${config.className}`}>
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const calculateAmount = (item) => item.qty * item.price;
    const calculateCostAmount = (item) => item.qty * item.cost;
    const calculateGrossProfit = (item) => calculateAmount(item) - calculateCostAmount(item);

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#AF19FF', '#FF1919', '#19FFD4', '#FF19B8', '#8884d8', '#82ca9d', '#a4de6c', '#d0ed57', '#ffc658', '#ff7300', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];

    const subtotal = estimate.items ? estimate.items.reduce((acc, item) => acc + calculateAmount(item), 0) : 0;
    const totalCost = estimate.items ? estimate.items.reduce((acc, item) => acc + calculateCostAmount(item), 0) : 0;
    const totalGrossProfit = subtotal - totalCost;

    const grossProfitChartData = estimate.items ? Object.entries(estimate.items.reduce((acc, item) => {
        const itemName = item.name;
        if (!acc[itemName]) {
            acc[itemName] = { grossProfit: 0 };
        }
        acc[itemName].grossProfit += calculateGrossProfit(item);
        return acc;
    }, {})).map(([itemName, data]) => ({ name: itemName, value: data.grossProfit })) : [];

    const costChartData = estimate.items ? Object.entries(estimate.items.reduce((acc, item) => {
        const itemName = item.name;
        if (!acc[itemName]) {
            acc[itemName] = { cost: 0 };
        }
        acc[itemName].cost += calculateCostAmount(item);
        return acc;
    }, {})).map(([itemName, data]) => ({ name: itemName, value: data.cost })) : [];

    const handleApprove = () => {
        if (!confirm('この見積書を承認しますか？')) return;
        router.put(route('estimates.updateApproval', estimate.id), { action: 'approve' }, {
            onSuccess: () => {
                // Reload to reflect approved_at and task lists
                router.reload({ preserveScroll: true });
                onClose();
                setIsRejecting(false);
                setRejectReason('');
            },
            onError: (errors) => {
                console.error('Approval error:', errors);
                alert(errors?.approval || '承認中にエラーが発生しました。');
            }
        });
    };

    const handleRejectSubmit = () => {
        if (!rejectReason.trim()) {
            alert('却下理由を入力してください。');
            return;
        }
        if (!confirm('この見積書を却下しますか？')) return;
        router.put(route('estimates.updateApproval', estimate.id), { action: 'reject', reason: rejectReason }, {
            onSuccess: () => {
                router.reload({ preserveScroll: true });
                onClose();
                setIsRejecting(false);
                setRejectReason('');
            },
            onError: (errors) => {
                console.error('Reject error:', errors);
                alert(errors?.approval || '却下処理中にエラーが発生しました。');
            }
        });
    };

    return (
        <Sheet open={isOpen} onOpenChange={onClose}>
            <SheetContent data-testid="estimate-detail-sheet" className="w-[800px] sm:max-w-none overflow-y-auto">
                <SheetHeader className="space-y-4">
                    <div className="flex items-start justify-between">
                        <div>
                            <SheetTitle data-testid="sheet-title" className="text-2xl flex items-center gap-2">
                                <FileText className="h-6 w-6" />
                                {estimate.title}
                            </SheetTitle>
                            <SheetDescription data-testid="estimate-number" className="text-base mt-2">
                                {estimate.estimate_number} / {estimate.customer_name}
                            </SheetDescription>
                        </div>
                        <div data-testid="status-badge">
                            {getStatusBadge(estimate.status)}
                        </div>
                    </div>
                </SheetHeader>

                <Tabs defaultValue="overview" className="mt-6">
                    <TabsList data-testid="tabs-list" className="grid w-full grid-cols-3">
                        <TabsTrigger data-testid="tab-overview" value="overview" className="flex items-center gap-2">
                            <Target className="h-4 w-4" />
                            概要
                        </TabsTrigger>
                        <TabsTrigger data-testid="tab-details" value="items" className="flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            明細
                        </TabsTrigger>
                        <TabsTrigger data-testid="tab-approval-history" value="approval" className="flex items-center gap-2">
                            <CheckCircle className="h-4 w-4" />
                            承認履歴
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent data-testid="overview-content" value="overview" className="py-6 space-y-6">
                        {/* 基本情報カード */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    基本情報
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-slate-500">顧客名</p>
                                        <p data-testid="customer-name" className="font-semibold">{estimate.customer_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500">件名</p>
                                        <p data-testid="estimate-subject" className="font-semibold">{estimate.title}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500">見積番号</p>
                                        <p className="font-semibold text-blue-600">{estimate.estimate_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500">発行日</p>
                                        <p data-testid="created-date" className="font-semibold">{estimate.issue_date}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500">自社担当者</p>
                                        <p className="font-semibold">{estimate.staff_name || (estimate.staff ? estimate.staff.name : '-')}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500">合計金額 (税込)</p>
                                        <p data-testid="total-amount" className="font-bold text-xl text-green-600">
                                            ¥{estimate.total_amount ? estimate.total_amount.toLocaleString() : 'N/A'}
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-4 pt-4 border-t">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2">
                                        {estimate.delivery_location && (
                                            <div className="col-span-2 mb-2">
                                                <p className="text-sm text-slate-500">納入場所</p>
                                                <p className="font-semibold whitespace-pre-wrap">{estimate.delivery_location}</p>
                                            </div>
                                        )}
                                        {estimate.notes && (
                                            <div>
                                                <p className="text-sm text-slate-500">備考（対外）</p>
                                                <p className="mt-1 text-sm whitespace-pre-wrap">{estimate.notes}</p>
                                            </div>
                                        )}
                                        {estimate.internal_memo && (
                                            <div>
                                                <p className="text-sm text-slate-500">備考（社内メモ）</p>
                                                <p className="mt-1 text-sm whitespace-pre-wrap">{estimate.internal_memo}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* 原価・粗利分析カード */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <TrendingUp className="h-5 w-5" />
                                    原価・粗利分析
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col lg:flex-row justify-between items-center gap-6">
                                    <div data-testid="amount-chart" className="w-full lg:w-1/2 flex justify-center items-center relative h-48">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={grossProfitChartData}
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={60}
                                                    outerRadius={80}
                                                    paddingAngle={5}
                                                    dataKey="value"
                                                >
                                                    {grossProfitChartData.map((entry, index) => (
                                                        <Cell key={`cell-gross-profit-${index}`} fill={COLORS[index % COLORS.length]} />
                                                    ))}
                                                </Pie>
                                            </PieChart>
                                        </ResponsiveContainer>
                                        <div className="absolute inset-0 flex items-center justify-center text-lg font-bold">粗利</div>
                                    </div>
                                    <div className="w-full lg:w-1/2 flex justify-center items-center relative h-48">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={costChartData}
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={60}
                                                    outerRadius={80}
                                                    paddingAngle={5}
                                                    dataKey="value"
                                                >
                                                    {costChartData.map((entry, index) => (
                                                        <Cell key={`cell-cost-${index}`} fill={COLORS[index % COLORS.length]} />
                                                    ))}
                                                </Pie>
                                            </PieChart>
                                        </ResponsiveContainer>
                                        <div className="absolute inset-0 flex items-center justify-center text-lg font-bold">原価</div>
                                    </div>
                                </div>
                                <div className="mt-6 space-y-2">
                                    <p className="font-bold mb-4">品目別 粗利・原価分析</p>
                                    {Object.entries(estimate.items ? estimate.items.reduce((acc, item) => {
                                        const itemName = item.name;
                                        if (!acc[itemName]) {
                                            acc[itemName] = { grossProfit: 0, cost: 0 };
                                        }
                                        acc[itemName].grossProfit += calculateGrossProfit(item);
                                        acc[itemName].cost += calculateCostAmount(item);
                                        return acc;
                                    }, {}) : {}).map(([itemName, data]) => (
                                        <div key={itemName} className="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                                            <span className="font-medium">{itemName}</span>
                                            <div className="text-right">
                                                <div className="font-bold text-green-600">
                                                    粗利: ¥{data.grossProfit.toLocaleString()}
                                                </div>
                                                <div className="text-sm text-slate-500">
                                                    原価: ¥{data.cost.toLocaleString()}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    <div className="border-t pt-4 mt-4">
                                        <div className="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                                            <span className="font-bold text-lg">合計</span>
                                            <div className="text-right">
                                                <div className="font-bold text-xl text-green-600">
                                                    粗利: ¥{totalGrossProfit.toLocaleString()}
                                                </div>
                                                <div className="text-sm text-slate-600">
                                                    原価: ¥{totalCost.toLocaleString()}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent data-testid="details-content" value="items" className="py-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    見積明細
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Table data-testid="details-table">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>品目名</TableHead>
                                            <TableHead>詳細</TableHead>
                                            <TableHead className="text-right">数量</TableHead>
                                            <TableHead>単位</TableHead>
                                            <TableHead className="text-right">単価</TableHead>
                                            <TableHead className="text-right">金額</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {estimate.items && estimate.items.map((item, index) => (
                                            <TableRow key={index} data-testid={`detail-row-${index}`}>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell>{item.description}</TableCell>
                                                <TableCell className="text-right">{item.qty}</TableCell>
                                                <TableCell>{item.unit}</TableCell>
                                                <TableCell className="text-right">¥{item.price ? item.price.toLocaleString() : 'N/A'}</TableCell>
                                                <TableCell className="text-right font-bold">¥{(item.qty * item.price).toLocaleString()}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="mt-6 flex flex-col md:flex-row gap-4 md:gap-6 md:justify-between">
                                    {/* 承認フロー（合計の左） */}
                                    <Card className="w-full md:w-96">
                                        <CardHeader>
                                            <CardTitle className="text-sm">承認フロー</CardTitle>
                                        </CardHeader>
                                        <CardContent className="p-4">
                                            {Array.isArray(estimate.approval_flow) && estimate.approval_flow.length ? (
                                                <ol className="list-decimal list-inside space-y-1 text-sm text-slate-700">
                                                    {estimate.approval_flow.map((ap, i) => (
                                                        <li key={`${ap.id}-${i}`}>{ap.name}</li>
                                                    ))}
                                                </ol>
                                            ) : (
                                                <p className="text-sm text-slate-400">未設定</p>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* 合計 */}
                                    <Card className="w-full md:w-96 md:ml-auto">
                                        <CardContent className="p-4 space-y-2">
                                            <div className="flex justify-between">
                                                <span>小計（税抜）</span>
                                                <span>¥{subtotal.toLocaleString()}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>消費税</span>
                                                <span>¥{estimate.tax_amount ? estimate.tax_amount.toLocaleString() : 'N/A'}</span>
                                            </div>
                                            <div className="border-t pt-2">
                                                <div data-testid="total-row" className="flex justify-between font-bold text-lg">
                                                    <span>合計金額 (税込)</span>
                                                    <span className="text-green-600">¥{estimate.total_amount ? estimate.total_amount.toLocaleString() : 'N/A'}</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent data-testid="approval-history-content" value="approval" className="py-6">
                        {(() => {
                            const flow = Array.isArray(estimate.approval_flow) ? estimate.approval_flow : [];
                            const steps = flow.length ? flow : [];

                            // 現行ステップを approved_at に基づいて決定
                            let currentStepIndex = -1;
                            for (let i = 0; i < steps.length; i++) {
                                const status = steps[i].status ?? (steps[i].approved_at ? 'approved' : 'pending');
                                if (status !== 'approved' && status !== 'rejected') {
                                    currentStepIndex = i;
                                    break;
                                }
                            }

                            // 自分が現行承認者か（users.id または external_user_id で突合）
                            let isCurrentUserNextApprover = false;
                            if (currentStepIndex !== -1) {
                                const currId = steps[currentStepIndex].id;
                                const meId = auth?.user?.id;
                                const meExternalId = auth?.user?.external_user_id;
                                const currStr = currId == null ? '' : String(currId);
                                if ((meId != null && currId === meId) || (meExternalId && currStr && currStr === String(meExternalId))) {
                                    isCurrentUserNextApprover = true;
                                }
                            }

                            // 行ごとの表示を approved_at ベースで作成
                            const derived = steps.map((s, idx) => {
                                const statusValue = s.status ?? (s.approved_at ? 'approved' : 'pending');
                                const isApproved = statusValue === 'approved';
                                const isRejected = statusValue === 'rejected';
                                const isCurrent = !isApproved && !isRejected && idx === currentStepIndex;
                                const statusLabel = isRejected ? '却下' : (isApproved ? '承認済' : (isCurrent ? '未承認' : '待機中'));
                                const decidedAt = isRejected
                                    ? (s.rejected_at ? new Date(s.rejected_at).toLocaleDateString('ja-JP') : '')
                                    : (isApproved && s.approved_at ? new Date(s.approved_at).toLocaleDateString('ja-JP') : '');
                                return {
                                    name: s.name,
                                    avatar: s.name?.[0] || '承',
                                    role: idx === 0 ? '第1承認者' : `第${idx+1}承認者`,
                                    status: statusLabel,
                                    date: decidedAt,
                                    originalApprover: s,
                                    isRejected,
                                    isCurrent,
                                };
                            });

                            return (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <CheckCircle className="h-5 w-5" />
                                            承認フロー
                                        </CardTitle>
                                        <CardDescription>
                                            この見積書の承認プロセスと履歴
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="relative">
                                            {/* Timeline line */}
                                            <div className="absolute left-8 top-0 bottom-0 w-0.5 bg-slate-200"></div>

                                            <div className="space-y-8">
                                                {(derived.length ? derived : [{name:'承認者未設定', avatar:'ー', role:'', status:'未承認', date:''}]).map((step, index) => (
                                                    <div key={index} data-testid={`approval-history-item`} className="flex items-start gap-4 relative">
                                                        {/* Avatar circle */}
                                                        <div className="flex-shrink-0 w-16 h-16 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg z-10 shadow-lg">
                                                            {step.avatar}
                                                        </div>

                                                        {/* Content card */}
                                                        <div className="flex-1 min-w-0">
                                                            <Card className="shadow-sm">
                                                                <CardContent className="p-4">
                                                                    <div className="flex items-center justify-between mb-2">
                                                                        <div>
                                                                            <h4 className="font-semibold text-lg">{step.name}</h4>
                                                                            <p className="text-sm text-slate-500">{step.role}</p>
                                                                            {step.rejectionReason && (
                                                                                <p className="text-xs text-red-600 mt-1">理由: {step.rejectionReason}</p>
                                                                            )}
                                                                        </div>
                                                                        <div className="text-right flex flex-col items-end gap-1">
                                                                            <Badge
                                                                                className={cn(
                                                                                    "flex items-center gap-1",
                                                                                    step.status === '承認済'
                                                                                        ? 'bg-green-100 text-green-800'
                                                                                        : step.status === '却下'
                                                                                            ? 'bg-red-100 text-red-800'
                                                                                            : 'bg-amber-100 text-amber-800'
                                                                                )}
                                                                            >
                                                                                {step.status === '承認済' && <CheckCircle className="h-3 w-3" />}
                                                                                {step.status === '却下' && <XCircle className="h-3 w-3" />}
                                                                                {step.status !== '承認済' && step.status !== '却下' && <Clock className="h-3 w-3" />}
                                                                                {step.status}
                                                                            </Badge>
                                                                            <div className="flex items-center gap-2 mt-1">
                                                                                {step.isCurrent && isCurrentUserNextApprover && (
                                                                                    isRejecting ? (
                                                                                        <div className="flex flex-col gap-2 w-56">
                                                                                            <Textarea
                                                                                                value={rejectReason}
                                                                                                onChange={(e) => setRejectReason(e.target.value)}
                                                                                                placeholder="却下理由を入力"
                                                                                                className="text-sm"
                                                                                                rows={3}
                                                                                            />
                                                                                            <div className="flex items-center gap-2">
                                                                                                <Button data-testid="reject-submit-button" variant="destructive" size="sm" onClick={handleRejectSubmit}>
                                                                                                    理由を送信
                                                                                                </Button>
                                                                                                <Button variant="outline" size="sm" onClick={() => { setIsRejecting(false); setRejectReason(''); }}>
                                                                                                    キャンセル
                                                                                                </Button>
                                                                                            </div>
                                                                                        </div>
                                                                                    ) : (
                                                                                        <div className="flex items-center gap-2">
                                                                                            <Button data-testid="approve-button" onClick={handleApprove} size="sm" className="bg-black hover:bg-black/90 text-white">
                                                                                                承認する
                                                                                            </Button>
                                                                                            <Button data-testid="reject-button" variant="destructive" size="sm" onClick={() => { setIsRejecting(true); setRejectReason(''); }}>
                                                                                                却下する
                                                                                            </Button>
                                                                                        </div>
                                                                                    )
                                                                                )}
                                                                            </div>
                                                                            {step.date && (
                                                                                <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                                                                    <Calendar className="h-3 w-3" />
                                                                                    {step.date}
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                    </div>

                                                                    <div className="text-sm text-slate-600">
                                                                        {step.status === '承認済'
                                                                            ? '承認が完了しました。'
                                                                            : step.status === '却下'
                                                                                ? 'このステップで却下されました。'
                                                                                : (step.isCurrent ? '承認待ちです。' : '前段の承認待ちです。')}
                                                                    </div>
                                                                    {/* ボタンは右肩に移動済み */}
                                                                </CardContent>
                                                            </Card>
                                                        </div>
                                                    </div>
                                                ))}
                                                {derived.length === 0 && (
                                                    <div data-testid="empty-approval-history" className="text-center py-8 text-slate-500">
                                                        承認履歴はありません
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })()}
                    </TabsContent>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}
