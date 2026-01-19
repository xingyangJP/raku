import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Checkbox } from "@/Components/ui/checkbox";
import axios from 'axios';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetTrigger, SheetFooter } from "@/Components/ui/sheet";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/Components/ui/dropdown-menu";
import { DotsHorizontalIcon } from "@radix-ui/react-icons";
import {
    FileText,
    PlusCircle,
    Search,
    Filter,
    CheckCircle,
    TrendingUp,
    Calendar,
    DollarSign,
    Eye,
    Edit,
    Trash2,
    Copy,
    Clock,
    AlertCircle,
    XCircle,
    Target,
    ClipboardCheck,
    Percent,
    Layers,
    Activity,
    BarChart2,
    ShoppingCart
} from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import { cn } from "@/lib/utils";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import SyncButton from '@/Components/SyncButton';

const computeDefaultQuoteMonth = (value) => {
    if (value) return value;
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${now.getFullYear()}-${month}`;
};

const filterEstimatesList = (source, filters, options = {}) => {
    let temp = [...source];

    if (filters.title) {
        const lower = filters.title.toLowerCase();
        temp = temp.filter((e) => (e.title || '').toLowerCase().includes(lower));
    }

    if (filters.issue_month_from || filters.issue_month_to) {
        const fromMonth = filters.issue_month_from ? new Date(`${filters.issue_month_from}-01`) : null;
        const toMonth = filters.issue_month_to ? new Date(`${filters.issue_month_to}-01`) : null;

        temp = temp.filter((e) => {
            if (!e.issue_date) return false;
            const issueDate = new Date(e.issue_date);
            if (Number.isNaN(issueDate.getTime())) return false;

            const monthStart = new Date(issueDate.getFullYear(), issueDate.getMonth(), 1);
            const monthEnd = new Date(issueDate.getFullYear(), issueDate.getMonth() + 1, 0, 23, 59, 59, 999);

            const afterFrom = fromMonth ? monthEnd >= fromMonth : true;
            const beforeTo = toMonth
                ? monthStart <= new Date(toMonth.getFullYear(), toMonth.getMonth() + 1, 0, 23, 59, 59, 999)
                : true;

            return afterFrom && beforeTo;
        });
    }

    if (filters.partner) {
        const partnerLower = filters.partner.toLowerCase();
        temp = temp.filter((e) => (e.customer_name || '').toLowerCase().includes(partnerLower));
    }

    if (filters.status) {
        temp = temp.filter((e) => (e.status || '') === filters.status);
    }

    const includeId = options.includeEstimateId;
    if (typeof includeId === 'number' && Number.isFinite(includeId)) {
        const alreadyIncluded = temp.some((estimate) => estimate.id === includeId);
        if (!alreadyIncluded) {
            const target = source.find((estimate) => estimate.id === includeId);
            if (target) {
                temp = [target, ...temp];
            }
        }
    }

    return temp;
};

const parseEstimateId = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

export default function QuoteIndex({ auth, estimates, moneyForwardConfig, syncStatus, error, defaultRange, initialFilters, focusEstimateId, customerPortalBase }) {
    const { props } = usePage();
    const products = useMemo(() => Array.isArray(props.products) ? props.products : [], [props.products]);
    const productLookups = useMemo(() => {
        const skuMap = new Map();
        const nameMap = new Map();
        const idMap = new Map();
        products.forEach((product) => {
            const id = product?.id;
            if (id !== undefined && id !== null) {
                idMap.set(Number(id), product);
            }
            const sku = (product?.sku ?? '').trim().toLowerCase();
            if (sku) {
                skuMap.set(sku, product);
            }
            const name = (product?.name ?? '').trim().toLowerCase();
            if (name) {
                nameMap.set(name, product);
            }
        });
        return { skuMap, nameMap, idMap };
    }, [products]);
    const FIRST_BUSINESS_KEY = 'first_business';
    const normalizedCustomerPortalBase = useMemo(() => {
        if (!customerPortalBase) {
            return null;
        }
        return customerPortalBase.endsWith('/')
            ? customerPortalBase.replace(/\/+$/, '')
            : customerPortalBase;
    }, [customerPortalBase]);
    const [openApprovalStarted, setOpenApprovalStarted] = useState(false);
    const [approverNames, setApproverNames] = useState([]);
    const [isSyncing, setIsSyncing] = useState(false);

    const defaultFromMonth = computeDefaultQuoteMonth(initialFilters?.from ?? defaultRange?.from);
    const defaultToMonth = computeDefaultQuoteMonth(initialFilters?.to ?? defaultRange?.to);

    const initialFilterState = {
        title: initialFilters?.title ?? '',
        issue_month_from: defaultFromMonth,
        issue_month_to: defaultToMonth,
        partner: initialFilters?.partner ?? '',
        status: initialFilters?.status ?? '',
    };

    const initialFocusedEstimateId = parseEstimateId(focusEstimateId);

    const [filters, setFilters] = useState(initialFilterState);
    const [appliedFilters, setAppliedFilters] = useState(initialFilterState);
    const [activeDetailId, setActiveDetailId] = useState(initialFocusedEstimateId);
    const [filteredEstimates, setFilteredEstimates] = useState(() =>
        filterEstimatesList(estimates, initialFilterState, { includeEstimateId: initialFocusedEstimateId })
    );
    const [rejectForm, setRejectForm] = useState({ id: null, reason: '' });

    const moneyForwardAuthUrl = (() => {
        if (!moneyForwardConfig) return null;
        const params = new URLSearchParams({
            response_type: 'code',
            client_id: moneyForwardConfig.client_id ?? '',
            redirect_uri: moneyForwardConfig.redirect_uri ?? '',
            scope: moneyForwardConfig.scope ?? '',
            state: Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
        });
        return `${moneyForwardConfig.authorization_url}?${params.toString()}`;
    })();

    const authStartRoute = moneyForwardConfig?.auth_start_route ?? null;
    const canTriggerAuth = Boolean(moneyForwardAuthUrl || authStartRoute);

    const lastSyncedAt = syncStatus?.synced_at ? new Date(syncStatus.synced_at).toLocaleString('ja-JP') : null;

    const headerStatusText = (() => {
        if (!syncStatus) {
            return '同期情報未取得';
        }
        switch (syncStatus.status) {
            case 'synced':
                return lastSyncedAt ? `最終同期: ${lastSyncedAt}` : '同期完了';
            case 'skipped':
                return lastSyncedAt ? `前回同期: ${lastSyncedAt}` : '同期済み';
            case 'error':
                return '同期エラー';
            case 'unauthorized':
                return '認証が必要です';
            case 'locked':
                return '同期中';
            default:
                return `同期状態: ${syncStatus.status}`;
        }
    })();

    const triggerMoneyForwardAuth = () => {
        if (authStartRoute) {
            window.location.href = authStartRoute;
            return;
        }
        if (moneyForwardAuthUrl) {
            window.location.href = moneyForwardAuthUrl;
        }
    };

    const handleManualSync = () => {
        if (isSyncing) return;
        if ((syncStatus?.status ?? '') === 'unauthorized') {
            triggerMoneyForwardAuth();
            return;
        }
        setIsSyncing(true);
        router.post(route('quotes.sync'), {}, {
            preserveScroll: true,
            onFinish: () => setIsSyncing(false),
        });
    };

    useEffect(() => {
        const started = props.flash?.approval_started;
        if (started) {
            setOpenApprovalStarted(true);
            const names = Array.isArray(props.flash?.approval_flow) ? props.flash.approval_flow.map(a => a.name) : [];
            setApproverNames(names);
        }
    }, [props.flash]);

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
                icon: Edit,
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
                icon: AlertCircle,
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

    const handleDuplicate = (id) => {
        if (confirm('この見積書を複製しますか？')) {
            router.post(route('estimates.duplicate', id));
        }
    };

    const handleDelete = (id) => {
        if (confirm('この見積書を削除しますか？この操作は取り消せません。')) {
            router.delete(route('estimates.destroy', id));
        }
    };

    const handleDetailSheetChange = (estimateId, isOpen) => {
        setActiveDetailId((prev) => {
            if (isOpen) {
                return estimateId;
            }
            return prev === estimateId ? null : prev;
        });
    };

    // 一覧ではプレビューを廃止。MF見積PDFがある場合のみ「PDF表示」を提供します。

    const toNumber = (value, fallback = 0) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : fallback;
        }
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        }
        return fallback;
    };

    const getQuantity = (item) => toNumber(item?.qty ?? item?.quantity, 0);
    const getPrice = (item) => toNumber(item?.price, 0);
    const getCost = (item) => toNumber(item?.cost, 0);

    const calculateAmount = (item) => getQuantity(item) * getPrice(item);
    const calculateCostAmount = (item) => getQuantity(item) * getCost(item);
    const calculateGrossProfit = (item) => calculateAmount(item) - calculateCostAmount(item);
    const calculateGrossMargin = (item) => {
        const amount = calculateAmount(item);
        return amount !== 0 ? (calculateGrossProfit(item) / amount) * 100 : 0;
    };
    const formatCurrency = (value) => `¥${Number(value || 0).toLocaleString()}`;
    const formatGrossRate = (gross, amount) => {
        if (!amount) return '—';
        return `${((gross / amount) * 100).toFixed(1)}%`;
    };
    const formatIssueDate = (value) => {
        if (!value) return '—';
        if (typeof value === 'string') {
            const match = value.match(/^\d{4}-\d{2}-\d{2}/);
            if (match) {
                return match[0];
            }
        }
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }
        return parsed.toLocaleDateString('ja-JP');
    };

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#AF19FF', '#FF1919', '#19FFD4', '#FF19B8', '#8884d8', '#82ca9d', '#a4de6c', '#d0ed57', '#ffc658', '#ff7300', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];

    const resolveProductForItem = (item) => {
        if (!item) {
            return null;
        }
        const productId = item?.product_id ?? item?.productId ?? item?.product?.id;
        if (productId !== undefined && productId !== null) {
            const product = productLookups.idMap.get(Number(productId));
            if (product) {
                return product;
            }
        }
        const sku = String(item.code ?? item.product_code ?? item.sku ?? '')
            .trim()
            .toLowerCase();
        if (sku && productLookups.skuMap.has(sku)) {
            return productLookups.skuMap.get(sku);
        }
        const name = String(item.name ?? item.product_name ?? '')
            .trim()
            .toLowerCase();
        if (name && productLookups.nameMap.has(name)) {
            return productLookups.nameMap.get(name);
        }
        return null;
    };

    const calculateFirstDivisionCost = (items) => {
        if (!Array.isArray(items) || !items.length) {
            return 0;
        }
        return items.reduce((sum, item) => {
            const product = resolveProductForItem(item);
            if (!product || product.business_division !== FIRST_BUSINESS_KEY) {
                return sum;
            }
            const unitCost = toNumber(item?.cost ?? product?.cost, 0);
            return sum + getQuantity(item) * unitCost;
        }, 0);
    };

    const shouldExcludeEffortItem = (item) => {
        const product = resolveProductForItem(item);
        return product?.business_division === FIRST_BUSINESS_KEY;
    };

    // approval history is rendered from estimate.approval_flow in the detail view

    // 統計計算
    useEffect(() => {
        setFilteredEstimates(
            filterEstimatesList(estimates, appliedFilters, { includeEstimateId: activeDetailId })
        );
    }, [estimates, appliedFilters, activeDetailId]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        const url = new URL(window.location.href);
        if (activeDetailId) {
            url.searchParams.set('estimate_id', activeDetailId);
        } else {
            url.searchParams.delete('estimate_id');
        }
        window.history.replaceState({}, '', url);
    }, [activeDetailId]);

    const handleFilterChange = (event) => {
        const { name, value } = event.target;
        setFilters((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const applyFilters = () => {
        const nextFilters = { ...filters };
        setAppliedFilters(nextFilters);
        setFilteredEstimates(filterEstimatesList(estimates, nextFilters, { includeEstimateId: activeDetailId }));
    };

    const resetFilters = () => {
        const reset = {
            title: '',
            issue_month_from: defaultFromMonth,
            issue_month_to: defaultToMonth,
            partner: '',
            status: '',
        };
        setFilters(reset);
        setAppliedFilters(reset);
        setFilteredEstimates(filterEstimatesList(estimates, reset, { includeEstimateId: activeDetailId }));
    };

    const sumEstimateGross = (estimate) => {
        const items = Array.isArray(estimate?.items) ? estimate.items : [];
        return items.reduce((sum, item) => sum + calculateGrossProfit(item), 0);
    };
    const sumEstimateEffort = (estimate) => {
        const items = Array.isArray(estimate?.items) ? estimate.items : [];
        return items.reduce((sum, item) => {
            if (shouldExcludeEffortItem(item)) {
                return sum;
            }
            return sum + getQuantity(item);
        }, 0);
    };

    const maintenanceFee = Number(usePage().props.maintenance_fee_total || 0);
    const totalAmount = filteredEstimates.reduce((sum, est) => sum + (est.total_amount || 0), 0);
    const totalAmountWithMaintenance = totalAmount + maintenanceFee;
    const totalGross = filteredEstimates.reduce((sum, est) => sum + sumEstimateGross(est), 0);
    const totalGrossWithMaintenance = totalGross + maintenanceFee; // 保守=粗利と同額
    const totalCount = filteredEstimates.length;

    const confirmedEstimates = filteredEstimates.filter((est) => est.is_order_confirmed);
    const confirmedAmount = confirmedEstimates.reduce((sum, est) => sum + (est.total_amount || 0), 0);
    const confirmedAmountWithMaintenance = confirmedAmount + maintenanceFee; // 保守は常に実績に含める
    const confirmedGross = confirmedEstimates.reduce((sum, est) => sum + sumEstimateGross(est), 0);
    const confirmedGrossWithMaintenance = confirmedGross + maintenanceFee; // 保守は実績も同額
    const confirmedEffort = confirmedEstimates.reduce((sum, est) => sum + sumEstimateEffort(est), 0);
    const confirmedFirstDivisionCost = confirmedEstimates.reduce((sum, est) => sum + calculateFirstDivisionCost(est?.items), 0);
    const confirmedCount = confirmedEstimates.length;
    const executionRate = totalAmountWithMaintenance > 0
        ? Math.round((confirmedAmountWithMaintenance / totalAmountWithMaintenance) * 100)
        : 0;

    return (
        <AuthenticatedLayout 
            user={auth.user} 
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
                        <FileText className="h-6 w-6" />
                        見積管理
                    </h2>
                    <div className="text-sm text-gray-600">
                        {headerStatusText}
                    </div>
                </div>
            }
        >
            <Head title="Quote Management" />
            <Dialog open={openApprovalStarted} onOpenChange={setOpenApprovalStarted}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>承認申請を開始しました</DialogTitle>
                        <DialogDescription>以下の承認フローで申請を受け付けました。</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        {approverNames.length ? (
                            <ol className="list-decimal list-inside text-slate-700">
                                {approverNames.map((n, i) => (
                                    <li key={`${n}-${i}`}>{n}</li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-slate-500 text-sm">承認者が設定されていません。</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button onClick={() => setOpenApprovalStarted(false)}>OK</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            
            <div className="space-y-8">
                <div className="space-y-3">
                    {error && (
                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded">
                            {error}
                        </div>
                    )}
                    {props.flash?.success && (
                        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded">
                            {props.flash.success}
                        </div>
                    )}
                    {props.flash?.info && (
                        <div className="bg-sky-50 border border-sky-200 text-sky-700 px-4 py-2 rounded">
                            {props.flash.info}
                        </div>
                    )}
                    {syncStatus?.status === 'synced' && lastSyncedAt && (
                        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded">
                            最終同期: {lastSyncedAt}
                        </div>
                    )}
                    {syncStatus?.status === 'skipped' && lastSyncedAt && (
                        <div className="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded">
                            前回同期: {lastSyncedAt}
                        </div>
                    )}
                    {syncStatus?.status === 'error' && syncStatus.message && (
                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded">
                            同期エラー: {syncStatus.message}
                        </div>
                    )}
                    {syncStatus?.status === 'unauthorized' && (
                        <div className="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-2 rounded flex flex-wrap items-center gap-3">
                            <span>Money Forwardの認証が必要です。</span>
                            <Button
                                size="sm"
                                className="bg-yellow-500 hover:bg-yellow-600 text-white"
                                disabled={!canTriggerAuth}
                                onClick={triggerMoneyForwardAuth}
                            >
                                認証する
                            </Button>
                        </div>
                    )}
                </div>

                {/* 統計ダッシュボード */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-blue-50 to-blue-100 shadow-lg">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-blue-700">
                                予算（見積合計）
                            </CardTitle>
                            <div className="rounded-full bg-blue-500 p-2">
                                <DollarSign className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-900">
                                ¥{totalAmountWithMaintenance.toLocaleString()}
                            </div>
                            <div className="text-xs text-blue-700 mt-1 space-y-1">
                                <div className="flex items-center">
                                    <TrendingUp className="h-3 w-3 mr-1" />
                                    <span>スポット {formatCurrency(totalAmount)}</span>
                                </div>
                                <div className="flex items-center ml-4">
                                    <span className="mr-1">保守</span>
                                    <span>{formatCurrency(maintenanceFee)}</span>
                                </div>
                            </div>
                        </CardContent>
                        <div className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-blue-200 opacity-20" />
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-emerald-50 to-emerald-100 shadow-lg">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-green-700">
                                実績（注文確定）
                            </CardTitle>
                            <div className="rounded-full bg-green-500 p-2">
                                <CheckCircle className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-900">
                                ¥{confirmedAmountWithMaintenance.toLocaleString()}
                            </div>
                            <div className="text-xs text-green-700 mt-1 space-y-1">
                                <div className="flex items-center">
                                    <ClipboardCheck className="h-3 w-3 mr-1" />
                                    <span>注文確定済みの合計</span>
                                </div>
                                <div className="flex items-center ml-4">
                                    <span className="mr-1">スポット</span>
                                    <span>{formatCurrency(confirmedAmount)}</span>
                                </div>
                                <div className="flex items-center ml-4">
                                    <span className="mr-1">保守</span>
                                    <span>{formatCurrency(maintenanceFee)}</span>
                                </div>
                            </div>
                        </CardContent>
                        <div className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-green-200 opacity-20" />
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-indigo-50 to-indigo-100 shadow-lg">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-indigo-700">
                                実績率
                            </CardTitle>
                            <div className="rounded-full bg-indigo-500 p-2">
                                <Percent className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-900">
                                {executionRate}%
                            </div>
                            <p className="text-xs text-indigo-600">
                                予算に対する達成率
                            </p>
                        </CardContent>
                        <div className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-indigo-200 opacity-20" />
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-purple-50 to-purple-100 shadow-lg">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-purple-700">
                                注文確定件数
                            </CardTitle>
                            <div className="rounded-full bg-purple-500 p-2">
                                <Target className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                            <CardContent>
                            <div className="text-2xl font-bold text-purple-900">
                                {confirmedCount}件
                            </div>
                            <p className="text-xs text-purple-600">
                                全{totalCount}件中の注文確定
                            </p>
                        </CardContent>
                        <div className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-purple-200 opacity-20" />
                    </Card>
                </div>
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mt-6">
                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-blue-50 to-blue-100 shadow">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-blue-700">
                                予算（粗利合計）
                            </CardTitle>
                            <div className="rounded-full bg-blue-500 p-2">
                                <Layers className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-900">
                                ¥{totalGrossWithMaintenance.toLocaleString()}
                            </div>
                            <div className="text-xs text-blue-700 mt-1 space-y-1">
                                <div>スポット {formatCurrency(totalGross)}</div>
                                <div>保守 {formatCurrency(maintenanceFee)}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-green-50 to-green-100 shadow">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-green-700">
                                実績（粗利合計）
                            </CardTitle>
                            <div className="rounded-full bg-green-500 p-2">
                                <Activity className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-900">
                                ¥{confirmedGrossWithMaintenance.toLocaleString()}
                            </div>
                            <div className="text-xs text-green-700 mt-1 space-y-1">
                                <div>スポット {formatCurrency(confirmedGross)}</div>
                                <div>保守 {formatCurrency(maintenanceFee)}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-violet-50 to-violet-100 shadow">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-violet-700">
                                受注人日
                            </CardTitle>
                            <div className="rounded-full bg-violet-500 p-2">
                                <BarChart2 className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-violet-900">
                                {confirmedEffort.toFixed(1)} 人日
                            </div>
                            <p className="text-xs text-violet-600">
                                注文確定品目の数量合計
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden border-0 bg-gradient-to-br from-amber-50 to-amber-100 shadow">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-amber-800">
                                仕入れ合計（確定）
                            </CardTitle>
                            <div className="rounded-full bg-amber-500 p-2">
                                <ShoppingCart className="h-4 w-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-900">
                                ¥{confirmedFirstDivisionCost.toLocaleString()}
                            </div>
                            <p className="text-xs text-amber-700">
                                第1種事業の原価合計
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* 検索・フィルタ */}
                <Accordion type="single" collapsible defaultValue="filters">
                    <AccordionItem value="filters" className="border rounded-lg shadow-sm bg-white">
                        <AccordionTrigger className="px-6 py-4 hover:no-underline">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-slate-50 rounded-lg">
                                    <Filter className="h-5 w-5 text-slate-600" />
                                </div>
                                <div className="text-left">
                                    <CardTitle className="text-lg">検索・フィルタ</CardTitle>
                                    <CardDescription>見積書の検索と絞り込み</CardDescription>
                                </div>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent>
                            <CardContent className="pt-4">
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label htmlFor="title" className="block text-sm font-medium text-gray-700">タイトル</label>
                                            <Input
                                                id="title"
                                                name="title"
                                                value={filters.title}
                                                onChange={handleFilterChange}
                                                className="mt-1 border-slate-300 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="issue_month_from" className="block text-sm font-medium text-gray-700">見積月 From</label>
                                            <input
                                                type="month"
                                                id="issue_month_from"
                                                name="issue_month_from"
                                                value={filters.issue_month_from}
                                                onChange={handleFilterChange}
                                                className="mt-1 block w-full rounded-md border border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="issue_month_to" className="block text-sm font-medium text-gray-700">見積月 To</label>
                                            <input
                                                type="month"
                                                id="issue_month_to"
                                                name="issue_month_to"
                                                value={filters.issue_month_to}
                                                onChange={handleFilterChange}
                                                className="mt-1 block w-full rounded-md border border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="partner" className="block text-sm font-medium text-gray-700">取引先（顧客名）</label>
                                            <Input
                                                id="partner"
                                                name="partner"
                                                value={filters.partner}
                                                onChange={handleFilterChange}
                                                className="mt-1 border-slate-300 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="status" className="block text-sm font-medium text-gray-700">ステータス</label>
                                            <select
                                                id="status"
                                                name="status"
                                                value={filters.status}
                                                onChange={handleFilterChange}
                                                className="mt-1 block w-full rounded-md border border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="">全て</option>
                                                <option value="sent">承認済</option>
                                                <option value="pending">承認待ち</option>
                                                <option value="draft">ドラフト</option>
                                                <option value="rejected">却下</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div className="flex justify-end gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={resetFilters}
                                            className="border-slate-300 text-slate-700"
                                        >
                                            リセット
                                        </Button>
                                        <Button
                                            type="button"
                                            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700"
                                            onClick={applyFilters}
                                        >
                                            <Search className="h-4 w-4" />
                                            検索
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

                {/* メインテーブル */}
                <Card className="shadow-xl border-0">
                    <CardHeader className="bg-gradient-to-r from-slate-50 to-slate-100 rounded-t-lg">
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-xl flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    見積一覧
                                </CardTitle>
                                <CardDescription className="mt-1">
                                    全 {filteredEstimates.length} 件
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <SyncButton
                                    onClick={handleManualSync}
                                    disabled={isSyncing}
                                    className={cn(isSyncing && 'opacity-70 cursor-not-allowed')}
                                >
                                    {isSyncing ? '同期中…' : 'MF同期'}
                                </SyncButton>
                                <Link href={route('estimates.create')}>
                                    <Button className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700">
                                        <PlusCircle className="h-4 w-4"/>
                                        新規見積
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-slate-50 hover:bg-slate-50">
                                        <TableHead className="w-16 text-center font-semibold">受注</TableHead>
                                        <TableHead className="font-semibold">見積番号</TableHead>
                                        <TableHead className="font-semibold">件名</TableHead>
                                        <TableHead className="font-semibold">顧客名</TableHead>
                                        <TableHead className="font-semibold">税込合計</TableHead>
                                        <TableHead className="font-semibold">ステータス</TableHead>
                                        <TableHead className="font-semibold">自社担当者</TableHead>
                                        <TableHead className="w-[50px] font-semibold text-center">操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredEstimates.map((estimate) => {
                                        const subtotal = estimate.items ? estimate.items.reduce((acc, item) => acc + calculateAmount(item), 0) : 0;
                                        const totalCost = estimate.items ? estimate.items.reduce((acc, item) => acc + calculateCostAmount(item), 0) : 0;
                                        const totalGrossProfit = subtotal - totalCost;
                                        const totalGrossMargin = subtotal !== 0 ? (totalGrossProfit / subtotal) * 100 : 0;
                                        const customerPortalUrl = normalizedCustomerPortalBase && estimate.pm_customer_id
                                            ? `${normalizedCustomerPortalBase}/${estimate.pm_customer_id}`
                                            : null;
                                        const itemSummaryMap = estimate.items ? estimate.items.reduce((acc, item) => {
                                            const itemName = item.name || '項目';
                                            if (!acc[itemName]) {
                                                acc[itemName] = { grossProfit: 0, cost: 0, amount: 0 };
                                            }
                                            const amount = calculateAmount(item);
                                            const cost = calculateCostAmount(item);
                                            acc[itemName].grossProfit += amount - cost;
                                            acc[itemName].cost += cost;
                                            acc[itemName].amount += amount;
                                            return acc;
                                        }, {}) : {};
                                        const summaryList = Object.entries(itemSummaryMap).map(([name, data]) => ({ name, ...data }));

                                        const grossProfitChartData = summaryList.map(({ name, grossProfit }) => ({ name, value: grossProfit }));
                                        const costChartData = summaryList.map(({ name, cost }) => ({ name, value: cost }));

                                        return (
                                            <Sheet
                                                key={estimate.id}
                                                open={activeDetailId === estimate.id}
                                                onOpenChange={(isOpen) => handleDetailSheetChange(estimate.id, isOpen)}
                                            >
                                                <TableRow className="hover:bg-slate-50 transition-colors group">
                                                    <TableCell className="text-center w-16">
                                                        {estimate.is_order_confirmed ? (
                                                            <CheckCircle className="h-4 w-4 text-green-600 inline-block" />
                                                        ) : (
                                                            <span className="text-slate-300 text-xs">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-medium text-blue-700">
                                                        <Link
                                                            href={route('estimates.edit', estimate.id)}
                                                            className="hover:underline"
                                                        >
                                                            {estimate.estimate_number}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell className="max-w-[200px] truncate">
                                                        {estimate.mf_quote_id ? (
                                                            <a
                                                                href={route('estimates.viewQuote.start', { estimate: estimate.id })}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:underline"
                                                            >
                                                                {estimate.title || '（件名未設定）'}
                                                            </a>
                                                        ) : (
                                                            estimate.title || '（件名未設定）'
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-medium">
                                                        {customerPortalUrl ? (
                                                            <a
                                                                href={customerPortalUrl}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:underline"
                                                            >
                                                                {estimate.customer_name || '（顧客未設定）'}
                                                            </a>
                                                        ) : (
                                                            estimate.customer_name || '（顧客未設定）'
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-bold text-green-700">
                                                        ¥{estimate.total_amount ? estimate.total_amount.toLocaleString() : 'N/A'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {getStatusBadge(estimate.status)}
                                                    </TableCell>
                                                    <TableCell className="text-slate-600">{estimate.staff_name || (estimate.staff ? estimate.staff.name : '-')}</TableCell>
                                                    <TableCell>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" className="h-8 w-8 p-0 hover:bg-slate-100">
                                                                    <DotsHorizontalIcon className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-48">
                                                                <SheetTrigger asChild>
                                                                    <DropdownMenuItem className="flex items-center gap-2">
                                                                        <Eye className="h-4 w-4" />
                                                                        詳細を見る
                                                                    </DropdownMenuItem>
                                                                </SheetTrigger>
                                                                <Link href={route('estimates.edit', estimate.id)}>
                                                                    <DropdownMenuItem className="flex items-center gap-2">
                                                                        <Edit className="h-4 w-4" />
                                                                        編集
                                                                    </DropdownMenuItem>
                                                                </Link>
                                                                <DropdownMenuItem 
                                                                    onClick={() => handleDuplicate(estimate.id)}
                                                                    className="flex items-center gap-2"
                                                                >
                                                                    <Copy className="h-4 w-4" />
                                                                    複製
                                                                </DropdownMenuItem>
                                                                {estimate.mf_quote_id && (
                                                                    <DropdownMenuItem 
                                                                        onClick={() => window.location.href = route('estimates.viewQuote.start', { estimate: estimate.id })}
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <FileText className="h-4 w-4" />
                                                                        PDFダウンロード
                                                                    </DropdownMenuItem>
                                                                )}
                                                                <DropdownMenuItem 
                                                                    onClick={() => handleDelete(estimate.id)}
                                                                    className="flex items-center gap-2 text-red-600 focus:text-red-600"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    削除
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </TableCell>
                                                </TableRow>
                                                
                                                {/* 詳細シート */}
                                                <SheetContent className="w-[800px] sm:max-w-none overflow-y-auto">
                                                    <SheetHeader className="space-y-4">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <SheetTitle className="text-2xl flex items-center gap-2">
                                                                    <FileText className="h-6 w-6" />
                                                                    {estimate.title}
                                                                </SheetTitle>
                                                                <SheetDescription className="text-base mt-2">
                                                                    {estimate.estimate_number} / {estimate.customer_name}
                                                                </SheetDescription>
                                                            </div>
                                                            {getStatusBadge(estimate.status)}
                                                        </div>
                                                    </SheetHeader>
                                                    
                                                    <Tabs defaultValue="overview" className="mt-6">
                                                        <TabsList className="grid w-full grid-cols-3">
                                                            <TabsTrigger value="overview" className="flex items-center gap-2">
                                                                <Target className="h-4 w-4" />
                                                                概要
                                                            </TabsTrigger>
                                                            <TabsTrigger value="items" className="flex items-center gap-2">
                                                                <FileText className="h-4 w-4" />
                                                                明細
                                                            </TabsTrigger>
                                                            <TabsTrigger value="approval" className="flex items-center gap-2">
                                                                <CheckCircle className="h-4 w-4" />
                                                                承認履歴
                                                            </TabsTrigger>
                                                        </TabsList>
                                                        
                                                        <TabsContent value="overview" className="py-6 space-y-6">
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
                                                                            <p className="font-semibold">{estimate.customer_name}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">件名</p>
                                                                            <p className="font-semibold">{estimate.title}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">見積番号</p>
                                                                            <p className="font-semibold text-blue-600">{estimate.estimate_number}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">発行日</p>
                                                                            <p className="font-semibold">{formatIssueDate(estimate.issue_date)}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">自社担当者</p>
                                                                            <p className="font-semibold">{estimate.staff_name || (estimate.staff ? estimate.staff.name : '-')}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">合計金額 (税込)</p>
                                                                            <p className="font-bold text-xl text-green-600">
                                                                                ¥{estimate.total_amount ? estimate.total_amount.toLocaleString() : 'N/A'}
                                                                            </p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">粗利率</p>
                                                                            <p className={`font-semibold text-lg ${Number.isFinite(totalGrossMargin) && totalGrossMargin >= 0 ? 'text-emerald-600' : 'text-slate-500'}`}>
                                                                                {Number.isFinite(totalGrossMargin) ? `${totalGrossMargin.toFixed(1)}%` : '―'}
                                                                            </p>
                                                                            <p className="text-xs text-slate-500 mt-1">
                                                                                粗利 ¥{Number.isFinite(totalGrossProfit) ? totalGrossProfit.toLocaleString() : '―'} / 原価 ¥{Number.isFinite(totalCost) ? totalCost.toLocaleString() : '―'}
                                                                            </p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-sm text-slate-500">承認フロー</p>
                                                                            {Array.isArray(estimate.approval_flow) && estimate.approval_flow.length ? (
                                                                                <ol className="text-sm text-slate-700 list-decimal list-inside space-y-1">
                                                                                    {estimate.approval_flow.map((ap, i) => (
                                                                                        <li key={`${ap.id}-${i}`}>{ap.name}</li>
                                                                                    ))}
                                                                                </ol>
                                                                            ) : (
                                                                                <p className="text-sm text-slate-400">未設定</p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    {estimate.notes && (
                                                                        <div className="mt-4 pt-4 border-t">
                                                                            <p className="text-sm text-slate-500">備考</p>
                                                                            <p className="mt-1">{estimate.notes}</p>
                                                                        </div>
                                                                    )}
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
                                                                        <div className="w-full lg:w-1/2 flex justify-center items-center relative h-48">
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
                                    {summaryList.map((item) => (
                                        <div key={item.name} className="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                                            <span className="font-medium">{item.name}</span>
                                            <div className="text-right">
                                                <div className="font-bold text-green-600">
                                                    粗利: {formatCurrency(item.grossProfit)}（粗利率: {formatGrossRate(item.grossProfit, item.amount)}）
                                                </div>
                                                <div className="text-sm text-slate-500">
                                                    原価: {formatCurrency(item.cost)}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    <div className="border-t pt-4 mt-4">
                                        <div className="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                                            <span className="font-bold text-lg">合計</span>
                                            <div className="text-right">
                                                <div className="font-bold text-xl text-green-600">
                                                    粗利: {formatCurrency(totalGrossProfit)}（粗利率: {formatGrossRate(totalGrossProfit, subtotal)}）
                                                </div>
                                                <div className="text-sm text-slate-600">
                                                    原価: {formatCurrency(totalCost)}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                                                    </div>
                                                                </CardContent>
                                                            </Card>
                                                        </TabsContent>
                                                        
                                                        <TabsContent value="items" className="py-6">
                                                            <Card>
                                                                <CardHeader>
                                                                    <CardTitle className="flex items-center gap-2">
                                                                        <FileText className="h-5 w-5" />
                                                                        見積明細
                                                                    </CardTitle>
                                                                </CardHeader>
                                                                <CardContent>
                                                                    <Table>
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
                                                                                <TableRow key={index}>
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
                                                                                    <div className="flex justify-between font-bold text-lg">
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
                                                        
                                                        <TabsContent value="approval" className="py-6">
                                                            {(() => {
                                                                const flow = Array.isArray(estimate.approval_flow) ? estimate.approval_flow : [];
                                                                const steps = flow.length ? flow : [];
                                                                const requiresRequirementDoc = Array.isArray(estimate.items)
                                                                    ? estimate.items.some((item) => {
                                                                        const rawCode = `${item?.code ?? item?.product_code ?? ''}`.trim().toUpperCase();
                                                                        if (!rawCode) {
                                                                            return false;
                                                                        }
                                                                        const prefix = rawCode.split('-')[0];
                                                                        return prefix === 'B' || prefix === 'C';
                                                                    })
                                                                    : false;
                                                                // 現行ステップ index（approved_at 基準）
                                                                let currentStepIndex = -1;
                                                                for (let i = 0; i < steps.length; i++) {
                                                                    const status = steps[i].status ?? (steps[i].approved_at ? 'approved' : 'pending');
                                                                    if (status !== 'approved' && status !== 'rejected') {
                                                                        currentStepIndex = i;
                                                                        break;
                                                                    }
                                                                }
                                                                const meId = props.auth?.user?.id;
                                                                const meExt = props.auth?.user?.external_user_id;
                                                                const isMeCurrent = (() => {
                                                                    if (currentStepIndex === -1) return false;
                                                                    const cid = steps[currentStepIndex].id;
                                                                    const cidStr = cid == null ? '' : String(cid);
                                                                    if (meExt) {
                                                                        if (cidStr && cidStr === String(meExt)) return true;
                                                                    } else if (meId != null && cidStr === String(meId)) {
                                                                        return true;
                                                                    }
                                                                    return false;
                                                                })();
                                                                const derived = steps.map((s, idx) => {
                                                                    const statusValue = s.status ?? (s.approved_at ? 'approved' : 'pending');
                                                                    const isApproved = statusValue === 'approved';
                                                                    const isRejected = statusValue === 'rejected';
                                                                    const isCurrent = !isApproved && !isRejected && idx === currentStepIndex;
                                                                    return {
                                                                        name: s.name,
                                                                        avatar: s.name?.[0] || '承',
                                                                        role: idx === 0 ? '第1承認者' : `第${idx+1}承認者`,
                                                                        status: isRejected ? '却下' : (isApproved ? '承認済' : (isCurrent ? '未承認' : '待機中')),
                                                                        date: isRejected
                                                                            ? (s.rejected_at ? new Date(s.rejected_at).toLocaleDateString('ja-JP') : '')
                                                                            : (isApproved && s.approved_at ? new Date(s.approved_at).toLocaleDateString('ja-JP') : ''),
                                                                        originalApprover: s,
                                                                        isRejected,
                                                                        rejectionReason: s.rejection_reason ?? '',
                                                                        isCurrent,
                                                                    };
                                                                });
                                                                const handleRequirementCheck = async (approverId, checked) => {
                                                                    try {
                                                                        await axios.put(`/estimates/${estimate.id}/approval-requirement-check`, {
                                                                            approver_id: approverId,
                                                                            checked,
                                                                        });
                                                                        router.reload({ preserveScroll: true });
                                                                    } catch (error) {
                                                                        const message = error?.response?.data?.message || '要件定義書の確認状態を更新できませんでした。';
                                                                        alert(message);
                                                                    }
                                                                };
                                                                const approveFromSheet = () => {
                                                                    if (!confirm('この見積書を承認しますか？')) return;
                                                                    router.put(route('estimates.updateApproval', estimate.id), { action: 'approve' }, {
                                                                        onSuccess: () => {
                                                                            router.reload({ preserveScroll: true });
                                                                            setRejectForm({ id: null, reason: '' });
                                                                        },
                                                                        onError: (errors) => { alert(errors?.approval || '承認中にエラーが発生しました。'); }
                                                                    });
                                                                };
                                                                const submitRejectFromSheet = () => {
                                                                    if (rejectForm.id !== estimate.id || !rejectForm.reason.trim()) {
                                                                        alert('却下理由を入力してください。');
                                                                        return;
                                                                    }
                                                                    if (!confirm('この見積書を却下しますか？')) return;
                                                                    router.put(route('estimates.updateApproval', estimate.id), { action: 'reject', reason: rejectForm.reason }, {
                                                                        onSuccess: () => {
                                                                            router.reload({ preserveScroll: true });
                                                                            setRejectForm({ id: null, reason: '' });
                                                                        },
                                                                        onError: (errors) => { alert(errors?.approval || '却下処理中にエラーが発生しました。'); }
                                                                    });
                                                                };
                                                                const isRejectingThis = rejectForm.id === estimate.id;
                                                                return (
                                                            <Card>
                                                                <CardHeader>
                                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                                        <CardTitle className="flex items-center gap-2">
                                                                            <CheckCircle className="h-5 w-5" />
                                                                            承認フロー
                                                                        </CardTitle>
                                                                        {estimate?.google_docs_url && (
                                                                            <Button
                                                                                type="button"
                                                                                variant="outline"
                                                                                size="sm"
                                                                                onClick={() => window.open(estimate.google_docs_url, '_blank', 'noopener,noreferrer')}
                                                                            >
                                                                                要件定義書を開く
                                                                            </Button>
                                                                        )}
                                                                    </div>
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
                                                                                <div key={index} className="flex items-start gap-4 relative">
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
                                                                                                    <div className="text-right">
                                                                                <div className="flex items-center justify-end gap-2 mb-1">
                                                                                    {step.isCurrent && isMeCurrent && (
                                                                                        isRejectingThis ? (
                                                                                            <div className="flex flex-col gap-2 w-56">
                                                                                                <Textarea
                                                                                                    value={rejectForm.reason}
                                                                                                    onChange={(e) => setRejectForm({ id: estimate.id, reason: e.target.value })}
                                                                                                    placeholder="却下理由を入力"
                                                                                                    className="text-sm"
                                                                                                    rows={3}
                                                                                                />
                                                                                                <div className="flex items-center gap-2">
                                                                                                    <Button onClick={submitRejectFromSheet} size="sm" variant="destructive">理由を送信</Button>
                                                                                                    <Button onClick={() => setRejectForm({ id: null, reason: '' })} size="sm" variant="outline">キャンセル</Button>
                                                                                                </div>
                                                                                            </div>
                                                                                        ) : (
                                                                                            <div className="flex items-center gap-2">
                                                                                                <Button onClick={approveFromSheet} size="sm" className="bg-black hover:bg-black/90 text-white">承認する</Button>
                                                                                                <Button onClick={() => setRejectForm({ id: estimate.id, reason: '' })} size="sm" variant="destructive">却下する</Button>
                                                                                            </div>
                                                                                        )
                                                                                    )}
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
                                                                                                            ? `このステップで却下されました。${step.rejectionReason ? `理由: ${step.rejectionReason}` : ''}`
                                                                                                            : (step.isCurrent ? '承認待ちです。' : '前段の承認待ちです。')}
                                                                                                </div>
                                                                                                <div className="mt-3 flex items-center gap-2">
                                                                                                    {requiresRequirementDoc && (
                                                                                                        <>
                                                                                                            <Checkbox
                                                                                                                id={`requirement-check-${estimate.id}-${index}`}
                                                                                                                checked={Boolean(step.originalApprover?.requirements_checked)}
                                                                                                                disabled={!estimate?.google_docs_url || (() => {
                                                                                                                    const stepId = step.originalApprover?.id == null ? '' : String(step.originalApprover.id);
                                                                                                                    const meId = props.auth?.user?.id != null ? String(props.auth.user.id) : '';
                                                                                                                    const meExternal = props.auth?.user?.external_user_id != null ? String(props.auth.user.external_user_id) : '';
                                                                                                                    return !(stepId !== '' && (stepId === meExternal || stepId === meId));
                                                                                                                })()}
                                                                                                                onCheckedChange={(checked) => {
                                                                                                                    handleRequirementCheck(step.originalApprover?.id, checked === true);
                                                                                                                }}
                                                                                                            />
                                                                                                            <label htmlFor={`requirement-check-${estimate.id}-${index}`} className="text-sm text-slate-700">
                                                                                                                要件定義書を確認済み
                                                                                                            </label>
                                                                                                            {step.originalApprover?.requirements_checked_at && (
                                                                                                                <span className="text-xs text-slate-500">
                                                                                                                    {new Date(step.originalApprover.requirements_checked_at).toLocaleDateString('ja-JP')}
                                                                                                                </span>
                                                                                                            )}
                                                                                                        </>
                                                                                                    )}
                                                                                                </div>
                                                                                            </CardContent>
                                                                                        </Card>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
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
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
