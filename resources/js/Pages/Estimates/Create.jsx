import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Bar, BarChart, PieChart, Pie, Cell, ResponsiveContainer, XAxis, Tooltip } from 'recharts';
import { PlusCircle, MinusCircle, Trash2, ArrowUp, ArrowDown, Copy, FileText, Eye, Check, ChevronsUpDown } from 'lucide-react';
import { cn } from "@/lib/utils"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/Components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog'
import { Badge } from '@/Components/ui/badge';
import axios from 'axios';

const REQUIRED_FIELD_LABELS = {
    title: '件名',
    customer_name: '顧客名',
    mf_department_id: '取引先部門',
    staff_name: '自社担当者',
    issue_date: '発行日',
    items: '明細',
};

// --- Components defined outside EstimateCreate to prevent state loss on re-render ---

function CustomerCombobox({ selectedCustomer, onCustomerChange }) {
    const [open, setOpen] = useState(false);
    const [customers, setCustomers] = useState([]);
    const [search, setSearch] = useState("");
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            setSearch("");
            setCustomers([]);
            setLoading(false);
            return;
        }

        const trimmed = search.trim();
        if (!trimmed) {
            setCustomers([]);
            setLoading(false);
            return;
        }

        let ignore = false;
        const fetchCustomers = async () => {
            try {
                setLoading(true);
                const response = await axios.get('/api/customers', { params: { search: trimmed } });
                const fetched = response.data.map(c => ({
                    id: c.id,
                    customer_name: c.customer_name,
                    department_id: c.department_id // Assuming department_id is returned
                }));
                if (!ignore) {
                    setCustomers(fetched);
                }
            } catch (error) {
                console.error("Failed to fetch customers:", error);
                if (!ignore) {
                    setCustomers([]);
                }
            } finally {
                if (!ignore) {
                    setLoading(false);
                }
            }
        };
        fetchCustomers();

        return () => {
            ignore = true;
        };
    }, [open, search]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between"
                >
                    {selectedCustomer
                        ? selectedCustomer.customer_name
                        : "顧客を選択..."}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                side="bottom"
                sideOffset={4}
                avoidCollisions={false}
                className="w-full p-0"
            >
                <Command>
                    <CommandInput
                        value={search}
                        placeholder="顧客を検索..."
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        <CommandEmpty className="px-4 py-3 text-left text-xs text-muted-foreground">
                            {loading
                                ? "検索中..."
                                : search.trim()
                                    ? "顧客が見つかりません。"
                                    : "検索キーワードを入力してください。"}
                        </CommandEmpty>
                        {customers.length > 0 && (
                            <CommandGroup>
                                {customers.map((customer) => (
                                    <CommandItem
                                        key={customer.id}
                                        value={`${customer.customer_name ?? ''} ${customer.id ?? ''}`}
                                        onSelect={() => {
                                            onCustomerChange(customer);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                "mr-2 h-4 w-4",
                                                selectedCustomer?.id === customer.id ? "opacity-100" : "opacity-0"
                                            )}
                                        />
                                        {customer.customer_name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    )
}

function DepartmentCombobox({ partnerId, selectedDepartment, onDepartmentChange, initialDepartmentId }) {
    const [open, setOpen] = useState(false);
    const [departments, setDepartments] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!partnerId) {
            setDepartments([]);
            return;
        }
        let ignore = false;
        const fetchDepartments = async () => {
            setLoading(true);
            try {
                const res = await axios.get(`/api/partners/${encodeURIComponent(partnerId)}/departments`);
                let arr = Array.isArray(res.data) ? res.data : [];
                // クライアント側フォールバック（サーバで拾えないケースの保険）
                if (!arr || arr.length === 0) {
                    arr = [];
                }
                if (!ignore) setDepartments(arr);
            } catch (e) {
                console.error('Failed to fetch departments:', e);
                if (!ignore) setDepartments([]);
            } finally {
                if (!ignore) setLoading(false);
            }
        };
        fetchDepartments();
        return () => { ignore = true; };
    }, [partnerId]);

    // Auto-select initial by id if provided and exists
    useEffect(() => {
        if (initialDepartmentId && (!selectedDepartment || selectedDepartment.id !== initialDepartmentId)) {
            const found = departments.find(d => String(d.id) === String(initialDepartmentId));
            if (found) onDepartmentChange(found);
        }
    }, [initialDepartmentId, departments]);

    // 部門候補が1件のみの場合は自動選択
    useEffect(() => {
        if (!selectedDepartment && departments.length === 1) {
            onDepartmentChange(departments[0]);
        }
    }, [departments, selectedDepartment]);

    const displaySelectedDepartment = () => {
        if (!selectedDepartment) return null;
        if (selectedDepartment.name || selectedDepartment.person_dept) return selectedDepartment;
        // Try resolve from fetched list
        const found = departments.find(d => String(d.id) === String(selectedDepartment.id));
        return found || selectedDepartment;
    };

    const selectedDeptForView = displaySelectedDepartment();

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={!partnerId || loading}
                    className="w-full justify-between"
                >
                    {selectedDeptForView ? (selectedDeptForView.name || selectedDeptForView.person_dept || selectedDeptForView.id) : (loading ? '読込中...' : '部門を選択...')}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-full p-0">
                <Command>
                    <CommandEmpty>{loading ? '読込中...' : '部門が見つかりません。'}</CommandEmpty>
                    <CommandGroup>
                        {departments.map((d) => (
                            <CommandItem
                                key={d.id}
                                value={d.id}
                                onSelect={() => {
                                    onDepartmentChange(d);
                                    setOpen(false);
                                }}
                            >
                                <Check className={cn("mr-2 h-4 w-4", selectedDepartment?.id === d.id ? "opacity-100" : "opacity-0")} />
                                {d.name || d.person_dept || d.id}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

function StaffCombobox({ selectedStaff, onStaffChange }) {
    const [open, setOpen] = useState(false);
    const [staff, setStaff] = useState([]);
    const [search, setSearch] = useState("");

    useEffect(() => {
        const fetchStaff = async () => {
            try {
                const res = await axios.get('/api/users', { params: { search } });
                setStaff(Array.isArray(res.data) ? res.data : []);
            } catch (e) {
                console.error('Failed to fetch staff:', e);
                setStaff([]);
            }
        };
        fetchStaff();
    }, [search]);
    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between"
                >
                    {selectedStaff
                        ? selectedStaff.name
                        : "担当者を選択..."}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-full p-0">
                <Command>
                    <CommandInput placeholder="担当者を検索..." onValueChange={setSearch} />
                    <CommandEmpty>担当者が見つかりません。</CommandEmpty>
                    <CommandGroup>
                        {staff.map((s) => (
                            <CommandItem
                                key={s.id}
                                value={`${s.name ?? ''} ${s.id ?? ''}`}
                                onSelect={() => {
                                    onStaffChange(s);
                                    setOpen(false);
                                }}
                            >
                                <Check
                                    className={cn(
                                        "mr-2 h-4 w-4",
                                        selectedStaff?.id === s.id ? "opacity-100" : "opacity-0"
                                    )}
                                />
                                {s.name}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </Command>
            </PopoverContent>
        </Popover>
    )
}

// --- Main Component ---

const mapStructuredRequirements = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return { functional: [], nonFunctional: [], unresolved: [] };
    }

    const functional = Array.isArray(payload.functional)
        ? payload.functional
        : Array.isArray(payload.functional_requirements)
            ? payload.functional_requirements
            : [];
    const nonFunctional = Array.isArray(payload.nonFunctional)
        ? payload.nonFunctional
        : Array.isArray(payload.non_functional)
            ? payload.non_functional
            : Array.isArray(payload.non_functional_requirements)
                ? payload.non_functional_requirements
                : [];
    const unresolved = Array.isArray(payload.unresolved)
        ? payload.unresolved
        : Array.isArray(payload.unresolved_requirements)
            ? payload.unresolved_requirements
            : Array.isArray(payload.pending_requirements)
                ? payload.pending_requirements
                : [];

    return {
        functional,
        nonFunctional,
        unresolved,
    };
};

export default function EstimateCreate({ auth, products, users = [], estimate = null, is_fully_approved = false }) {
    const isEditMode = estimate !== null;

    const [isInternalView, setIsInternalView] = useState(true);
    const normalizeNumber = (value, fallback = 0) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : fallback;
        }
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        }
        return fallback;
    };

    const transformIncomingItems = (items = []) => items.map((item, index) => ({
        id: item.id ?? item.__temp_id ?? Date.now() + index,
        product_id: item.product_id ?? null,
        code: item.code ?? item.sku ?? null,
        name: item.name ?? '',
        description: item.description ?? item.detail ?? '',
        qty: normalizeNumber(item.qty ?? item.quantity, 1) || 1,
        unit: item.unit ?? '式',
        price: normalizeNumber(item.price, 0),
        cost: normalizeNumber(item.cost, 0),
        tax_category: item.tax_category ?? 'standard',
        display_mode: item.display_mode ?? 'calculated',
        display_qty: normalizeNumber(item.display_qty, 1) || 1,
        display_unit: item.display_unit ?? '式',
        business_division: item.business_division ?? null,
    }));

    const [lineItems, setLineItems] = useState(() => transformIncomingItems(estimate?.items));
    const displayModeOptions = [
        { value: 'calculated', label: '数量表示' },
        { value: 'lump', label: '1式表示' },
    ];
    const preventArrowKeyChange = (event) => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
        }
    };
    const notePromptPlaceholder = '例: 検収基準・納期の条件・クライアント提供物・変更手続き・保守保証など備考に明記したい要素';

    const [notePrompt, setNotePrompt] = useState('');
    const [notePromptError, setNotePromptError] = useState(null);
    const [isGeneratingNotes, setIsGeneratingNotes] = useState(false);
    const [structuredRequirements, setStructuredRequirements] = useState(() => mapStructuredRequirements(estimate?.structured_requirements));
    const [isStructuringRequirements, setIsStructuringRequirements] = useState(false);
    const [structureError, setStructureError] = useState(null);
    const [pmSupportRequired, setPmSupportRequired] = useState(false);
    const [isGeneratingAiDraft, setIsGeneratingAiDraft] = useState(false);
    const [aiDraftError, setAiDraftError] = useState(null);
    const [aiDraftPreview, setAiDraftPreview] = useState([]);
    const [aiPreviewOpen, setAiPreviewOpen] = useState(false);
    const [aiNotesSuggestion, setAiNotesSuggestion] = useState('');
    const [selectedStaff, setSelectedStaff] = useState(() => (estimate?.staff_id && estimate?.staff_name)
        ? { id: estimate.staff_id, name: estimate.staff_name }
        : null
    );
    const [selectedCustomer, setSelectedCustomer] = useState(estimate ? { customer_name: estimate.customer_name, id: estimate.client_id || null } : null);
    const [approvers, setApprovers] = useState(Array.isArray(estimate?.approval_flow) ? estimate.approval_flow : []);
    const [selectedDepartment, setSelectedDepartment] = useState(() => (
        estimate?.mf_department_id
            ? {
                id: estimate.mf_department_id,
                name: null,
                person_name: estimate?.client_contact_name || null,
                person_title: estimate?.client_contact_title || null,
            }
            : null
    ));
    const [openApproval, setOpenApproval] = useState(false);
    const [openApprovalStarted, setOpenApprovalStarted] = useState(false);
    const [approvalStatus, setApprovalStatus] = useState('');
    // UI即時反映用のローカルフラグ（サーバ反映前でもボタンを切替）
    const [approvalLocal, setApprovalLocal] = useState(false);

    const [openIssueMFQuoteConfirm, setOpenIssueMFQuoteConfirm] = useState(false);
    const [openConvertToInvoiceConfirm, setOpenConvertToInvoiceConfirm] = useState(false);
    const [chatMessages, setChatMessages] = useState([]);
    const [chatInput, setChatInput] = useState('');
    const [chatLoading, setChatLoading] = useState(false);
    const [requirementsMode, setRequirementsMode] = useState('chat'); // 'chat' | 'manual'
    const createDraftChatKey = () => {
        if (typeof window === 'undefined') {
            return `draft-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
        }
        try {
            const existing = window.sessionStorage.getItem('reqchat-active-draft-key');
            if (existing) {
                return existing;
            }
            const generated = `draft-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
            window.sessionStorage.setItem('reqchat-active-draft-key', generated);
            return generated;
        } catch (error) {
            console.warn('Failed to init draft chat key', error);
            return `draft-${Date.now().toString(36)}`;
        }
    };

    const [draftChatKey, setDraftChatKey] = useState(() => (!estimate?.id ? createDraftChatKey() : null));
    const draftLocalStorageKey = draftChatKey ? `reqchat-${draftChatKey}` : null;
    const chatStorageKey = useMemo(() => {
        if (estimate?.id) {
            return `reqchat-estimate-${estimate.id}`;
        }
        return draftChatKey ? `reqchat-${draftChatKey}` : null;
    }, [estimate?.id, draftChatKey]);
    const lastAssistantMessage = useMemo(() => {
        for (let i = chatMessages.length - 1; i >= 0; i -= 1) {
            if (chatMessages[i]?.role === 'assistant') {
                return chatMessages[i];
            }
        }
        return null;
    }, [chatMessages]);
    const lastAssistantContent = useMemo(() => lastAssistantMessage?.content?.trim() ?? '', [lastAssistantMessage]);

    const handleIssueMFQuote = () => {
        router.visit(route('estimates.createQuote.start', { estimate: estimate.id }));
    };

    const handleConvertToInvoice = () => {
        router.visit(route('estimates.convertToBilling.start', { estimate: estimate.id }));
    };

    const triggerOrderConfirmation = (mode) => {
        setOrderConfirmMode(mode);
        setOrderConfirmDialogOpen(true);
    };

    const submitOrderConfirmation = () => {
        if (!estimate?.id) return;
        router.post(route('estimates.orderConfirmation', estimate.id), {
            confirmed: orderConfirmMode === 'confirm',
        }, {
            onSuccess: () => {
                setOrderConfirmDialogOpen(false);
                setData('is_order_confirmed', orderConfirmMode === 'confirm');
            },
            onError: (errors) => {
                alert(errors?.order || '受注確定処理でエラーが発生しました。');
            },
            preserveScroll: true,
        });
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        if (typeof dateString === 'string' && dateString.includes('T')) {
            return dateString.split('T')[0];
        }
        // already YYYY-MM-DD
        return dateString;
    };

    const today = new Date();
    const issueDateDefault = new Date(today).toISOString().slice(0, 10);
    const futureDate = new Date(today);
    futureDate.setDate(today.getDate() + 30);
    const dueDateDefault = futureDate.toISOString().slice(0, 10);

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        id: estimate?.id || null,
        customer_name: estimate?.customer_name || '',
        client_contact_name: estimate?.client_contact_name || '',
        client_contact_title: estimate?.client_contact_title || '',
        client_id: estimate?.client_id || null,
        mf_department_id: estimate?.mf_department_id || null,
        title: estimate?.title || '',
        issue_date: isEditMode ? formatDate(estimate?.issue_date) : issueDateDefault,
        due_date: isEditMode ? formatDate(estimate?.due_date) : dueDateDefault,
        delivery_date: isEditMode ? formatDate(estimate?.delivery_date) : '',
        total_amount: estimate?.total_amount || 0,
        tax_amount: estimate?.tax_amount || 0,
        notes: estimate?.notes || '',
        internal_memo: estimate?.internal_memo || '',
        requirement_summary: estimate?.requirement_summary || '',
        delivery_location: estimate?.delivery_location || '',
        items: transformIncomingItems(estimate?.items),
        estimate_number: estimate?.estimate_number || '',
        staff_id: estimate?.staff_id || null,
        staff_name: estimate?.staff_name || (estimate?.staff ? estimate.staff.name : null) || null,
        approval_flow: Array.isArray(estimate?.approval_flow) ? estimate.approval_flow : [],
        status: estimate?.status || 'draft',
        is_order_confirmed: estimate?.is_order_confirmed ?? false,
        structured_requirements: estimate?.structured_requirements ?? null,
    });

    const hasRequirementSummary = (data.requirement_summary ?? '').trim() !== '';
    const canGenerateOverview = requirementsMode === 'chat'
        ? Boolean(lastAssistantContent)
        : hasRequirementSummary;
    const canGenerateDraft = hasRequirementSummary || (requirementsMode === 'chat' && Boolean(lastAssistantContent));

    useEffect(() => {
        const hasContent = structuredRequirements.functional.length > 0
            || structuredRequirements.nonFunctional.length > 0
            || structuredRequirements.unresolved.length > 0;
        setData('structured_requirements', hasContent ? {
            functional: structuredRequirements.functional,
            non_functional: structuredRequirements.nonFunctional,
            unresolved: structuredRequirements.unresolved,
        } : null);
    }, [structuredRequirements]);

    const [submitErrors, setSubmitErrors] = useState([]);
    const [hasRequiredError, setHasRequiredError] = useState(false);
    const [orderConfirmDialogOpen, setOrderConfirmDialogOpen] = useState(false);
    const [orderConfirmMode, setOrderConfirmMode] = useState('confirm');

    useEffect(() => {
        setLineItems(transformIncomingItems(estimate?.items));
    }, [estimate?.items]);

    const clearDraftChatStorage = useCallback(() => {
        if (typeof window !== 'undefined') {
            if (draftLocalStorageKey) {
                window.localStorage.removeItem(draftLocalStorageKey);
            }
            window.sessionStorage.removeItem('reqchat-active-draft-key');
        }
        setDraftChatKey(null);
    }, [draftLocalStorageKey]);

    useEffect(() => {
        if (!estimate?.id) {
            setDraftChatKey((prev) => {
                if (prev) {
                    return prev;
                }
                return createDraftChatKey();
            });
        }
    }, [estimate?.id]);

    useEffect(() => {
        const syncDraftChat = async () => {
            if (!estimate?.id || !draftLocalStorageKey || typeof window === 'undefined') {
                return;
            }
            const cached = window.localStorage.getItem(draftLocalStorageKey);
            if (!cached) {
                clearDraftChatStorage();
                return;
            }
            let parsed;
            try {
                parsed = JSON.parse(cached);
            } catch (error) {
                console.warn('Failed to parse draft chat cache', error);
                clearDraftChatStorage();
                return;
            }
            if (!Array.isArray(parsed) || parsed.length === 0) {
                clearDraftChatStorage();
                return;
            }
            try {
                await axios.post(route('estimates.requirementChat.import', estimate.id), {
                    messages: parsed.map(({ role, content }) => ({ role, content })),
                });
                clearDraftChatStorage();
                loadChat();
            } catch (error) {
                console.error('Failed to import draft chat history', error);
            }
        };
        syncDraftChat();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estimate?.id, draftLocalStorageKey]);

    useEffect(() => {
        setChatMessages([]);
        loadChat();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estimate?.id, chatStorageKey]);

    useEffect(() => () => {
        if (!estimate?.id && typeof window !== 'undefined') {
            window.sessionStorage.removeItem('reqchat-active-draft-key');
        }
    }, [estimate?.id]);

    useEffect(() => {
        if (data.status !== 'sent' && data.is_order_confirmed) {
            setData('is_order_confirmed', false);
        }
    }, [data.status]);

    // data.status を直接参照して、現在のUIの状態を正しく判定する
    const isInApproval = useMemo(() => {
        if (approvalLocal) return true; // 申請直後は即座に取消へ
        return data.status === 'pending';
    }, [approvalLocal, data.status]);

    

    const prevPartnerIdRef = useRef(estimate?.client_id || null);
    const contactEditedRef = useRef({ name: false, title: false });
    const prevDepartmentIdRef = useRef(selectedDepartment?.id || null);
    useEffect(() => {
        const newId = selectedCustomer?.id || null;
        setData('customer_name', selectedCustomer?.customer_name || '');
        setData('client_id', newId);
        // ユーザー操作で実際に取引先が変わった時のみ部門をリセット
        if (prevPartnerIdRef.current !== newId) {
            setSelectedDepartment(null);
            setData('mf_department_id', null);
            setData('client_contact_name', '');
            setData('client_contact_title', '');
            contactEditedRef.current = { name: false, title: false };
            prevDepartmentIdRef.current = null;
            prevPartnerIdRef.current = newId;
        }
    }, [selectedCustomer]);

    // Keep staff_id/staff_name in sync when selected
    useEffect(() => {
        setData('staff_id', selectedStaff?.id || null);
        setData('staff_name', selectedStaff?.name || null);
    }, [selectedStaff]);

    // 部門選択の同期
    useEffect(() => {
        setData('mf_department_id', selectedDepartment?.id || null);
    }, [selectedDepartment]);

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        const deptId = selectedDepartment?.id || null;
        if (!deptId) {
            if (!contactEditedRef.current.name) {
                setData('client_contact_name', '');
            }
            if (!contactEditedRef.current.title) {
                setData('client_contact_title', '');
            }
            prevDepartmentIdRef.current = null;
            return;
        }

        const departmentChanged = prevDepartmentIdRef.current !== deptId;
        const deptName = selectedDepartment?.person_name ?? '';
        const deptTitle = selectedDepartment?.person_title ?? '';

        if (departmentChanged) {
            prevDepartmentIdRef.current = deptId;
            contactEditedRef.current = { name: false, title: false };
        }

        if (departmentChanged || !contactEditedRef.current.name) {
            if (data.client_contact_name !== deptName) {
                setData('client_contact_name', deptName);
            }
        }
        if (departmentChanged || !contactEditedRef.current.title) {
            if (data.client_contact_title !== deptTitle) {
                setData('client_contact_title', deptTitle);
            }
        }
    }, [selectedDepartment]);

    // These useEffects cause issues in edit mode by overwriting data. 
    // It's better to manage form state directly with setData in onChange handlers.
    // For simplicity, we will bind the inputs directly to useForm's data object.

    const calculateAmount = (item) => normalizeNumber(item.qty, 0) * normalizeNumber(item.price, 0);
    const calculateCostAmount = (item) => normalizeNumber(item.qty, 0) * normalizeNumber(item.cost, 0);
    const calculateGrossProfit = (item) => calculateAmount(item) - calculateCostAmount(item);
    const calculateGrossMargin = (item) => {
        const amount = calculateAmount(item);
        return amount !== 0 ? (calculateGrossProfit(item) / amount) * 100 : 0;
    };

    const productDivisionMaps = useMemo(() => {
        const byId = new Map();
        const bySku = new Map();
        const byName = new Map();
        products.forEach((product) => {
            const division = product?.business_division || null;
            if (product?.id !== undefined && product?.id !== null) {
                byId.set(Number(product.id), division);
            }
            const sku = (product?.sku ?? '').trim().toLowerCase();
            if (sku) {
                bySku.set(sku, division);
            }
            const name = (product?.name ?? '').trim().toLowerCase();
            if (name) {
                byName.set(name, division);
            }
        });
        return { byId, bySku, byName };
    }, [products]);

    const EXCLUDED_DIVISION = 'first_business';

    const resolveBusinessDivisionForItem = (item) => {
        if (!item) {
            return null;
        }
        if (item.product_id !== undefined && item.product_id !== null) {
            const division = productDivisionMaps.byId.get(Number(item.product_id));
            if (division !== undefined) {
                return division;
            }
        }
        const sku = String(item.code ?? item.product_code ?? item.sku ?? '')
            .trim()
            .toLowerCase();
        if (sku && productDivisionMaps.bySku.has(sku)) {
            return productDivisionMaps.bySku.get(sku);
        }
        const name = String(item.name ?? '')
            .trim()
            .toLowerCase();
        if (name && productDivisionMaps.byName.has(name)) {
            return productDivisionMaps.byName.get(name);
        }
        return null;
    };

    const subtotal = lineItems.reduce((acc, item) => acc + calculateAmount(item), 0);
    const totalCost = lineItems.reduce((acc, item) => acc + calculateCostAmount(item), 0);
    const totalGrossProfit = subtotal - totalCost;
    const totalGrossMargin = subtotal !== 0 ? (totalGrossProfit / subtotal) * 100 : 0;

    const categorizeItem = (item) => {
        const division = resolveBusinessDivisionForItem(item);
        if (division === 'first_business') return 'type1';
        if (division === 'fifth_business') return 'type5';
        const n = String(item?.name ?? '');
        const code = String(item?.code ?? '');
        const desc = String(item?.description ?? '');
        const text = `${n} ${code} ${desc}`;
        if (/第?\s*[1１]\s*種/.test(text) || /\b1\s*種/.test(text)) return 'type1';
        if (/第?\s*[5５]\s*種/.test(text) || /\b5\s*種/.test(text)) return 'type5';
        return 'other';
    };

    const categoryTotals = lineItems.reduce((acc, item) => {
        const cat = categorizeItem(item);
        const revenue = calculateAmount(item);
        const cost = calculateCostAmount(item);
        acc[cat] = acc[cat] || { revenue: 0, cost: 0 };
        acc[cat].revenue += revenue;
        acc[cat].cost += cost;
        return acc;
    }, { type1: { revenue: 0, cost: 0 }, type5: { revenue: 0, cost: 0 }, other: { revenue: 0, cost: 0 } });

    const calcRate = (revenue, cost) => revenue > 0 ? ((revenue - cost) / revenue) * 100 : 0;
    // 税区分ごとの税率（必要なら拡張）
    const taxRates = { standard: 0.1, reduced: 0.08 };
    const tax = lineItems.reduce((acc, item) => {
        const rate = taxRates[item.tax_category || 'standard'] || 0;
        return acc + (item.qty * item.price * rate);
    }, 0);
    const total = subtotal + tax;

