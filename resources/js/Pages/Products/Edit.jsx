import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';
import InputError from '@/Components/InputError';
import { CategoryDialog } from '@/Components/CategoryDialog';
import { useState } from 'react';

const taxCategories = [
    { value: 'ten_percent', label: '10%' },
    { value: 'eight_percent_as_reduced_tax_rate', label: '8% (軽減税率)' },
    { value: 'five_percent', label: '5%' },
    { value: 'eight_percent', label: '8%' },
    { value: 'tax_exemption', label: '免税' },
    { value: 'non_taxable', label: '非課税' },
    { value: 'untaxable', label: '不課税' },
];

export default function Edit({ auth, product, categories }) {
    const [isCategoryDialogOpen, setCategoryDialogOpen] = useState(false);
    const { data, setData, put, processing, errors, reset } = useForm({
        sku: product.sku || '自動採番',
        name: product.name || '',
        category_id: product.category_id || '',
        unit: product.unit || '式',
        price: product.price || 0,
        quantity: product.quantity || 1,
        cost: product.cost || 0,
        tax_category: product.tax_category || 'ten_percent',
        is_deduct_withholding_tax: product.is_deduct_withholding_tax || false,
        is_active: product.is_active || true,
        description: product.description || '',
        attributes: product.attributes || {},
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">商品編集</h2>}
        >
            <Head title="商品編集" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <form onSubmit={submit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label htmlFor="sku">商品コード</Label>
                                        <Input
                                            id="sku"
                                            type="text"
                                            name="sku"
                                            value={data.sku}
                                            className="mt-1 block w-full bg-gray-100"
                                            readOnly
                                        />
                                        <InputError message={errors.sku} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="name">商品名</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            name="name"
                                            value={data.name}
                                            className="mt-1 block w-full"
                                            onChange={(e) => setData('name', e.target.value)}
                                            isFocused={true}
                                        />
                                        <InputError message={errors.name} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="category_id">分類</Label>
                                        <div className="flex items-center space-x-2">
                                            <Select onValueChange={(value) => setData('category_id', value)} value={String(data.category_id)}>
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="分類を選択" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.map((cat) => (
                                                        <SelectItem key={cat.id} value={String(cat.id)}>
                                                            {cat.name} ({cat.code})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Button type="button" variant="outline" onClick={() => setCategoryDialogOpen(true)}>+</Button>
                                        </div>
                                        <InputError message={errors.category_id} className="mt-2" />
                                    </div>

                                    

                                    <div>
                                        <Label htmlFor="tax_category">税区分</Label>
                                        <Select onValueChange={(value) => setData('tax_category', value)} value={data.tax_category}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select a tax category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {taxCategories.map((cat) => (
                                                    <SelectItem key={cat.value} value={cat.value}>
                                                        {cat.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.tax_category} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="price">単価</Label>
                                        <Input
                                            id="price"
                                            type="number"
                                            name="price"
                                            value={data.price}
                                            className="mt-1 block w-full"
                                            onChange={(e) => setData('price', e.target.value)}
                                        />
                                        <InputError message={errors.price} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="unit">単位</Label>
                                        <Input
                                            id="unit"
                                            type="text"
                                            name="unit"
                                            value={data.unit}
                                            className="mt-1 block w-full"
                                            onChange={(e) => setData('unit', e.target.value)}
                                        />
                                        <InputError message={errors.unit} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="quantity">標準数量</Label>
                                        <Input
                                            id="quantity"
                                            type="number"
                                            name="quantity"
                                            value={data.quantity}
                                            className="mt-1 block w-full"
                                            onChange={(e) => setData('quantity', e.target.value)}
                                        />
                                        <InputError message={errors.quantity} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="cost">原価</Label>
                                        <Input
                                            id="cost"
                                            type="number"
                                            name="cost"
                                            value={data.cost}
                                            className="mt-1 block w-full"
                                            onChange={(e) => setData('cost', e.target.value)}
                                        />
                                        <InputError message={errors.cost} className="mt-2" />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="description">詳細</Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={data.description}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('description', e.target.value)}
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                <div className="flex items-center space-x-4">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_deduct_withholding_tax"
                                            checked={data.is_deduct_withholding_tax}
                                            onCheckedChange={(checked) => setData('is_deduct_withholding_tax', checked)}
                                        />
                                        <Label htmlFor="is_deduct_withholding_tax">源泉徴収</Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_active"
                                            checked={data.is_active}
                                            onCheckedChange={(checked) => setData('is_active', checked)}
                                        />
                                        <Label htmlFor="is_active">有効</Label>
                                    </div>
                                </div>


                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>保存</Button>
                                    <Link href={route('products.sync.one', product.id)} method="get" as="button">
                                        <Button variant="secondary" type="button">MFへ同期</Button>
                                    </Link>
                                    <Link href={route('products.index')} className="text-sm text-gray-600 hover:text-gray-900">
                                        キャンセル
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <CategoryDialog 
                open={isCategoryDialogOpen} 
                onOpenChange={setCategoryDialogOpen}
                onSave={() => router.reload({ only: ['categories'] })}
            />
        </AuthenticatedLayout>
    );
}