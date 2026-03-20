import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fragment, useState, useEffect, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import axios from 'axios';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/Components/ui/accordion";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/Components/ui/dropdown-menu";
import { DotsHorizontalIcon } from "@radix-ui/react-icons";
import {
    FileText,
    PlusCircle,
    Search,
    Filter,
    CheckCircle,
    Calendar,
    Eye,
    Edit,
    Trash2,
    Copy,
    Clock,
    AlertCircle,
    Target,
    ClipboardCheck,
    ExternalLink
} from 'lucide-react';
import { cn } from "@/lib/utils";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import SyncButton from '@/Components/SyncButton';
import EstimateDetailSheet from '@/Components/EstimateDetailSheet';

const computeDefaultQuoteMonth = (value) => {
    if (value) return value;
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${now.getFullYear()}-${month}`;
};

const WORKLOAD_STATUS_META = {
    ok: {
        label: '余力あり',
        badgeClassName: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    },
    warning: {
        label: '要注意',
        badgeClassName: 'bg-amber-50 text-amber-800 border border-amber-200',
    },
    danger: {
        label: '過負荷',
        badgeClassName: 'bg-red-50 text-red-700 border border-red-200',
    },
};

const isPastDate = (value) => {
    if (!value) return false;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return false;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    date.setHours(0, 0, 0, 0);

    return date < today;
};

const resolveDeadlineDate = (estimate) => estimate?.follow_up_due_date ?? estimate?.due_date ?? null;

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

    switch (filters.quickView) {
        case 'pending':
            temp = temp.filter((e) => (e.status || '') === 'pending');
            break;
        case 'lost':
            temp = temp.filter((e) => (e.status || '') === 'lost');
            break;
        case 'mine':
            temp = temp.filter((e) => {
                const currentUser = options.currentUser;
                if (!currentUser) return false;
                return e.staff_id === currentUser.id || (e.staff_name || '') === currentUser.name;
            });
            break;
        case 'mf_pending':
            temp = temp.filter((e) => (e.status || '') === 'sent' && !e.mf_quote_id);
            break;
        case 'overdue':
            temp = temp.filter((e) => e.status !== 'lost' && !e.is_order_confirmed && isPastDate(resolveDeadlineDate(e)));
            break;
        case 'attention':
            temp = temp.filter((e) =>
                (e.status || '') !== 'lost' && (
                (e.status || '') === 'pending' ||
                ((e.status || '') === 'sent' && !e.mf_quote_id) ||
                (!e.is_order_confirmed && isPastDate(resolveDeadlineDate(e)))
                )
            );
            break;
        default:
            break;
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
    const quoteOperationsSummary = useMemo(() => props.quoteOperationsSummary ?? null, [props.quoteOperationsSummary]);
    const overdueFollowUpPrompt = useMemo(() => props.overdueFollowUpPrompt ?? null, [props.overdueFollowUpPrompt]);
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
    const [isOverduePromptOpen, setIsOverduePromptOpen] = useState(false);
    const [isSubmittingFollowUp, setIsSubmittingFollowUp] = useState(false);
    const [overdueDecision, setOverdueDecision] = useState('still_pursuing');
    const [followUpForm, setFollowUpForm] = useState({
        follow_up_due_date: '',
        overdue_decision_note: '',
        lost_reason: '',
        lost_note: '',
    });

    const defaultFromMonth = computeDefaultQuoteMonth(initialFilters?.from ?? defaultRange?.from);
    const defaultToMonth = computeDefaultQuoteMonth(initialFilters?.to ?? defaultRange?.to);

    const initialFilterState = {
        title: initialFilters?.title ?? '',
        issue_month_from: defaultFromMonth,
        issue_month_to: defaultToMonth,
        partner: initialFilters?.partner ?? '',
        status: initialFilters?.status ?? '',
        quickView: 'all',
    };

    const initialFocusedEstimateId = parseEstimateId(focusEstimateId);

    const [filters, setFilters] = useState(initialFilterState);
    const [appliedFilters, setAppliedFilters] = useState(initialFilterState);
    const [activeDetailId, setActiveDetailId] = useState(initialFocusedEstimateId);
    const [filteredEstimates, setFilteredEstimates] = useState(() =>
        filterEstimatesList(estimates, initialFilterState, {
            includeEstimateId: initialFocusedEstimateId,
            currentUser: auth?.user,
        })
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

    useEffect(() => {
        if (!overdueFollowUpPrompt?.id) {
            setIsOverduePromptOpen(false);
            return;
        }

        const baseDate = (() => {
            const date = new Date();
            date.setDate(date.getDate() + 7);
            return date.toISOString().slice(0, 10);
        })();

        setFollowUpForm({
            follow_up_due_date: overdueFollowUpPrompt.follow_up_due_date
                ? String(overdueFollowUpPrompt.follow_up_due_date).slice(0, 10)
                : baseDate,
            overdue_decision_note: overdueFollowUpPrompt.overdue_decision_note ?? '',
            lost_reason: '',
            lost_note: '',
        });
        setOverdueDecision('still_pursuing');
        setIsOverduePromptOpen(true);

        axios.patch(route('estimates.acknowledgeOverduePrompt', overdueFollowUpPrompt.id)).catch(() => {
            // same-day再表示抑止なので、失敗しても画面は継続させる
        });
    }, [overdueFollowUpPrompt]);

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
            },
            'lost': {
                variant: 'secondary',
                className: 'bg-slate-600 hover:bg-slate-700 text-white',
                icon: AlertCircle,
                label: '失注'
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

    // approval history is rendered from estimate.approval_flow in the detail view

    // 統計計算
    useEffect(() => {
        setFilteredEstimates(
            filterEstimatesList(estimates, appliedFilters, {
                includeEstimateId: activeDetailId,
                currentUser: auth?.user,
            })
        );
    }, [estimates, appliedFilters, activeDetailId, auth?.user]);

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
        setFilteredEstimates(filterEstimatesList(estimates, nextFilters, {
            includeEstimateId: activeDetailId,
            currentUser: auth?.user,
        }));
    };

    const resetFilters = () => {
        const reset = {
            title: '',
            issue_month_from: defaultFromMonth,
            issue_month_to: defaultToMonth,
            partner: '',
            status: '',
            quickView: 'all',
        };
        setFilters(reset);
        setAppliedFilters(reset);
        setFilteredEstimates(filterEstimatesList(estimates, reset, {
            includeEstimateId: activeDetailId,
            currentUser: auth?.user,
        }));
    };

    const baseOperationalEstimates = useMemo(() => (
        filterEstimatesList(estimates, { ...appliedFilters, quickView: 'all' }, {
            includeEstimateId: activeDetailId,
            currentUser: auth?.user,
        })
    ), [estimates, appliedFilters, activeDetailId, auth?.user]);

    const totalCount = filteredEstimates.length;
    const totalBaseCount = baseOperationalEstimates.length;
    const pendingCount = baseOperationalEstimates.filter((estimate) => estimate.status === 'pending').length;
    const myEstimateCount = baseOperationalEstimates.filter((estimate) => {
        return estimate.staff_id === auth?.user?.id || (estimate.staff_name || '') === auth?.user?.name;
    }).length;
    const mfPendingCount = baseOperationalEstimates.filter((estimate) => estimate.status === 'sent' && !estimate.mf_quote_id).length;
    const lostCount = baseOperationalEstimates.filter((estimate) => estimate.status === 'lost').length;
    const overdueCount = baseOperationalEstimates.filter((estimate) => estimate.status !== 'lost' && !estimate.is_order_confirmed && isPastDate(resolveDeadlineDate(estimate))).length;
    const attentionCount = baseOperationalEstimates.filter((estimate) => {
        return estimate.status !== 'lost' && (
            estimate.status === 'pending'
            || (estimate.status === 'sent' && !estimate.mf_quote_id)
            || (!estimate.is_order_confirmed && isPastDate(resolveDeadlineDate(estimate)))
        );
    }).length;
    const currentMonthSummary = quoteOperationsSummary?.current_month ?? null;
    const nextMonthSummary = quoteOperationsSummary?.next_month ?? null;
    const staffCount = Number(quoteOperationsSummary?.staff_count ?? 0);
    const monthlyCapacityPersonDays = Number(quoteOperationsSummary?.monthly_capacity_person_days ?? 0);

    const quickViews = [
        { key: 'all', label: '全件', helper: `${totalBaseCount}件` },
        { key: 'pending', label: '承認待ち', helper: `${pendingCount}件` },
        { key: 'mine', label: '自分の案件', helper: `${myEstimateCount}件` },
        { key: 'mf_pending', label: 'MF未発行', helper: `${mfPendingCount}件` },
        { key: 'overdue', label: '期限超過', helper: `${overdueCount}件` },
        { key: 'lost', label: '失注', helper: `${lostCount}件` },
        { key: 'attention', label: '要フォロー', helper: `${attentionCount}件` },
    ];

    const operationCards = [
        {
            key: 'pending',
            title: '承認待ち',
            value: `${pendingCount}件`,
            helper: '先に処理すべき見積',
            icon: Clock,
            accent: 'amber',
        },
        {
            key: 'mf_pending',
            title: 'MF未発行',
            value: `${mfPendingCount}件`,
            helper: '承認済だが未発行',
            icon: FileText,
            accent: 'blue',
        },
        {
            key: 'mine',
            title: '自分担当',
            value: `${myEstimateCount}件`,
            helper: `${auth?.user?.name ?? '担当者'}が持つ案件`,
            icon: Target,
            accent: 'violet',
        },
        {
            key: 'overdue',
            title: '期限超過',
            value: `${overdueCount}件`,
            helper: '未受注の要確認案件',
            icon: AlertCircle,
            accent: 'rose',
        },
    ];

    const workloadCards = [
        {
            key: 'current_month',
            title: currentMonthSummary?.label ?? '今月',
            summary: currentMonthSummary,
        },
        {
            key: 'next_month',
            title: nextMonthSummary?.label ?? '来月',
            summary: nextMonthSummary,
        },
    ];

    const getWorkloadStatusMeta = (status) => WORKLOAD_STATUS_META[status] ?? WORKLOAD_STATUS_META.ok;

    const applyQuickView = (quickView) => {
        const nextFilters = {
            ...filters,
            quickView,
        };
        setFilters(nextFilters);
        setAppliedFilters(nextFilters);
        setFilteredEstimates(filterEstimatesList(estimates, nextFilters, {
            includeEstimateId: activeDetailId,
            currentUser: auth?.user,
        }));
    };

    const buildEffortNotice = (estimate) => {
        if (estimate?.status === 'lost') {
            return {
                label: '失注',
                className: 'bg-slate-50 text-slate-600 border border-slate-200',
            };
        }

        const targetDate = estimate?.delivery_date ?? estimate?.issue_date ?? null;
        if (!targetDate) {
            return {
                label: '納期未設定',
                className: 'bg-slate-50 text-slate-600 border border-slate-200',
            };
        }

        const targetMonth = String(targetDate).slice(0, 7);
        const summary = [currentMonthSummary, nextMonthSummary].find((item) => item?.month === targetMonth);

        if (!summary) {
            return {
                label: '2ヶ月先以降',
                className: 'bg-slate-50 text-slate-600 border border-slate-200',
            };
        }

        const monthLabel = summary.label.replace(/^\d{4}年/, '');
        if (summary.utilization_rate >= 100) {
            return {
                label: `${monthLabel}過負荷`,
                className: 'bg-red-50 text-red-700 border border-red-200',
            };
        }
        if (summary.utilization_rate >= 85) {
            return {
                label: `${monthLabel}逼迫`,
                className: 'bg-amber-50 text-amber-800 border border-amber-200',
            };
        }

        return {
            label: `${monthLabel}余力あり`,
            className: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        };
    };

    const buildDeadlineNotice = (estimate) => {
        if (estimate?.status === 'lost') {
            return {
                label: '失注',
                helper: estimate?.lost_at ? `登録日 ${formatIssueDate(estimate.lost_at)}` : '営業追跡を終了',
                className: 'bg-slate-50 text-slate-600 border border-slate-200',
            };
        }

        const deadline = resolveDeadlineDate(estimate);
        if (!deadline) {
            return {
                label: '期限未設定',
                helper: '見積期限なし',
                className: 'bg-slate-50 text-slate-600 border border-slate-200',
            };
        }

        if (isPastDate(deadline) && !estimate?.is_order_confirmed) {
            return {
                label: '期限超過',
                helper: estimate?.follow_up_due_date ? `追跡期限 ${formatIssueDate(deadline)}` : formatIssueDate(deadline),
                className: 'bg-red-50 text-red-700 border border-red-200',
            };
        }

        return {
            label: '期限内',
            helper: estimate?.follow_up_due_date ? `追跡期限 ${formatIssueDate(deadline)}` : formatIssueDate(deadline),
            className: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        };
    };

    const handleOverdueDecisionSubmit = () => {
        if (!overdueFollowUpPrompt?.id) {
            return;
        }

        if (overdueDecision === 'lost') {
            if (!followUpForm.lost_reason.trim()) {
                alert('失注理由を選択してください。');
                return;
            }

            setIsSubmittingFollowUp(true);
            router.patch(route('estimates.markLost', overdueFollowUpPrompt.id), {
                lost_reason: followUpForm.lost_reason,
                lost_note: followUpForm.lost_note,
                lost_at: new Date().toISOString().slice(0, 10),
            }, {
                preserveScroll: true,
                onFinish: () => setIsSubmittingFollowUp(false),
                onSuccess: () => setIsOverduePromptOpen(false),
            });
            return;
        }

        if (!followUpForm.follow_up_due_date) {
            alert('追跡期限を入力してください。');
            return;
        }

        setIsSubmittingFollowUp(true);
        router.patch(route('estimates.extendOverdueFollowUp', overdueFollowUpPrompt.id), {
            follow_up_due_date: followUpForm.follow_up_due_date,
            overdue_decision_note: followUpForm.overdue_decision_note,
        }, {
            preserveScroll: true,
            onFinish: () => setIsSubmittingFollowUp(false),
            onSuccess: () => setIsOverduePromptOpen(false),
        });
    };

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
            <Dialog open={isOverduePromptOpen} onOpenChange={setIsOverduePromptOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>期限超過の見積があります</DialogTitle>
                        <DialogDescription>
                            {overdueFollowUpPrompt?.title ?? '見積案件'} が期限を過ぎています。失注にするか、まだ追うかを選んでください。
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <div>見積番号: {overdueFollowUpPrompt?.estimate_number ?? '—'}</div>
                            <div>顧客名: {overdueFollowUpPrompt?.customer_name ?? '—'}</div>
                            <div>現在の期限: {resolveDeadlineDate(overdueFollowUpPrompt) ? formatIssueDate(resolveDeadlineDate(overdueFollowUpPrompt)) : '未設定'}</div>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                className={cn(
                                    'rounded-xl border px-4 py-3 text-left',
                                    overdueDecision === 'still_pursuing'
                                        ? 'border-blue-600 bg-blue-50 text-blue-900'
                                        : 'border-slate-200 bg-white text-slate-700'
                                )}
                                onClick={() => setOverdueDecision('still_pursuing')}
                            >
                                <div className="font-semibold">まだ追う</div>
                                <div className="mt-1 text-xs">追跡期限を延長して次回確認日を決めます。</div>
                            </button>
                            <button
                                type="button"
                                className={cn(
                                    'rounded-xl border px-4 py-3 text-left',
                                    overdueDecision === 'lost'
                                        ? 'border-red-600 bg-red-50 text-red-900'
                                        : 'border-slate-200 bg-white text-slate-700'
                                )}
                                onClick={() => setOverdueDecision('lost')}
                            >
                                <div className="font-semibold">失注にする</div>
                                <div className="mt-1 text-xs">営業追跡を終了し、期限超過一覧から外します。</div>
                            </button>
                        </div>

                        {overdueDecision === 'still_pursuing' ? (
                            <div className="space-y-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">いつまで追うか</label>
                                    <Input
                                        type="date"
                                        value={followUpForm.follow_up_due_date}
                                        onChange={(event) => setFollowUpForm((prev) => ({ ...prev, follow_up_due_date: event.target.value }))}
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">補足メモ</label>
                                    <Textarea
                                        rows={4}
                                        value={followUpForm.overdue_decision_note}
                                        onChange={(event) => setFollowUpForm((prev) => ({ ...prev, overdue_decision_note: event.target.value }))}
                                        placeholder="延長理由や次回確認の観点を記録"
                                    />
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">失注理由</label>
                                    <select
                                        value={followUpForm.lost_reason}
                                        onChange={(event) => setFollowUpForm((prev) => ({ ...prev, lost_reason: event.target.value }))}
                                        className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                                    >
                                        <option value="">選択してください</option>
                                        <option value="価格">価格</option>
                                        <option value="競合">競合</option>
                                        <option value="時期見送り">時期見送り</option>
                                        <option value="要件未確定">要件未確定</option>
                                        <option value="予算未確保">予算未確保</option>
                                        <option value="その他">その他</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">補足メモ</label>
                                    <Textarea
                                        rows={4}
                                        value={followUpForm.lost_note}
                                        onChange={(event) => setFollowUpForm((prev) => ({ ...prev, lost_note: event.target.value }))}
                                        placeholder="失注理由の補足や再提案の余地があれば記録"
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setIsOverduePromptOpen(false)}>
                            後で確認
                        </Button>
                        <Button type="button" onClick={handleOverdueDecisionSubmit} disabled={isSubmittingFollowUp}>
                            {isSubmittingFollowUp ? '保存中…' : overdueDecision === 'lost' ? '失注登録する' : '追跡期限を保存する'}
                        </Button>
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

                <Card className="border-0 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white shadow-xl">
                    <CardContent className="flex flex-col gap-5 px-6 py-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100">
                                <ClipboardCheck className="h-3.5 w-3.5" />
                                見積運用ワークスペース
                            </div>
                            <div>
                                <h3 className="text-2xl font-bold">今やるべき見積を先に処理する画面</h3>
                                <p className="mt-1 text-sm text-slate-300">
                                    承認待ち・MF未発行・担当案件・期限超過を優先表示し、経営分析はダッシュボードへ分離します。
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-sm text-slate-200">
                                <div className="text-xs text-slate-400">一覧対象</div>
                                <div className="mt-1 font-semibold">{totalCount}件</div>
                            </div>
                            <Link href={route('dashboard')}>
                                <Button variant="secondary" className="bg-white text-slate-900 hover:bg-slate-100">
                                    経営ダッシュボードを見る
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
                    <Card className="border-slate-200 shadow-sm">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">保存ビュー</CardTitle>
                            <CardDescription>実務でよく使う条件をワンクリックで切り替えます。</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {quickViews.map((view) => {
                                const active = filters.quickView === view.key;
                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() => applyQuickView(view.key)}
                                        className={cn(
                                            'rounded-full border px-4 py-2 text-left transition',
                                            active
                                                ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                        )}
                                    >
                                        <div className="text-sm font-semibold">{view.label}</div>
                                        <div className={cn('text-xs', active ? 'text-blue-100' : 'text-slate-500')}>
                                            {view.helper}
                                        </div>
                                    </button>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 shadow-sm">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">受注ベース工数の目安</CardTitle>
                            <CardDescription>
                                予算ではなく、注文確定済み案件の工数で今月・来月の余力を判断します。
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {workloadCards.map(({ key, title, summary }) => {
                                const meta = getWorkloadStatusMeta(summary?.status);
                                return (
                                    <div key={key} className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="text-sm font-semibold text-slate-900">{title}</div>
                                            <span className={cn('rounded-full px-2.5 py-1 text-xs font-medium', meta.badgeClassName)}>
                                                {meta.label}
                                            </span>
                                        </div>
                                        <div className="mt-3 text-2xl font-bold text-slate-950">
                                            {summary?.utilization_rate?.toFixed?.(1) ?? '0.0'}%
                                        </div>
                                        <div className="mt-2 space-y-1 text-xs text-slate-600">
                                            <div>受注工数 {summary?.planned_person_days ?? 0} 人日</div>
                                            <div>残り {summary?.available_person_days ?? 0} 人日</div>
                                            <div>受注件数 {summary?.confirmed_count ?? 0} 件</div>
                                        </div>
                                    </div>
                                );
                            })}
                            <div className="sm:col-span-2 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                                基準キャパ: {staffCount}人 / 月間 {monthlyCapacityPersonDays.toFixed(1)} 人日
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {operationCards.map((card) => {
                        const Icon = card.icon;
                        const accentMap = {
                            amber: 'from-amber-50 to-orange-50 text-amber-900 border-amber-100',
                            blue: 'from-blue-50 to-cyan-50 text-blue-900 border-blue-100',
                            violet: 'from-violet-50 to-fuchsia-50 text-violet-900 border-violet-100',
                            rose: 'from-rose-50 to-red-50 text-rose-900 border-rose-100',
                        };

                        return (
                            <Card key={card.key} className={cn('border bg-gradient-to-br shadow-sm', accentMap[card.accent])}>
                                <CardContent className="flex items-start justify-between gap-3 px-5 py-5">
                                    <div>
                                        <div className="text-sm font-medium text-slate-700">{card.title}</div>
                                        <div className="mt-2 text-3xl font-bold">{card.value}</div>
                                        <div className="mt-2 text-xs text-slate-600">{card.helper}</div>
                                    </div>
                                    <div className="rounded-full bg-white/80 p-2 shadow-sm">
                                        <Icon className="h-5 w-5 text-slate-700" />
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
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
                                                <option value="lost">失注</option>
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
                                        <TableHead className="font-semibold">工数注意</TableHead>
                                        <TableHead className="font-semibold">期限</TableHead>
                                        <TableHead className="font-semibold">主要操作</TableHead>
                                        <TableHead className="w-[50px] font-semibold text-center">その他</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredEstimates.map((estimate) => {
                                        const customerPortalUrl = normalizedCustomerPortalBase && estimate.pm_customer_id
                                            ? `${normalizedCustomerPortalBase}/${estimate.pm_customer_id}`
                                            : null;
                                        const effortNotice = buildEffortNotice(estimate);
                                        const deadlineNotice = buildDeadlineNotice(estimate);

                                        return (
                                            <Fragment key={estimate.id}>
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
                                                        <span className={cn('inline-flex rounded-full px-2.5 py-1 text-xs font-medium', effortNotice.className)}>
                                                            {effortNotice.label}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="space-y-1">
                                                            <span className={cn('inline-flex rounded-full px-2.5 py-1 text-xs font-medium', deadlineNotice.className)}>
                                                                {deadlineNotice.label}
                                                            </span>
                                                            <div className="text-xs text-slate-500">{deadlineNotice.helper}</div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-8"
                                                                onClick={() => handleDetailSheetChange(estimate.id, true)}
                                                            >
                                                                <Eye className="mr-1 h-3.5 w-3.5" />
                                                                詳細
                                                            </Button>
                                                            <Link href={route('estimates.edit', estimate.id)}>
                                                                <Button type="button" variant="outline" size="sm" className="h-8">
                                                                    <Edit className="mr-1 h-3.5 w-3.5" />
                                                                    編集
                                                                </Button>
                                                            </Link>
                                                            {estimate.mf_quote_id && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="h-8"
                                                                    onClick={() => window.location.href = route('estimates.viewQuote.start', { estimate: estimate.id })}
                                                                >
                                                                    <ExternalLink className="mr-1 h-3.5 w-3.5" />
                                                                    PDF
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" className="h-8 w-8 p-0 hover:bg-slate-100">
                                                                    <DotsHorizontalIcon className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-48">
                                                                <DropdownMenuItem
                                                                    onClick={() => handleDetailSheetChange(estimate.id, true)}
                                                                    className="flex items-center gap-2"
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                    詳細を見る
                                                                </DropdownMenuItem>
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
                                                <EstimateDetailSheet
                                                    estimate={estimate}
                                                    isOpen={activeDetailId === estimate.id}
                                                    onClose={() => handleDetailSheetChange(estimate.id, false)}
                                                />
                                            </Fragment>
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
