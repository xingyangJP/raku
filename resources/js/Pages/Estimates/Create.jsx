import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import { PlusCircle, MinusCircle, Trash2, ArrowUp, ArrowDown, Copy, FileText, Eye, History, Check, ChevronsUpDown } from 'lucide-react';
import { cn } from "@/lib/utils"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from "@/Components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover"
import axios from 'axios';

// --- Components defined outside EstimateCreate to prevent state loss on re-render ---

function CustomerCombobox({ selectedCustomer, onCustomerChange }) {
    const [open, setOpen] = useState(false);
    const [customers, setCustomers] = useState([]);
    const [search, setSearch] = useState("");

    useEffect(() => {
        const fetchCustomers = async () => {
            try {
                const response = await axios.get(`/api/customers?search=${search}`);
                setCustomers(response.data);
            } catch (error) {
                console.error("Failed to fetch customers:", error);
            }
        };
        fetchCustomers();
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
                    {selectedCustomer
                        ? selectedCustomer.customer_name
                        : "顧客を選択..."}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-full p-0">
                <Command>
                    <CommandInput placeholder="顧客を検索..." onValueChange={setSearch} />
                    <CommandEmpty>顧客が見つかりません。</CommandEmpty>
                    <CommandGroup>
                        {customers.map((customer) => (
                            <CommandItem
                                key={customer.id}
                                value={customer.id}
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
                </Command>
            </PopoverContent>
        </Popover>
    )
}

function StaffCombobox({ selectedStaff, onStaffChange }) {
    const [open, setOpen] = useState(false);
    const [staff, setStaff] = useState([]);
    const [search, setSearch] = useState("");

    useEffect(() => {
        const fetchStaff = async () => {
            try {
                const response = await axios.get(`/api/users?search=${search}`);
                setStaff(response.data);
            } catch (error) {
                console.error("Failed to fetch staff:", error);
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
                                value={s.id}
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

export default function EstimateCreate({ auth, products, estimate = null }) {
    const isEditMode = estimate !== null;

    const [isInternalView, setIsInternalView] = useState(true);
    const [lineItems, setLineItems] = useState(estimate?.items || []);
    const [selectedStaff, setSelectedStaff] = useState(null); // In edit mode, you might need to fetch the staff member based on estimate.staff_id
    const [selectedCustomer, setSelectedCustomer] = useState(estimate ? { customer_name: estimate.customer_name, id: null } : null); // Simplified for now
    const [estimateNumber, setEstimateNumber] = useState(estimate?.estimate_number || '');

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        customer_name: estimate?.customer_name || '',
        title: estimate?.title || '',
        issue_date: estimate?.issue_date || '',
        due_date: estimate?.due_date || '',
        total_amount: estimate?.total_amount || 0,
        tax_amount: estimate?.tax_amount || 0,
        notes: estimate?.notes || '',
        items: estimate?.items || [],
        estimate_number: estimate?.estimate_number || '',
    });

    useEffect(() => {
        if (!isEditMode && selectedStaff) {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yy = String(today.getFullYear()).slice(-2);
            setEstimateNumber(`EST-${selectedStaff.id}-${dd}${mm}${yy}`);
        } else if (!selectedStaff) {
            setEstimateNumber(estimate?.estimate_number || '');
        }
    }, [selectedStaff, isEditMode, estimate]);

    useEffect(() => {
        setData('items', lineItems);
    }, [lineItems]);

    useEffect(() => {
        setData('customer_name', selectedCustomer?.customer_name || '');
    }, [selectedCustomer]);

    // These useEffects cause issues in edit mode by overwriting data. 
    // It's better to manage form state directly with setData in onChange handlers.
    // For simplicity, we will bind the inputs directly to useForm's data object.

    const calculateAmount = (item) => item.qty * item.price;
    const calculateCostAmount = (item) => item.qty * item.cost;
    const calculateGrossProfit = (item) => calculateAmount(item) - calculateCostAmount(item);
    const calculateGrossMargin = (item) => {
        const amount = calculateAmount(item);
        return amount !== 0 ? (calculateGrossProfit(item) / amount) * 100 : 0;
    };

    const subtotal = lineItems.reduce((acc, item) => acc + calculateAmount(item), 0);
    const totalCost = lineItems.reduce((acc, item) => acc + calculateCostAmount(item), 0);
    const totalGrossProfit = subtotal - totalCost;
    const totalGrossMargin = subtotal !== 0 ? (totalGrossProfit / subtotal) * 100 : 0;
    const tax = subtotal * 0.1;
    const total = subtotal + tax;

    useEffect(() => {
        setData(prevData => ({ ...prevData, items: lineItems, total_amount: total, tax_amount: tax, estimate_number: estimateNumber }));
    }, [lineItems, total, tax, estimateNumber]);


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
            }
        ]);
    };

    const removeLineItem = (id) => {
        setLineItems(prevItems => prevItems.filter(item => item.id !== id));
    };

    const handleItemChange = (id, field, value) => {
        setLineItems(prevItems =>
            prevItems.map(item =>
                item.id === id ? { ...item, [field]: value } : item
            )
        );
    };

    const handleProductSelect = (itemId, productId) => {
        const selectedProduct = products.find(p => p.id === parseInt(productId));
        if (selectedProduct) {
            setLineItems(prevItems => prevItems.map(item => 
                item.id === itemId ? { ...item, name: selectedProduct.name, price: selectedProduct.price, cost: selectedProduct.cost, product_id: selectedProduct.id } : item
            ));
        }
    };

    const groupedAnalysisData = lineItems.reduce((acc, item) => {
        const itemName = item.name;
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

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#AF19FF', '#FF1919', '#19FFD4', '#FF19B8', '#8884d8', '#82ca9d', '#a4de6c', '#d0ed57', '#ffc658', '#ff7300', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];

    const saveDraft = () => {
        // The saveDraft controller endpoint can handle both creation and update based on estimate_number
        post(route('estimates.saveDraft'), {
            ...data, // Send all form data
            status: 'draft',
            onSuccess: () => alert('下書きが保存されました。'),
            onError: (e) => console.error('下書き保存エラー:', e),
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditMode) {
            patch(route('estimates.update', estimate.id), {
                ...data,
                onSuccess: () => alert('見積書が更新されました。'),
                onError: (e) => console.error('更新エラー:', e),
            });
        } else {
            post(route('estimates.store'), {
                ...data,
                onSuccess: () => alert('見積書が承認申請されました。'),
                onError: (e) => console.error('承認申請エラー:', e),
            });
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

                        <Card>
                            <CardHeader>
                                <CardTitle>基本情報</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="customer">顧客名</Label>
                                    <CustomerCombobox selectedCustomer={selectedCustomer} onCustomerChange={setSelectedCustomer} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="staff">自社担当者</Label>
                                    <StaffCombobox selectedStaff={selectedStaff} onStaffChange={setSelectedStaff} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="project-name">件名</Label>
                                    <Input id="project-name" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="新会計システム導入" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="estimate-number">見積番号</Label>
                                    <Input id="estimate-number" value={data.estimate_number} onChange={(e) => setData('estimate_number', e.target.value)} readOnly={!isEditMode && selectedStaff} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="issue-date">発行日</Label>
                                    <Input type="date" id="issue-date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="expiry-date">有効期限</Label>
                                    <Input type="date" id="expiry-date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="payment-terms">支払条件</Label>
                                    <Input id="payment-terms" defaultValue="月末締め翌月末払い" />
                                </div>
                                <div className="lg:col-span-2 space-y-2">
                                    <Label htmlFor="delivery-location">納入場所</Label>
                                    <Input id="delivery-location" placeholder="お客様指定の場所" />
                                </div>
                                <div className="lg:col-span-2 space-y-2">
                                    <Label htmlFor="external-remarks">備考（対外）</Label>
                                    <Textarea id="external-remarks" value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="お見積りの有効期限は発行後1ヶ月です。" />
                                </div>
                                {isInternalView && (
                                    <div className="lg:col-span-2 space-y-2">
                                        <Label htmlFor="internal-remarks">備考（社内メモ）</Label>
                                        <Textarea id="internal-remarks" placeholder="値引きの背景について..." />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>明細</CardTitle>
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
                                                <TableCell><Checkbox /></TableCell>
                                                <TableCell>
                                                    <Select
                                                        value={item.product_id ? String(item.product_id) : ''}
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
                                                        value={item.qty}
                                                        onChange={(e) => {
                                                            const val = parseInt(e.target.value) || 0;
                                                            if (val <= 999) {
                                                                handleItemChange(item.id, 'qty', val);
                                                            }
                                                        }}
                                                        className="w-16 text-right"
                                                        max="999"
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        value={item.unit}
                                                        onChange={(e) => {
                                                            const val = e.target.value.slice(0, 3);
                                                            handleItemChange(item.id, 'unit', val);
                                                        }}
                                                        className="w-12"
                                                        maxLength="3"
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Input
                                                        type="number"
                                                        value={item.price}
                                                        onChange={(e) => handleItemChange(item.id, 'price', parseInt(e.target.value) || 0)}
                                                        className="w-24 text-right"
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {calculateAmount(item).toLocaleString()}
                                                </TableCell>
                                                {isInternalView && (
                                                    <TableCell className="text-right text-gray-500">
                                                        <Input
                                                            type="number"
                                                            value={item.cost}
                                                            onChange={(e) => handleItemChange(item.id, 'cost', parseInt(e.target.value) || 0)}
                                                            className="w-24 text-right"
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
                                    <Button type="button" variant="outline" size="sm"><Copy className="mr-2 h-4 w-4" />選択行を複製</Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="space-y-6">
                            <div className="flex justify-end">
                                <div className="w-full lg:w-1/3">
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
                            <div>
                                {isInternalView && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>原価・粗利分析（社内用）</CardTitle>
                                        </CardHeader>
                                        <CardContent className="flex flex-col lg:flex-row justify-between items-center">
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
                                            <div className="w-full lg:w-1/2 space-y-2 mt-4 lg:mt-0">
                                                <p className="font-bold mb-2">品目名 粗利金額（原価金額）</p>
                                                {Object.entries(groupedAnalysisData).map(([itemName, data]) => (
                                                    <p key={itemName} className="flex justify-between">
                                                        <span>{itemName}:</span>
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
                            </div>
                        </div>
                    </form>

                    <div className="mt-6">
                        <Card>
                            <CardFooter className="flex justify-between items-center py-4">
                                <div>
                                    <Button variant="outline"><History className="mr-2 h-4 w-4" />変更履歴</Button>
                                </div>
                                <div className="space-x-2">
                                    <Button variant="secondary" onClick={saveDraft}>下書き保存</Button>
                                    <Button onClick={handleSubmit}>{isEditMode ? '更新して申請' : '承認申請'}</Button>
                                </div>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}