useEffect(() => {
    const payloadItems = lineItems.map(item => ({
        product_id: item.product_id,
        code: item.code,
        name: item.name,
        description: item.description,
        qty: normalizeNumber(item.qty, 0),
        unit: item.unit,
        price: normalizeNumber(item.price, 0),
        cost: normalizeNumber(item.cost, 0),
        tax_category: item.tax_category,
        business_division: item.business_division ?? null,
        display_mode: item.display_mode,
        display_qty: item.display_mode === 'lump'
            ? (normalizeNumber(item.display_qty, 1) || 1)
            : null,
        display_unit: item.display_mode === 'lump'
            ? (item.display_unit || '式')
            : null,
    }));

    setData(prevData => ({
        ...prevData,
        items: payloadItems,
        total_amount: Math.round(total),
        tax_amount: Math.round(tax),
    }));
}, [lineItems, total, tax]);

    useEffect(() => {
        setData('approval_flow', approvers);
    }, [approvers]);


    const handleDueDateBlur = (e) => {
        const newDueDateString = e.target.value;
        if (data.issue_date && newDueDateString) {
            const issueDate = new Date(data.issue_date);
            const newDueDate = new Date(newDueDateString);

            issueDate.setHours(0, 0, 0, 0);
            newDueDate.setHours(0, 0, 0, 0);

            const diffTime = newDueDate.getTime() - issueDate.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays <= 7) {
                alert('有効期間は発行日から7日以上離れている必要があります。');
                const validDueDate = new Date(issueDate);
                validDueDate.setDate(issueDate.getDate() + 30);
                setData('due_date', validDueDate.toISOString().slice(0, 10));
            }
        }
    };

    const handlePdfPreview = () => {
        // This function needs to be adapted to use `data` from useForm
        const estimateData = {
            ...data,
            lineItems: lineItems, // ensure lineItems are from state
            // ... other fields from the form that are not in useForm
        };

        axios.post('/estimates/preview-pdf', estimateData)
            .then(response => {
                const previewHtml = response.data;
                const newWindow = window.open("", "_blank");
                if (newWindow) {
                    newWindow.document.write(previewHtml);
                    newWindow.document.close();
                } else {
                    alert("ポップアップがブロックされました。プレビューを表示するには、ポップアップを許可してください。");
                }
            })
            .catch(error => {
                console.error('Error generating web preview:', error);
                alert('プレビューの生成中にエラーが発生しました。');
            });
    };

    // 共通エラーバナー（フィールド別の下にも出すが、まとめて表示してわかりやすく）
    const ErrorBanner = () => {
        const keys = Object.keys(errors || {});
        if (keys.length === 0 && submitErrors.length === 0) return null;
        const submitErrorMessages = hasRequiredError
            ? submitErrors.map((name) => `${name}が未入力です。`)
            : submitErrors;

        const allMsgs = [
            ...keys.map(k => errors[k]).filter(Boolean),
            ...submitErrorMessages.filter(Boolean),
        ];
        if (allMsgs.length === 0) return null;
        return (
            <div className="mb-4 rounded border border-red-300 bg-red-50 text-red-800 p-3 text-sm">
                {allMsgs.map((m, i) => <div key={i}>・{String(m)}</div>)}
            </div>
        );
    };

    const addLineItem = () => {
        setLineItems(prevItems => [
            ...prevItems,
            {
                id: Date.now(), // Use temporary ID for new items
                product_id: null,
                name: '',
                description: '',
                qty: 1,
                unit: '式',
                price: 0,
                cost: 0,
                tax_category: 'standard',
                display_mode: 'calculated',
                display_qty: 1,
                display_unit: '式',
            }
        ]);
    };

    const removeLineItem = (id) => {
        setLineItems(prevItems => prevItems.filter(item => item.id !== id));
    };

    const numericFields = new Set(['qty', 'price', 'cost', 'display_qty']);

    const cleanseNumericInput = (raw) => {
        if (typeof raw !== 'string') {
            return raw;
        }
        return raw.replace(/,/g, '').trim();
    };

    const handleItemChange = (id, field, value) => {
        let incomingValue = value;
        if (numericFields.has(field) && typeof value === 'string') {
            incomingValue = cleanseNumericInput(value);
        }

        let normalizedValue = numericFields.has(field) ? normalizeNumber(incomingValue, 0) : incomingValue;

        if (field === 'display_qty' && (normalizedValue === null || normalizedValue <= 0)) {
            normalizedValue = 1;
        }

        setLineItems(prevItems =>
            prevItems.map(item =>
                item.id === id ? { ...item, [field]: normalizedValue } : item
            )
        );
    };

    const handleProductSelect = (itemId, productId) => {
        const selectedProduct = products.find(p => Number(p.id) === Number(productId));
        if (selectedProduct) {
            setLineItems(prevItems => prevItems.map(item =>
                item.id === itemId ? {
                    ...item,
                    code: selectedProduct.sku,
                    name: selectedProduct.name,
                    price: selectedProduct.price,
                    cost: selectedProduct.cost,
                    product_id: selectedProduct.id,
                    description: selectedProduct.description,
                    unit: selectedProduct.unit,
                    business_division: selectedProduct.business_division ?? null,
                } : item
            ));
        }
    };

    const isCompleteDateInput = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value);

    const handleGenerateNotes = async () => {
        setNotePromptError(null);
        if (!notePrompt.trim()) {
            setNotePromptError('プロンプトを入力してください。');
            return;
        }

        try {
            setIsGeneratingNotes(true);
            const response = await axios.post(route('estimates.generateNotes'), {
                prompt: notePrompt,
                estimate_id: data.id ?? null,
            });
            const generated = response?.data?.notes ?? '';
            if (generated !== '') {
                setData('notes', generated);
            }
            setNotePromptError(null);
        } catch (error) {
            const message = error?.response?.data?.errors?.prompt?.[0]
                ?? error?.response?.data?.message
                ?? '備考の生成に失敗しました。';
            setNotePromptError(message);
        } finally {
            setIsGeneratingNotes(false);
        }
    };

    const handleGenerateRequirementOverview = async () => {
        setStructureError(null);
        let summarySource = (data.requirement_summary || '').trim();
        if (requirementsMode === 'chat') {
            if (!lastAssistantContent) {
                setStructureError('AI整理結果がありません。');
                return;
            }
            summarySource = lastAssistantContent;
            setData('requirement_summary', lastAssistantContent);
        } else if (summarySource === '') {
            setStructureError('要件概要を入力してください。');
            return;
        }

        try {
            setIsStructuringRequirements(true);
            const response = await axios.post(route('estimates.ai.structure'), {
                requirement_summary: summarySource,
                estimate_id: data.id ?? null,
            });
            setStructuredRequirements(mapStructuredRequirements({
                functional: response?.data?.functional_requirements ?? [],
                non_functional: response?.data?.non_functional_requirements ?? [],
                unresolved: response?.data?.unresolved_requirements ?? [],
            }));
        } catch (error) {
            const message = error?.response?.data?.message ?? '要件整理に失敗しました。';
            setStructureError(message);
        } finally {
            setIsStructuringRequirements(false);
        }
    };

    const mapAiItemsToLineItems = (items = []) =>
        items.map((item, index) => {
            const qty = normalizeNumber(item.qty, 0);
            const normalizedQty = qty <= 0 ? 0.5 : Math.max(0.5, Math.round(qty * 2) / 2);
            return {
                id: Date.now() + index,
                product_id: item.product_id ?? null,
                code: item.code ?? null,
                name: item.name ?? '',
                description: item.description ?? '',
                qty: normalizedQty,
                unit: item.unit ?? '人日',
                price: normalizeNumber(item.price, 0),
                cost: normalizeNumber(item.cost, 0),
                tax_category: item.tax_category ?? 'standard',
                display_mode: 'calculated',
                display_qty: 1,
                display_unit: '式',
                business_division: item.business_division ?? null,
            };
        });

    const applyAiDraft = (mode = 'replace') => {
        if (!Array.isArray(aiDraftPreview) || aiDraftPreview.length === 0) {
            return;
        }
        const normalized = mapAiItemsToLineItems(aiDraftPreview);
        setLineItems((prev) => (mode === 'replace' ? normalized : [...prev, ...normalized]));
        setAiPreviewOpen(false);
        setAiDraftPreview([]);
    };

    const handleAdoptAiNotes = () => {
        if (aiNotesSuggestion) {
            setData('notes', aiNotesSuggestion);
        }
    };

    const handleGenerateAiDraft = async () => {
        setAiDraftError(null);
        let summarySource = (data.requirement_summary || '').trim();
        if (!summarySource && requirementsMode === 'chat' && lastAssistantContent) {
            summarySource = lastAssistantContent;
            setData('requirement_summary', lastAssistantContent);
        }
        if (!summarySource) {
            setAiDraftError('要件概要を入力してください。');
            return;
        }

        try {
            setIsGeneratingAiDraft(true);
            const response = await axios.post(route('estimates.ai.generateDraft'), {
                requirement_summary: summarySource,
                functional_requirements: structuredRequirements.functional,
                non_functional_requirements: structuredRequirements.nonFunctional,
                unresolved_requirements: structuredRequirements.unresolved,
                pm_required: pmSupportRequired,
                estimate_id: data.id ?? null,
            });

            const generatedItems = response?.data?.items ?? [];
            setAiDraftPreview(generatedItems);
            setAiNotesSuggestion(response?.data?.notes ?? '');
            setAiPreviewOpen(true);
        } catch (error) {
            const message = error?.response?.data?.message ?? 'ドラフト生成に失敗しました。';
            setAiDraftError(message);
        } finally {
            setIsGeneratingAiDraft(false);
        }
    };

    const groupedAnalysisData = lineItems.reduce((acc, item) => {
        const itemName = item.name && item.name !== '' ? item.name : '未設定';
        if (!acc[itemName]) {
            acc[itemName] = {
                grossProfit: 0,
                cost: 0,
            };
        }
        acc[itemName].grossProfit += calculateGrossProfit(item);
        acc[itemName].cost += calculateCostAmount(item);
        return acc;
    }, {});

    const grossProfitChartData = Object.entries(groupedAnalysisData).map(([itemName, data]) => ({
        name: itemName,
        value: data.grossProfit,
    }));

    const costChartData = Object.entries(groupedAnalysisData).map(([itemName, data]) => ({
        name: itemName,
        value: data.cost,
    }));

    const effortData = lineItems.reduce((acc, item) => {
        const division = resolveBusinessDivisionForItem(item);
        if (division === EXCLUDED_DIVISION) {
            return acc;
        }
        const key = item.name && item.name !== '' ? item.name : '未設定';
        const qty = normalizeNumber(item.qty, 0);
        if (qty === 0) return acc;
        acc[key] = (acc[key] || 0) + qty;
        return acc;
    }, {});

    const effortChartData = Object.entries(effortData).map(([name, value]) => ({
        name,
        value,
    }));

    const totalEffort = effortChartData.reduce((sum, entry) => sum + entry.value, 0);

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#AF19FF', '#FF1919', '#19FFD4', '#FF19B8', '#8884d8', '#82ca9d', '#a4de6c', '#d0ed57', '#ffc658', '#ff7300', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];

    const saveDraft = () => {
        post(route('estimates.saveDraft'), {
            onSuccess: (page) => {
                if (!isEditMode) {
                    const newEstimateId = page?.props?.estimate?.id
                        || page?.props?.flash?.estimate_id
                        || page?.props?.estimate_id
                        || null;

                    const redirectUrl = route('estimates.edit', newEstimateId);

                    const syncAndRedirect = async () => {
                        if (redirectUrl) {
                            // サーバー側で同期する感覚が得られるよう軽微な遅延
                            await new Promise((resolve) => setTimeout(resolve, 150));
                            clearDraftChatStorage();
                            window.location.href = redirectUrl;
                        } else {
                            window.location.reload();
                        }
                    };

                    syncAndRedirect();
                } else {
                    alert('下書きが保存されました。');
                }
            },
            onError: (e) => console.error('下書き保存エラー:', e),
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isInApproval) {
            setApprovalStatus('success');
        } else {
            setApprovalStatus('');
        }
        setOpenApproval(true);
    };

    const loadChat = async () => {
        try {
            setChatLoading(true);
            if (!estimate?.id) {
                if (!chatStorageKey) {
                    setChatMessages([]);
                    return;
                }
                const local = localStorage.getItem(chatStorageKey);
                if (local) {
                    setChatMessages(JSON.parse(local));
                } else {
                    setChatMessages([]);
                }
            } else {
                const res = await axios.get(route('estimates.requirementChat.show', estimate.id));
                setChatMessages(res.data?.messages || []);
            }
        } catch (e) {
            console.error('requirement chat load failed', e);
        } finally {
            setChatLoading(false);
        }
    };

    const sendChat = async () => {
        if (chatInput.trim() === '') return;
        const userMessage = {
            id: `local-${Date.now()}`,
            role: 'user',
            content: chatInput,
        };
        setChatMessages((prev) => [...prev, userMessage]);
        setChatInput('');

        try {
            setChatLoading(true);
            if (!estimate?.id) {
                const res = await axios.post(route('estimates.requirementChat.draft'), {
                    messages: [...chatMessages, userMessage].map(m => ({ role: m.role, content: m.content })),
                });
                const assistant = res.data?.assistant;
                const withAssistant = assistant
                    ? [...chatMessages, userMessage, { id: `draft-assistant-${Date.now()}`, role: 'assistant', content: assistant }]
                    : [...chatMessages, userMessage];
                setChatMessages(withAssistant);
                if (chatStorageKey) {
                    localStorage.setItem(chatStorageKey, JSON.stringify(withAssistant));
                }
            } else {
                const res = await axios.post(route('estimates.requirementChat.store', estimate.id), {
                    message: userMessage.content,
                });
                setChatMessages(res.data?.messages || []);
            }
        } catch (e) {
            console.error('requirement chat send failed', e);
            setChatMessages((prev) => prev.filter((m) => m.id !== userMessage.id));
        } finally {
            setChatLoading(false);
        }
    };

    const submitWithApprovers = () => {
        // 送信直前に明確な payload を構築して、状態更新の非同期に依存しない
        const payload = { ...data, status: 'pending' };
        if (!payload.title || payload.title.trim() === '') {
            setSubmitErrors(['件名']);
            setHasRequiredError(true);
            setApprovalStatus('error');
            return;
        }
        if (!(isEditMode && approvers.length === 0 && Array.isArray(estimate?.approval_flow) && estimate.approval_flow.length > 0)) {
            // 新規 or モーダルで承認者を設定した場合のみ明示送信
            payload.approval_flow = approvers;
        } else {
            // 既存フローあり・モーダル未編集 → サーバ側で既存フローをpendingリセット
            delete payload.approval_flow;
        }

        const options = {
            onStart: () => setApprovalStatus('submitting'),
            onSuccess: () => {
                setApprovalStatus('success');
                // 成功時もこのダイアログを開いたまま「申請取消」を出せるようにする
                setOpenApproval(true);
                setApprovalLocal(true); // 即座にフッターボタンを申請取消に切替
                setSubmitErrors([]);
                setHasRequiredError(false);
                setData('status', 'pending');
            },
            onError: (errors) => {
                console.error('承認申請エラー:', errors);
                const missingRequired = Object.keys(errors || {}).reduce((acc, key) => {
                    if (REQUIRED_FIELD_LABELS[key]) {
                        acc.add(REQUIRED_FIELD_LABELS[key]);
                    }
                    return acc;
                }, new Set());

                if (missingRequired.size > 0) {
                    setSubmitErrors(Array.from(missingRequired));
                    setHasRequiredError(true);
                } else {
                    const msgs = Object.values(errors || {}).map(e => String(e));
                    setSubmitErrors(msgs.length ? msgs : ['送信に失敗しました。入力内容をご確認ください。']);
                    setHasRequiredError(false);
                }

                setApprovalStatus('error');
            },
            preserveState: true,
            preserveScroll: true,
        };

        if (isEditMode) {
            // 明示 payload で送信
            router.patch(route('estimates.update', estimate.id), payload, options);
        } else {
            // Do not send estimate_number for new estimates, let backend generate it.
            delete payload.estimate_number;
            router.post(route('estimates.store'), payload, options);
        }
    };

    const cancelApproval = () => {
        if (!estimate?.id) return;
        if (!confirm('承認申請を取り消しますか？')) return;
        router.patch(route('estimates.cancel', estimate.id), {}, {
            onSuccess: () => {
                setApprovalStatus('');
                setOpenApproval(false);
                setApprovalLocal(false);
                setData('status', 'draft');
                setApprovers([]);
            },
            onError: (e) => {
                console.error('申請取消エラー:', e);
                alert('申請取消エラー: ' + JSON.stringify(e));
            },
            preserveState: false, 
            preserveScroll: true,
        });
    };

    // ダイアログを開いた時点で pending なら「申請取消」表示にする（再申請も統一）
    useEffect(() => {
        if (openApproval) {
            setApprovalStatus(isInApproval ? 'success' : '');
        }
    }, [openApproval, isInApproval]);

    useEffect(() => {
        setApprovalStatus(data.status === 'pending' ? 'success' : '');
        if (data.status !== 'pending') {
            setApprovalLocal(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estimate?.id]);

    const handleDelete = () => {
        if (!isEditMode) return;
        if (confirm('この見積書を削除しますか？この操作は取り消せません。')) {
            router.delete(route('estimates.destroy', estimate.id));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{isEditMode ? '見積書編集' : '見積書作成'}</h2>}
        >
            <Head title={isEditMode ? '見積書編集' : '見積書作成'} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="space-y-6 bg-white p-4 sm:p-8 shadow sm:rounded-lg">
                        <div className="flex justify-between items-center">
                            <h1 className="text-2xl font-bold">{isEditMode ? '見積書編集' : '見積書'}</h1>
                            <div className="flex items-center space-x-2">
                                <Switch id="view-mode" checked={isInternalView} onCheckedChange={setIsInternalView} />
                                <Label htmlFor="view-mode">{isInternalView ? '社内ビュー' : '社外ビュー'}</Label>
                            </div>
                        </div>

                        <ErrorBanner />

                        {/* 承認フロー概要は明細下の合計の隣に移動 */}

                        <Card>
                            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-3">
                                    <CardTitle className="flex items-center gap-2">
                                        <span>基本情報</span>
                                        {isEditMode && ['sent', 'approved'].includes(String(estimate?.status || '').toLowerCase()) && (
                                            <span className="inline-flex items-center rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white">
                                                承認済
                                            </span>
                                        )}
                                        {data.is_order_confirmed && (
                                            <span className="inline-flex items-center rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">
                                                受注確定
                                            </span>
                                        )}
                                    </CardTitle>
                                </div>
                                {isEditMode && estimate?.status === 'sent' && (
                                    <Button
                                        type="button"
                                        variant={data.is_order_confirmed ? 'destructive' : 'default'}
                                        onClick={() => triggerOrderConfirmation(data.is_order_confirmed ? 'cancel' : 'confirm')}
                                    >
                                        {data.is_order_confirmed ? '受注取消' : '受注確定'}
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="estimate-number">見積番号</Label>
                                    <div id="estimate-number" className="flex h-10 w-full items-center rounded-md border border-input bg-slate-100 px-3 py-2 text-sm text-muted-foreground" aria-readonly="true">
                                        {data.estimate_number || '（下書き保存時に自動採番）'}
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="customer">顧客名 <span className="text-red-500 ml-1">*</span></Label>
                                        <CustomerCombobox selectedCustomer={selectedCustomer} onCustomerChange={setSelectedCustomer} />
                                        {errors.customer_name && <p className="text-sm text-red-600 mt-1">{errors.customer_name}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="department">取引先部門 <span className="text-red-500 ml-1">*</span></Label>
                                        <DepartmentCombobox
                                            partnerId={selectedCustomer?.id || null}
                                            selectedDepartment={selectedDepartment}
                                            onDepartmentChange={setSelectedDepartment}
                                            initialDepartmentId={estimate?.mf_department_id || null}
                                        />
                                        {errors.mf_department_id && <p className="text-sm text-red-600 mt-1">{errors.mf_department_id}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="client-contact-title">担当者役職</Label>
                                        <Input
                                            id="client-contact-title"
                                            value={data.client_contact_title || ''}
                                            maxLength={35}
                                            onChange={(e) => {
                                                contactEditedRef.current.title = true;
                                                setData('client_contact_title', e.target.value.slice(0, 35));
                                            }}
                                            placeholder="部長"
                                        />
                                        {errors.client_contact_title && <p className="text-sm text-red-600 mt-1">{errors.client_contact_title}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="client-contact-name">担当者氏名</Label>
                                        <Input
                                            id="client-contact-name"
                                            value={data.client_contact_name || ''}
                                            maxLength={35}
                                            onChange={(e) => {
                                                contactEditedRef.current.name = true;
                                                setData('client_contact_name', e.target.value.slice(0, 35));
                                            }}
                                            placeholder="宮田愛子"
                                        />
                                        {errors.client_contact_name && <p className="text-sm text-red-600 mt-1">{errors.client_contact_name}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="project-name">件名 <span className="text-red-500 ml-1">*</span></Label>
                                        <Input id="project-name" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="新会計システム導入" />
                                        {errors.title && <p className="text-sm text-red-600 mt-1">{errors.title}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="staff">自社担当者 <span className="text-red-500 ml-1">*</span></Label>
                                        <StaffCombobox selectedStaff={selectedStaff} onStaffChange={setSelectedStaff} />
                                        {errors.staff_name && <p className="text-sm text-red-600 mt-1">{errors.staff_name}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="issue-date">発行日 <span className="text-red-500 ml-1">*</span></Label>
                                        <Input
                                            type="date"
                                            id="issue-date"
                                            value={data.issue_date}
                                            onChange={(e) => {
                                                const newIssueDate = e.target.value;
                                                setData((prevData) => {
                                                    const nextData = {
                                                        ...prevData,
                                                        issue_date: newIssueDate,
                                                    };

                                                    if (isCompleteDateInput(newIssueDate)) {
                                                        const issueDate = new Date(newIssueDate);
                                                        if (!Number.isNaN(issueDate.getTime())) {
                                                            const newDueDate = new Date(issueDate);
                                                            newDueDate.setDate(issueDate.getDate() + 30);
                                                            nextData.due_date = newDueDate.toISOString().slice(0, 10);
                                                        }
                                                    }

                                                    return nextData;
                                                });
                                            }}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="due-date">有効期間</Label>
                                        <Input type="date" id="due-date" value={data.due_date || ''} onChange={(e) => setData('due_date', e.target.value)} onBlur={handleDueDateBlur} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="delivery-date">納期</Label>
                                        <Input type="date" id="delivery-date" value={data.delivery_date || ''} onChange={(e) => setData('delivery_date', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="payment-terms">支払条件</Label>
                                        <Input id="payment-terms" defaultValue="月末締め翌月末払い" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="delivery-location">納入場所</Label>
                                        <Input id="delivery-location" value={data.delivery_location} onChange={(e) => setData('delivery_location', e.target.value)} placeholder="お客様指定の場所" />
                                    </div>
                                </div>
                                <div className="space-y-3 rounded-lg border border-dashed border-slate-200 bg-slate-50/70 p-4">
                                    <div className="flex flex-wrap items-center gap-2 justify-between">
                                        <div className="flex gap-2">
                                            <Button type="button" variant={requirementsMode === 'chat' ? 'default' : 'outline'} size="sm" onClick={() => setRequirementsMode('chat')}>要件整理チャット</Button>
                                            <Button type="button" variant={requirementsMode === 'manual' ? 'default' : 'outline'} size="sm" onClick={() => setRequirementsMode('manual')}>手動入力</Button>
                                        </div>
                                        <span className="text-xs text-muted-foreground">要件を整理し、AIドラフトに渡す情報です。</span>
                                    </div>
                                    {requirementsMode === 'manual' && (
                                    <div className="flex flex-col gap-3 md:flex-row md:items-start">
                                        <div className="flex-1 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor="requirement-summary" className="text-sm font-medium">要件概要（AIプロンプト）</Label>
                                                <span className="text-xs text-muted-foreground">システム開発のみ対象</span>
                                            </div>
                                            <Textarea
                                                id="requirement-summary"
                                                value={data.requirement_summary || ''}
                                                maxLength={4000}
                                                rows={4}
                                                onChange={(e) => setData('requirement_summary', e.target.value)}
                                                placeholder="例: Salesforce連携と新承認フローの実装。ユーザー50名、モバイル対応必須。非機能として可用性99.9%と監査ログが必要。"
                                            />
                                        </div>
                                    </div>
                                    )}
                                    {requirementsMode === 'chat' && isInternalView && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <Label className="text-sm font-medium">要件整理チャット（内部用）</Label>
                                                    <Button type="button" variant="outline" size="sm" onClick={loadChat} disabled={chatLoading || !isEditMode}>
                                                        再読込
                                                    </Button>
                                                </div>
                                                <div className="h-60 overflow-y-auto rounded border bg-white p-2 text-sm space-y-2">
                                                    {chatLoading && <p className="text-xs text-muted-foreground">読み込み中...</p>}
                                                    {!chatLoading && chatMessages.length === 0 && <p className="text-xs text-muted-foreground">メッセージはありません。</p>}
                                                    {chatMessages.map((m, idx) => (
                                                        <div key={m.id || idx} className={`rounded p-2 ${m.role === 'assistant' ? 'bg-slate-50 border' : ''}`}>
                                                            <p className="text-[11px] text-muted-foreground mb-1">{m.role === 'assistant' ? 'AI' : 'あなた'}</p>
                                                            <div className="whitespace-pre-wrap text-slate-800 text-sm">{m.content}</div>
                                                        </div>
                                                    ))}
                                                </div>
                                                        <div className="flex gap-2">
                            <Input
                                value={chatInput}
                                onChange={(e) => setChatInput(e.target.value)}
                                placeholder="不足情報や要望を入力"
                                disabled={chatLoading}
                            />
                            <Button type="button" onClick={sendChat} disabled={chatLoading}>送信</Button>
                        </div>
                        {!estimate?.id && (
                            <p className="text-xs text-muted-foreground">未保存の間はローカルで保持します（保存後はサーバに永続化されます）。</p>
                        )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-sm font-medium">AI整理結果プレビュー</Label>
                                                <div className="rounded border bg-white p-3 text-sm h-60 overflow-y-auto whitespace-pre-wrap">
                                                    {(() => {
                                                        const lastAssistant = [...chatMessages].reverse().find(m => m.role === 'assistant');
                                                        return lastAssistant ? lastAssistant.content : 'AI整理結果はまだありません。';
                                                    })()}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:gap-3">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={handleGenerateRequirementOverview}
                                                disabled={!canGenerateOverview || isStructuringRequirements}
                                            >
                                                {isStructuringRequirements ? '生成中...' : '要件概要を生成'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={handleGenerateAiDraft}
                                                disabled={!canGenerateDraft || isGeneratingAiDraft}
                                            >
                                                {isGeneratingAiDraft ? '生成中...' : 'AIでドラフト見積生成'}
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {requirementsMode === 'chat'
                                                ? '最新のAI整理結果をもとに要件概要と見積ドラフトを生成します。'
                                                : '要件概要の入力内容をもとに生成します。'}
                                        </p>
                                    </div>
                                    {structureError && (
                                        <p className="text-sm text-red-600">{structureError}</p>
                                    )}
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500">機能要件</p>
                                            {structuredRequirements.functional.length > 0 ? (
                                                <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    {structuredRequirements.functional.map((line, idx) => (
                                                        <li key={`fr-${idx}`}>{line}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="mt-1 text-xs text-muted-foreground">AI整理結果はまだありません。</p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500">非機能要件</p>
                                            {structuredRequirements.nonFunctional.length > 0 ? (
                                                <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    {structuredRequirements.nonFunctional.map((line, idx) => (
                                                        <li key={`nfr-${idx}`}>{line}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="mt-1 text-xs text-muted-foreground">AI整理結果はまだありません。</p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500">未確定要件</p>
                                            {structuredRequirements.unresolved.length > 0 ? (
                                                <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    {structuredRequirements.unresolved.map((line, idx) => (
                                                        <li key={`unresolved-${idx}`}>{line}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="mt-1 text-xs text-muted-foreground">AI整理結果はまだありません。</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        <Checkbox
                                            id="pm-support-required"
                                            checked={pmSupportRequired}
                                            onCheckedChange={(checked) => setPmSupportRequired(checked === true)}
                                        />
                                        <Label htmlFor="pm-support-required" className="text-sm">
                                            PM支援が必要（プロジェクト管理品目を必須挿入）
                                        </Label>
                                    </div>
                                    {aiDraftError && (
                                        <p className="text-sm text-red-600">{aiDraftError}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="external-remarks">備考（対外）</Label>
                                    <Textarea id="external-remarks" value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="お見積りの有効期限は発行後1ヶ月です。" />
                                </div>
                                {isInternalView && (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="notes-prompt">備考生成プロンプト</Label>
                                            <Textarea
                                                id="notes-prompt"
                                                value={notePrompt}
                                                onChange={(e) => setNotePrompt(e.target.value)}
                                                placeholder={notePromptPlaceholder}
                                            />
                                            {notePromptError && (
                                                <p className="text-sm text-red-600">{notePromptError}</p>
                                            )}
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={handleGenerateNotes}
                                                    disabled={isGeneratingNotes}
                                                >
                                                    {isGeneratingNotes ? '生成中...' : '備考生成'}
                                                </Button>
                                                <p className="text-xs text-slate-500">入力した内容に基づいて対外備考を提案します。</p>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="internal-remarks">備考（社内メモ）</Label>
                                            <Textarea id="internal-remarks" value={data.internal_memo} onChange={(e) => setData('internal_memo', e.target.value)} placeholder="値引きの背景について..." />
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-1">
                                    明細
                                    <span className="text-red-500 leading-none">*</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12"></TableHead>
                                            <TableHead>品目名</TableHead>
                                            <TableHead>詳細</TableHead>
                                            <TableHead className="text-right">数量</TableHead>
                                            <TableHead>単位</TableHead>
                                            <TableHead className="text-right">単価</TableHead>
                                            <TableHead className="text-right">金額</TableHead>
                                            <TableHead>税区分</TableHead>
                                            {isInternalView && <TableHead className="text-right">原価</TableHead>}
                                            {isInternalView && <TableHead className="text-right">原価金額</TableHead>}
                                            {isInternalView && <TableHead className="text-right">粗利</TableHead>}
                                            {isInternalView && <TableHead className="text-right">粗利率</TableHead>}
                                            <TableHead className="w-24"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lineItems.map(item => (
                                            <TableRow key={item.id}>
                                                <TableCell></TableCell>
                                                <TableCell>
                                                    <Select
                                                        key={`${item.id}-${item.product_id ?? 'none'}`}
                                                        value={item.product_id != null ? String(item.product_id) : undefined}
                                                        defaultValue={item.product_id != null ? String(item.product_id) : undefined}
                                                        onValueChange={(value) => handleProductSelect(item.id, value)}
                                                    >
                                                        <SelectTrigger className="w-[180px]">
                                                            <SelectValue placeholder="品目を選択" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {products.map(product => (
                                                                <SelectItem key={product.id} value={String(product.id)}>
                                                                    {product.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </TableCell>
                                                <TableCell>
                                                    <Textarea
                                                        className="mt-2 w-full min-w-[500px]"
                                                        placeholder="詳細（項目）"
                                                        value={item.description}
                                                        onChange={(e) => handleItemChange(item.id, 'description', e.target.value)}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Input
                                                        type="number"
                                                        step="0.1"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={item.qty}
                                                        onChange={(e) => handleItemChange(item.id, 'qty', e.target.value)}
                                                        className="w-20 text-right appearance-none"
                                                        onWheel={(e) => e.currentTarget.blur()}
                                                        onKeyDown={preventArrowKeyChange}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-2">
                                                        <Input
                                                            value={item.unit}
                                                            onChange={(e) => {
                                                                const val = e.target.value.slice(0, 3);
                                                                handleItemChange(item.id, 'unit', val);
                                                            }}
                                                            className="w-16"
                                                            maxLength="3"
                                                        />
                                                        <div className="flex items-center gap-2">
                                                            <Select
                                                                value={item.display_mode}
                                                                onValueChange={(value) => handleItemChange(item.id, 'display_mode', value)}
                                                            >
                                                                <SelectTrigger className="w-28 h-8 text-xs">
                                                                    <SelectValue placeholder="表示形式" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {displayModeOptions.map(option => (
                                                                        <SelectItem key={option.value} value={option.value}>
                                                                            {option.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        {item.display_mode === 'lump' && (
                                                            <div className="flex items-center gap-2 text-xs">
                                                                <Input
                                                                    type="number"
                                                                    step="0.1"
                                                                    className="w-16 text-right"
                                                                    value={item.display_qty}
                                                                    min="0"
                                                                    inputMode="decimal"
                                                                    onChange={(e) => handleItemChange(item.id, 'display_qty', e.target.value)}
                                                                    onKeyDown={preventArrowKeyChange}
                                                                />
                                                                <Input
                                                                    value={item.display_unit}
                                                                    onChange={(e) => handleItemChange(item.id, 'display_unit', e.target.value.slice(0, 3))}
                                                                    className="w-16"
                                                                    maxLength="3"
                                                                />
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Input
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={item.price}
                                                        onChange={(e) => handleItemChange(item.id, 'price', e.target.value)}
                                                        className="w-24 text-right appearance-none"
                                                        onWheel={(e) => e.currentTarget.blur()}
                                                        onKeyDown={preventArrowKeyChange}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {calculateAmount(item).toLocaleString()}
                                                </TableCell>
                                                <TableCell>
                                                    <Select
                                                        value={item.tax_category || 'standard'}
                                                        onValueChange={(value) => handleItemChange(item.id, 'tax_category', value)}
                                                    >
                                                        <SelectTrigger className="w-[120px]">
                                                            <SelectValue placeholder="税区分" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="standard">標準</SelectItem>
                                                            <SelectItem value="reduced">軽減</SelectItem>
                                                            <SelectItem value="exempt">非課税</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </TableCell>
                                                {isInternalView && (
                                                    <TableCell className="text-right text-gray-500">
                                                    <Input
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={item.cost}
                                                        onChange={(e) => handleItemChange(item.id, 'cost', e.target.value)}
                                                        className="w-24 text-right appearance-none"
                                                        onWheel={(e) => e.currentTarget.blur()}
                                                        onKeyDown={preventArrowKeyChange}
                                                    />
                                                </TableCell>
                                                )}
                                                {isInternalView && <TableCell className="text-right text-gray-500">{calculateCostAmount(item).toLocaleString()}</TableCell>}
                                                {isInternalView && <TableCell className="text-right text-gray-500">{calculateGrossProfit(item).toLocaleString()}</TableCell>}
                                                {isInternalView && <TableCell className="text-right text-gray-500">{calculateGrossMargin(item).toFixed(1)}%</TableCell>}
                                                <TableCell className="flex items-center justify-center space-x-1">
                                                    <Button variant="ghost" size="icon"><ArrowUp className="h-4 w-4" /></Button>
                                                    <Button variant="ghost" size="icon"><ArrowDown className="h-4 w-4" /></Button>
                                                    <Button variant="ghost" size="icon" onClick={() => removeLineItem(item.id)}><Trash2 className="h-4 w-4" /></Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="mt-4 flex items-center space-x-2">
                                    <Button type="button" variant="outline" size="sm" onClick={addLineItem}><PlusCircle className="mr-2 h-4 w-4" />行を追加</Button>
                                </div>
                                {errors.items && <p className="text-sm text-red-600 mt-2">{errors.items}</p>}
                            </CardContent>
                        </Card>

                        <div className="space-y-6">
                            <div className="flex flex-col lg:flex-row gap-4 lg:gap-6">
                                {isInternalView && (
                                    <div className="w-full lg:w-2/3">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>品目別集計</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-3">
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    {[
                                                        { key: 'type1', label: '第1種品目', ...categoryTotals.type1 },
                                                        { key: 'type5', label: '第5種品目', ...categoryTotals.type5 },
                                                    ].map((row) => {
                                                        const gross = row.revenue - row.cost;
                                                        const rate = calcRate(row.revenue, row.cost);
                                                        return (
                                                            <div key={row.key} className="rounded border bg-slate-50 p-3">
                                                                <p className="text-xs text-slate-500 mb-1">{row.label}</p>
                                                                <div className="flex justify-between text-sm"><span>売上</span><span>{row.revenue.toLocaleString()}円</span></div>
                                                                <div className="flex justify-between text-sm text-slate-600"><span>原価</span><span>{row.cost.toLocaleString()}円</span></div>
                                                                <div className="flex justify-between text-sm font-semibold"><span>粗利益</span><span>{gross.toLocaleString()}円</span></div>
                                                                <div className={`flex justify-between text-sm font-bold ${rate < 20 ? 'text-red-500' : 'text-green-600'}`}>
                                                                    <span>粗利率</span><span>{rate.toFixed(1)}%</span>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}
                                {/* 合計（右） */}
                                <div className="w-full lg:w-1/3 lg:ml-auto">
                                    <Card>

                                        <CardHeader>
                                            <CardTitle>合計</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            <div className="flex justify-between"><span>小計（税抜）</span><span>{subtotal.toLocaleString()}円</span></div>
                                            <div className="flex justify-between"><span>消費税 (10%)</span><span>{tax.toLocaleString()}円</span></div>
                                            <div className="flex justify-between font-bold text-lg"><span>合計金額</span><span>{total.toLocaleString()}円</span></div>
                                            {isInternalView && (
                                                <div className="border-t pt-2 mt-2 space-y-2">
                                                    <div className="flex justify-between text-muted-foreground"><span>合計原価</span><span>{totalCost.toLocaleString()}円</span></div>
                                                    <div className="flex justify-between text-muted-foreground"><span>粗利益</span><span>{totalGrossProfit.toLocaleString()}円</span></div>
                                                    <div className={`flex justify-between font-bold ${totalGrossMargin < 20 ? 'text-red-500' : 'text-green-600'}`}>
                                                        <span>粗利率</span>
                                                        <span>{totalGrossMargin.toFixed(1)}%</span>
                                                    </div>
                                                    {totalGrossMargin < 20 && <p className="text-red-500 text-sm text-center">粗利率が基準値を下回っています。</p>}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                            {/* 承認フロー概要（1段下に） */}
                            {isInternalView && (
                                <div className="w-full">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>承認フロー</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {(() => {
                                                const baseFlow = Array.isArray(estimate?.approval_flow) && estimate.approval_flow.length
                                                    ? estimate.approval_flow
                                                    : approvers;
                                                if (!Array.isArray(baseFlow) || baseFlow.length === 0) {
                                                    return (
                                                        <p className="text-sm text-slate-500">承認者が設定されていません。「承認申請」で追加してください。</p>
                                                    );
                                                }
                                                let currentIdx = -1;
                                                for (let i = 0; i < baseFlow.length; i++) {
                                                    const status = baseFlow[i].status ?? (baseFlow[i].approved_at ? 'approved' : 'pending');
                                                    if (status !== 'approved' && status !== 'rejected') { currentIdx = i; break; }
                                                }
                                                const derived = baseFlow.map((s, idx) => {
                                                    const statusValue = s.status ?? (s.approved_at ? 'approved' : 'pending');
                                                    const isApproved = statusValue === 'approved';
                                                    const isRejected = statusValue === 'rejected';
                                                    const isCurrent = !isApproved && !isRejected && idx === currentIdx;
                                                    return {
                                                        id: s.id,
                                                        name: s.name,
                                                        approved_at: s.approved_at || '',
                                                        status: isRejected ? '却下' : (isApproved ? '承認済' : (isCurrent ? '未承認' : '待機中')),
                                                        rejection_reason: isRejected ? (s.rejection_reason ?? '') : '',
                                                        date: isRejected
                                                            ? (s.rejected_at ? new Date(s.rejected_at).toLocaleDateString('ja-JP') : '')
                                                            : (isApproved && s.approved_at ? new Date(s.approved_at).toLocaleDateString('ja-JP') : ''),
                                                        order: idx + 1,
                                                    };
                                                });
                                                return (
                                                    <ol className="space-y-2 list-decimal list-inside">
                                                        {derived.map(step => (
                                                            <li key={`${step.id}-${step.order}`} className="flex flex-col gap-1">
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">{step.order}</span>
                                                                        <div>
                                                                            <span className="font-medium">{step.name}</span>
                                                                            {step.rejection_reason && (
                                                                                <p className="text-xs text-red-600">理由: {step.rejection_reason}</p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="flex items-center gap-3">
                                                                        <span className={`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded ${
                                                                            step.status==='承認済'
                                                                                ? 'bg-green-100 text-green-800'
                                                                                : step.status==='却下'
                                                                                    ? 'bg-red-100 text-red-800'
                                                                                    : 'bg-amber-100 text-amber-800'
                                                                        }`}>
                                                                            {step.status}
                                                                        </span>
                                                                        {step.date && (
                                                                            <span className="text-xs text-slate-500">{step.date}</span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        ))}
                                                    </ol>
                                                );
                                            })()}
                                        </CardContent>
                                    </Card>
                                </div>
                            )}
                            <div>
                                {isInternalView && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>原価・粗利分析（社内用）</CardTitle>
                                        </CardHeader>
                                        <CardContent className="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                                            <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div className="w-full flex justify-center items-center relative h-48">
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
                                                <div className="w-full flex justify-center items-center relative h-48">
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
                                            <div className="lg:col-span-2 space-y-2">
                                                <p className="font-bold mb-2">品目名 粗利金額（原価金額）</p>
                                                {Object.entries(groupedAnalysisData).map(([itemName, data], idx) => (
                                                    <p key={itemName} className="flex justify-between">
                                                        <span className="flex items-center">
                                                            <span
                                                                aria-hidden
                                                                className="inline-block w-3 h-3 rounded-sm mr-2"
                                                                style={{ backgroundColor: COLORS[idx % COLORS.length] }}
                                                            />
                                                            {itemName}:
                                                        </span>
                                                        <span>{data.grossProfit.toLocaleString()}円（{data.cost.toLocaleString()}円）</span>
                                                    </p>
                                                ))}
                                                <div className="border-t pt-2 mt-2">
                                                    <p className="flex justify-between font-bold">
                                                        <span>合計:</span>
                                                        <span>{totalGrossProfit.toLocaleString()}円（{totalCost.toLocaleString()}円）</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                                {isInternalView && effortChartData.length > 0 && (
                                    <Card className="mt-6">
                                        <CardHeader>
                                            <CardTitle>工数明細（社内用）</CardTitle>
                                        </CardHeader>
                                        <CardContent className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            <div className="h-64">
                                                <ResponsiveContainer width="100%" height="100%">
                                                    <BarChart data={effortChartData}>
                                                        <XAxis dataKey="name" hide />
                                                        <Tooltip formatter={(value) => `${value.toFixed(1)} 工数`} />
                                                        <Bar dataKey="value" fill="#6366f1" radius={[4, 4, 0, 0]} />
                                                    </BarChart>
                                                </ResponsiveContainer>
                                                <div className="flex justify-center mt-2 text-sm text-muted-foreground">
                                                    項目別人日
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <p className="font-bold">工数一覧</p>
                                                {effortChartData.map((entry, idx) => (
                                                    <p key={entry.name} className="flex justify-between">
                                                        <span className="flex items-center">
                                                            <span
                                                                aria-hidden
                                                                className="inline-block w-3 h-3 rounded-sm mr-2"
                                                                style={{ backgroundColor: COLORS[idx % COLORS.length] }}
                                                            />
                                                            {entry.name}
                                                        </span>
                                                        <span>{entry.value.toFixed(1)} 人日</span>
                                                    </p>
                                                ))}
                                                <div className="border-t pt-2 mt-2 flex justify-between font-semibold">
                                                    <span>合計</span>
                                                    <span>{totalEffort.toFixed(1)} 人日</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>
                    </form>

                    <div className="mt-6">
                        <Card>
                            <CardFooter className="flex justify-between items-center py-4">
                                <div>
                                </div>
                                <div className="space-x-2">
                                    {isEditMode && is_fully_approved && estimate.client_id && !estimate.mf_quote_id && (
                                        <Button type="button" onClick={() => setOpenIssueMFQuoteConfirm(true)}>
                                            マネーフォワードで見積書発行
                                        </Button>
                                    )}

                                    

                                    {isEditMode && estimate.mf_quote_id && (
                                        <Button type="button" onClick={() => window.location.href = route('estimates.viewQuote.start', { estimate: estimate.id })}>
                                            PDFダウンロード
                                        </Button>
                                    )}

                                    {isEditMode && is_fully_approved && (
                                        <>
                                            <Button
                                                type="button"
                                                onClick={() => window.open(route('estimates.purchaseOrder.preview', { estimate: estimate.id }), '_blank')}
                                            >
                                                注文書を印刷
                                            </Button>
                                            <Button type="button" onClick={() => router.post(route('invoices.fromEstimate', { estimate: estimate.id }))}>
                                                自社請求書に変換
                                            </Button>
                                        </>
                                    )}

                                    {isEditMode && estimate.mf_invoice_pdf_url && (
                                        <a href={estimate.mf_invoice_pdf_url} target="_blank" rel="noopener noreferrer">
                                            <Button type="button">
                                                請求書を確認
                                            </Button>
                                        </a>
                                    )}

                                    {isEditMode && (
                                        <Button type="button" variant="destructive" onClick={handleDelete}>削除</Button>
                                    )}
                                    {data.status === 'draft' && (
                                        <Button type="button" variant="secondary" onClick={saveDraft}>下書き保存</Button>
                                    )}
                                    {/* プレビューは廃止（MFのPDF表示に統一） */}
                                    {isInApproval ? (
                                        <Button type="button" variant="destructive" onClick={cancelApproval}>申請取消</Button>
                                    ) : (
                                        <Button type="button" onClick={handleSubmit}>{isEditMode ? '更新して申請' : '承認申請'}</Button>
                                    )}
                                </div>
                            </CardFooter>
                        </Card>
                    </div>
                    <Dialog open={aiPreviewOpen} onOpenChange={setAiPreviewOpen}>
                        <DialogContent className="max-w-3xl">
                            <DialogHeader>
                                <DialogTitle>AIドラフトを確認</DialogTitle>
                                <DialogDescription>提案された明細を確認して追加・置換を選択してください。</DialogDescription>
                            </DialogHeader>
                            <div className="max-h-80 overflow-y-auto rounded border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>品目</TableHead>
                                            <TableHead>説明</TableHead>
                                            <TableHead className="text-right">数量(人日)</TableHead>
                                            <TableHead className="text-right">単価</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {aiDraftPreview.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-center text-sm text-muted-foreground">
                                                    AIの明細候補がありません。
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {aiDraftPreview.map((item, idx) => (
                                            <TableRow key={`ai-${idx}`}>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell className="text-sm">{item.description}</TableCell>
                                                <TableCell className="text-right">
                                                    {Number.isFinite(Number(item.qty)) ? Number(item.qty).toFixed(2) : '-'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {Number.isFinite(Number(item.price))
                                                        ? `¥${Number(item.price || 0).toLocaleString()}`
                                                        : '-'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                            {aiNotesSuggestion && (
                                <div className="space-y-2 rounded border bg-slate-100 p-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <p className="font-semibold">AI備考案</p>
                                        <Button type="button" variant="outline" size="sm" onClick={handleAdoptAiNotes}>
                                            備考に反映
                                        </Button>
                                    </div>
                                    <p className="whitespace-pre-wrap text-slate-700">{aiNotesSuggestion}</p>
                                </div>
                            )}
                            <DialogFooter className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => applyAiDraft('append')}
                                    disabled={aiDraftPreview.length === 0}
                                >
                                    末尾に追加
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => applyAiDraft('replace')}
                                    disabled={aiDraftPreview.length === 0}
                                >
                                    すべて置き換え
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <Dialog open={openApproval} onOpenChange={setOpenApproval}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>承認申請</DialogTitle>
                                <DialogDescription>承認者を追加し、順序を調整してください。上から順に承認されます。</DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <ApproverPicker onAdd={(u) => setApprovers(prev => [...prev, { id: u.id, name: u.name }])} />
                                <div className="space-y-2">
                                    {approvers.length === 0 && (
                                        <p className="text-sm text-slate-500">承認者が追加されていません。</p>
                                    )}
                                    {approvers.map((ap, idx) => (
                                        <div key={`${ap.id}-${idx}`} className="flex items-center justify-between rounded border p-2">
                                            <div className="flex items-center gap-2">
                                                <span className="text-slate-500 text-sm">{idx + 1}</span>
                                                <span className="font-medium">{ap.name}</span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button type="button" variant="outline" size="icon" onClick={() => idx>0 && setApprovers(prev => {
                                                    const a=[...prev]; const t=a[idx-1]; a[idx-1]=a[idx]; a[idx]=t; return a;})}>
                                                    <ArrowUp className="h-4 w-4" />
                                                </Button>
                                                <Button type="button" variant="outline" size="icon" onClick={() => idx<approvers.length-1 && setApprovers(prev => {
                                                    const a=[...prev]; const t=a[idx+1]; a[idx+1]=a[idx]; a[idx]=t; return a;})}>
                                                    <ArrowDown className="h-4 w-4" />
                                                </Button>
                                                <Button type="button" variant="outline" size="icon" onClick={() => setApprovers(prev => prev.filter((_,i)=>i!==idx))}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <DialogFooter className="flex flex-col items-start gap-3 sm:flex-col w-full">
                                { (isInApproval || approvalStatus === 'success') && (
                                    <span className="inline-flex items-center rounded px-2 py-1 text-xs font-medium bg-red-600 text-white">承認申請を開始しました</span>
                                )}
                                {approvalStatus === 'error' && (
                                    <div className="w-full rounded border border-red-600 bg-red-50 px-4 py-3 text-sm text-red-900">
                                        <span className="font-semibold block mb-1">
                                            {hasRequiredError ? '必須項目が未入力です。' : '粗利率が低いため承認条件を満たしてください。'}
                                        </span>
                                        {submitErrors.length > 0 && (
                                            <ul className="list-disc space-y-1 pl-5 text-xs text-red-900">
                                                {submitErrors.map((msg, idx) => (
                                                    <li key={`submit-error-${idx}`}>
                                                        {hasRequiredError ? `${msg}` : msg}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                                <div className="flex items-center gap-2 w-full">
                                    <Button variant="secondary" type="button" onClick={() => { setOpenApproval(false); setApprovalStatus(''); }}>キャンセル</Button>
                                    {!isInApproval ? (
                                        <Button type="button" onClick={submitWithApprovers} disabled={approvalStatus === 'submitting'}>
                                            {approvalStatus === 'submitting' ? '申請中..' : '申請する'}
                                        </Button>
                                    ) : (
                                        <Button type="button" variant="destructive" onClick={cancelApproval}>
                                            申請取消
                                        </Button>
                                    )}
                                </div>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    {/* 承認開始通知モーダル（ローカル表示） */}
                    <Dialog open={openApprovalStarted} onOpenChange={setOpenApprovalStarted}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>承認申請を開始しました</DialogTitle>
                                <DialogDescription>以下の承認フローで申請を受け付けました。</DialogDescription>
                            </DialogHeader>
                            <div className="space-y-2">
                                {approvers.length ? (
                                    <ol className="list-decimal list-inside text-slate-700">
                                        {approvers.map((u, i) => (
                                            <li key={`${u.id}-${i}`}>{u.name}</li>
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
                    <Dialog open={orderConfirmDialogOpen} onOpenChange={setOrderConfirmDialogOpen}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{orderConfirmMode === 'confirm' ? '受注を確定しますか？' : '受注確定を解除しますか？'}</DialogTitle>
                                <DialogDescription>
                                    {orderConfirmMode === 'confirm'
                                        ? '承認済みの見積を実績として計上します。'
                                        : 'この見積の受注確定を解除します。'}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="flex items-center gap-2">
                                <Button type="button" variant="secondary" onClick={() => setOrderConfirmDialogOpen(false)}>
                                    いいえ
                                </Button>
                                <Button
                                    type="button"
                                    variant={orderConfirmMode === 'confirm' ? 'default' : 'destructive'}
                                    onClick={submitOrderConfirmation}
                                >
                                    はい
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* マネーフォワードで見積書発行 確認モーダル */}
                    <Dialog open={openIssueMFQuoteConfirm} onOpenChange={setOpenIssueMFQuoteConfirm}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>マネーフォワードで見積書発行</DialogTitle>
                                <DialogDescription>この見積からマネーフォワードで見積書を発行しますか？</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="secondary" onClick={() => setOpenIssueMFQuoteConfirm(false)}>キャンセル</Button>
                                <Button onClick={handleIssueMFQuote}>発行する</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* 請求書に変換 確認モーダル */}
                    <Dialog open={openConvertToInvoiceConfirm} onOpenChange={setOpenConvertToInvoiceConfirm}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>請求書に変換</DialogTitle>
                                <DialogDescription>この見積をマネーフォワードで請求書に変換しますか？</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="secondary" onClick={() => setOpenConvertToInvoiceConfirm(false)}>キャンセル</Button>
                                <Button onClick={handleConvertToInvoice}>変換する</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ApproverPicker({ onAdd }) {
    const [open, setOpen] = useState(false);
    const [staff, setStaff] = useState([]);
    const [search, setSearch] = useState("");

    useEffect(() => {
        const fetchStaff = async () => {
            try {
                const res = await axios.get('/api/users', { params: { search } });
                setStaff(Array.isArray(res.data) ? res.data : []);
            } catch (e) {
                console.error('Failed to fetch staff:', e);
                setStaff([]);
            }
        };
        fetchStaff();
    }, [search]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" className="justify-between w-full">承認者を追加…</Button>
            </PopoverTrigger>
            <PopoverContent className="w-full p-0">
                <Command>
                    <CommandInput placeholder="承認者を検索..." onValueChange={setSearch} />
                    <CommandEmpty>見つかりません。</CommandEmpty>
                    <CommandGroup>
                        {staff.map((s) => (
                            <CommandItem
                                key={s.id}
                                value={`${s.name ?? ''} ${s.id ?? ''}`}
                                onSelect={() => { onAdd(s); setOpen(false); }}
                            >
                                {s.name}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
