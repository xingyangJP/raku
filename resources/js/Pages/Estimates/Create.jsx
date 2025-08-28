import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
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
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

export default function EstimateCreate({ auth, products }) { // products を props に追加
    const [isInternalView, setIsInternalView] = useState(true);
    const [lineItems, setLineItems] = useState([]); // lineItems を useState で管理
    const [selectedStaffId, setSelectedStaffId] = useState(null);
    const [estimateNumber, setEstimateNumber] = useState('');

    useEffect(() => {
        if (selectedStaffId) {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0'); // January is 0!
            const yy = String(today.getFullYear()).slice(-2);
            setEstimateNumber(`EST-${selectedStaffId}-${dd}${mm}${yy}`);
        } else {
            setEstimateNumber(''); // Clear if no staff selected
        }
    }, [selectedStaffId]);

    const estimateContentRef = useRef(null);

    const companyInfo = {
        name: '熊本コンピュータソフト株式会社',
        address: '〒862-0976 熊本県熊本市中央区九品寺５丁目８−９',
        website: 'www.k-cs.co.jp',
        tel: '096-371-1400',
        fax: '096-371-1404',
    };

    const handlePdfPreview = async () => {
        if (!estimateContentRef.current) return;

        const canvas = await html2canvas(estimateContentRef.current, { scale: 2 });
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
        const imgHeight = canvas.height * imgWidth / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        // Add company info
        pdf.setFontSize(10);
        pdf.text(companyInfo.name, 10, 10);
        pdf.text(companyInfo.address, 10, 15);
        pdf.text(`Tel: ${companyInfo.tel} Fax: ${companyInfo.fax}`, 10, 20);
        pdf.text(companyInfo.website, 10, 25);

        // Add inkan.gif
        const inkanImg = new Image();
        inkanImg.src = '/inkan.gif'; // Assuming inkan.gif is in the public directory or accessible path
        inkanImg.onload = () => {
            // Adjust position and size as needed
            pdf.addImage(inkanImg, 'GIF', 150, 20, 30, 30); // x, y, width, height
            pdf.save('estimate-preview.pdf');
        };
        inkanImg.onerror = (err) => {
            console.error("Failed to load inkan.gif", err);
            pdf.save('estimate-preview.pdf'); // Save even if image fails
        };
    };

    // 明細行を追加する関数
    const addLineItem = () => {
        setLineItems(prevItems => [
            ...prevItems,
            {
                id: Date.now(), // ユニークなIDを生成
                product_id: null, // 選択された商品ID
                name: '', // 品目名（マスターから、または自由入力）
                description: '', // 自由入力
                qty: 1,
                unit: '式', // デフォルト単位
                price: 0,
                cost: 0, // 原価
            }
        ]);
    };

    // 明細行を削除する関数
    const removeLineItem = (id) => {
        setLineItems(prevItems => prevItems.filter(item => item.id !== id));
    };

    // 明細行の値を更新する関数
    const handleItemChange = (id, field, value) => {
        setLineItems(prevItems =>
            prevItems.map(item =>
                item.id === id ? { ...item, [field]: value } : item
            )
        );
    };

    // 品目選択時の処理
    const handleProductSelect = (itemId, productId) => {
        const selectedProduct = products.find(p => p.id === parseInt(productId));
        if (selectedProduct) {
            handleItemChange(itemId, 'product_id', selectedProduct.id);
            handleItemChange(itemId, 'name', selectedProduct.name);
            handleItemChange(itemId, 'price', selectedProduct.price);
            handleItemChange(itemId, 'cost', selectedProduct.cost);
            // 必要に応じてtypeやunitも設定
            // handleItemChange(itemId, 'type', '製品'); // 例: マスター品目は製品とする
        } else {
            // マスターから選択解除された場合、または自由入力の場合
            handleItemChange(itemId, 'product_id', null);
            handleItemChange(itemId, 'name', ''); // 品目名をクリア
            handleItemChange(itemId, 'price', 0);
            handleItemChange(itemId, 'cost', 0);
            // handleItemChange(itemId, 'type', 'その他'); // Removed as per user request
        }
    };


    function CustomerCombobox() {
        const [open, setOpen] = useState(false)
        const [selectedCustomerId, setSelectedCustomerId] = useState("")
        const [selectedCustomer, setSelectedCustomer] = useState(null);
        const [customers, setCustomers] = useState([]);
        const [search, setSearch] = useState("");

        useEffect(() => {
            const fetchCustomers = async () => {
                try {
                    console.log("Fetching customers with search:", search);
                    const response = await axios.get(`/api/customers?search=${search}`);
                    console.log("API Response:", response.data);
                    setCustomers(response.data);
                } catch (error) {
                    console.error("Failed to fetch customers:", error);
                    if (error.response) {
                        console.error("Error response data:", error.response.data);
                        console.error("Error response status:", error.response.status);
                        console.error("Error response headers:", error.response.headers);
                    } else if (error.request) {
                        console.error("Error request:", error.request);
                    } else {
                        console.error("Error message:", error.message);
                    }
                }
            };
            fetchCustomers();
        }, [search]);

        useEffect(() => {
            if (selectedCustomerId) {
                const foundCustomer = customers.find(customer => customer.id === selectedCustomerId);
                console.log("useEffect for selectedCustomer. selectedCustomerId:", selectedCustomerId, "foundCustomer:", foundCustomer);
                setSelectedCustomer(foundCustomer);
            } else {
                setSelectedCustomer(null);
            }
        }, [selectedCustomerId, customers]);


        return (
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between"
                    >
                        {console.log("PopoverTrigger rendering. selectedCustomer:", selectedCustomer)}
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
                                        console.log("onSelect called for customer.id:", customer.id);
                                        setSelectedCustomerId(customer.id);
                                        console.log("Setting selectedCustomerId to:", customer.id);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            "mr-2 h-4 w-4",
                                            selectedCustomerId === customer.id ? "opacity-100" : "opacity-0"
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

    function StaffCombobox({ selectedStaffId, setSelectedStaffId }) {
        const [open, setOpen] = useState(false)
        const [selectedStaff, setSelectedStaff] = useState(null);
        const [staff, setStaff] = useState([]);
        const [search, setSearch] = useState("");

        useEffect(() => {
            const fetchStaff = async () => {
                try {
                    console.log("Fetching staff with search:", search);
                    const response = await axios.get(`/api/users?search=${search}`);
                    console.log("API Response (Staff):", response.data);
                    setStaff(response.data);
                } catch (error) {
                    console.error("Failed to fetch staff:", error);
                    if (error.response) {
                        console.error("Error response data:", error.response.data);
                        console.error("Error response status:", error.response.status);
                        console.error("Error response headers:", error.response.headers);
                    } else if (error.request) {
                        console.error("Error request:", error.request);
                    } else {
                        console.error("Error message:", error.message);
                    }
                }
            };
            fetchStaff();
        }, [search]);

        useEffect(() => {
            if (selectedStaffId) {
                const foundStaff = staff.find(s => s.id === selectedStaffId);
                console.log("useEffect for selectedStaff. selectedStaffId:", selectedStaffId, "foundStaff:", foundStaff);
                setSelectedStaff(foundStaff);
            } else {
                setSelectedStaff(null);
            }
        }, [selectedStaffId, staff]);

        return (
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between"
                    >
                        {console.log("PopoverTrigger rendering. selectedStaff:", selectedStaff)}
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
                                        console.log("onSelect called for staff.id:", s.id);
                                        setSelectedStaffId(s.id);
                                        console.log("Setting selectedStaffId to:", s.id);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            "mr-2 h-4 w-4",
                                            selectedStaffId === s.id ? "opacity-100" : "opacity-0"
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

    // 計算ロジックはlineItemsの状態に基づいて動的に計算されるように変更
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
    const tax = subtotal * 0.1; // Assuming 10% tax
    const total = subtotal + tax;

    const groupedAnalysisData = lineItems.reduce((acc, item) => {
        const itemName = item.name; // Use item name directly
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

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#AF19FF', '#FF1919', '#19FFD4', '#FF19B8', '#8884d8', '#82ca9d', '#a4de6c', '#d0ed57', '#ffc658', '#ff7300', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff']; // Expanded Colors for the pie chart segments


    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">見積書作成</h2>}
        >
            <Head title="見積書作成" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="flex justify-between items-center">
                        <h1 className="text-2xl font-bold">見積書</h1>
                        <div className="flex items-center space-x-2">
                            <Switch id="view-mode" checked={isInternalView} onCheckedChange={setIsInternalView} />
                            <Label htmlFor="view-mode">{isInternalView ? '社内ビュー' : '社外ビュー'}</Label>
                        </div>
                    </div>

                    {/* Header */}
                    <Card>
                        <CardHeader>
                            <CardTitle>基本情報</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="customer">顧客名</Label>
                                <CustomerCombobox />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="staff">自社担当者</Label>
                                <StaffCombobox selectedStaffId={selectedStaffId} setSelectedStaffId={setSelectedStaffId} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="project-name">件名</Label>
                                <Input id="project-name" placeholder="新会計システム導入" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="estimate-number">見積番号</Label>
                                <Input id="estimate-number" value={estimateNumber} readOnly />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="issue-date">発行日</Label>
                                <Input type="date" id="issue-date" defaultValue="2025-08-27" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="expiry-date">有効期限</Label>
                                <Input type="date" id="expiry-date" />
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
                                <Textarea id="external-remarks" placeholder="お見積りの有効期限は発行後1ヶ月です。" />
                            </div>
                            {isInternalView && (
                                <div className="lg:col-span-2 space-y-2">
                                    <Label htmlFor="internal-remarks">備考（社内メモ）</Label>
                                    <Textarea id="internal-remarks" placeholder="値引きの背景について..." />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Line Items */}
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
                                        <TableHead>詳細</TableHead> {/* 新しく追加 */}
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
                                            <TableCell> {/* 品目名 */}
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
                                            <TableCell> {/* 詳細 */}
                                                <Textarea
                                                    className="mt-2 w-full min-w-[500px]"
                                                    placeholder="詳細（項目）"
                                                    value={item.description}
                                                    onChange={(e) => handleItemChange(item.id, 'description', e.target.value)}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right"> {/* 数量 */}
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
                                            <TableCell> {/* 単位 */}
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
                                            <TableCell className="text-right"> {/* 単価 */}
                                                <Input
                                                    type="number"
                                                    value={item.price}
                                                    onChange={(e) => handleItemChange(item.id, 'price', parseInt(e.target.value) || 0)}
                                                    className="w-24 text-right"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right font-medium"> {/* 金額 */}
                                                {calculateAmount(item).toLocaleString()}
                                            </TableCell>
                                            {isInternalView && (
                                                <TableCell className="text-right text-gray-500"> {/* 原価 */}
                                                    <Input
                                                        type="number"
                                                        value={item.cost}
                                                        onChange={(e) => handleItemChange(item.id, 'cost', parseInt(e.target.value) || 0)}
                                                        className="w-24 text-right"
                                                    />
                                                </TableCell>
                                            )}
                                            {isInternalView && <TableCell className="text-right text-gray-500">{calculateCostAmount(item).toLocaleString()}</TableCell>} {/* 原価金額 */}
                                            {isInternalView && <TableCell className="text-right text-gray-500">{calculateGrossProfit(item).toLocaleString()}</TableCell>} {/* 粗利 */} 
                                            {isInternalView && <TableCell className="text-right text-gray-500">{calculateGrossMargin(item).toFixed(1)}%</TableCell>} {/* 粗利率 */}
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
                                <Button variant="outline" size="sm" onClick={addLineItem}><PlusCircle className="mr-2 h-4 w-4" />行を追加</Button>
                                <Button variant="outline" size="sm"><Copy className="mr-2 h-4 w-4" />選択行を複製</Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Summary */}
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

                    {/* Bottom Action Bar */}
                    <Card>
                        <CardFooter className="flex justify-between items-center py-4">
                            <div>
                                <Button variant="outline"><History className="mr-2 h-4 w-4" />変更履歴</Button>
                            </div>
                            <div className="space-x-2">
                                <Button variant="secondary">下書き保存</Button>
                                <Button variant="outline">
                                    <Eye className="mr-2 h-4 w-4" />
                                    PDFプレビュー
                                </Button>
                                <Button>承認申請</Button>
                            </div>
                        </CardFooter>
                    </Card>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}