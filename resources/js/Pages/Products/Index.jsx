import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { router } from '@inertiajs/core';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { CategoryDialog } from '@/Components/CategoryDialog';
import { useState } from 'react';
import SyncButton from '@/Components/SyncButton';

const taxCategories = [
    { value: 'ten_percent', label: '10%' },
    { value: 'eight_percent_as_reduced_tax_rate', label: '8% (軽減税率)' },
    { value: 'five_percent', label: '5%' },
    { value: 'eight_percent', label: '8%' },
    { value: 'tax_exemption', label: '免税' },
    { value: 'non_taxable', label: '非課税' },
    { value: 'untaxable', label: '不課税' },
];

export default function Index({ auth, products, categories, filters }) {
    const [isCategoryDialogOpen, setCategoryDialogOpen] = useState(false);
    const { flash } = usePage().props;

    const { data, setData, get } = useForm({
        search_name: filters.search_name || '',
        search_sku: filters.search_sku || '',
        search_tax_category: filters.search_tax_category || 'all',
        search_category_id: filters.search_category_id || 'all',
    });

    const handleDelete = (productId) => {
        if (confirm('Are you sure you want to delete this product?')) {
            router.delete(route('products.destroy', productId));
        }
    };

    const handleSearch = (e) => {
        e.preventDefault();
        get(route('products.index'), { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">商品マスタ</h2>}
        >
            <Head title="商品マスタ" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {flash.success && (
                                <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    <span className="block sm:inline">{flash.success}</span>
                                </div>
                            )}
                            {flash.error && (
                                <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    <span className="block sm:inline">{flash.error}</span>
                                </div>
                            )}
                            <div className="flex justify-between items-center mb-4">
                                <div className="flex space-x-2">
                                    <SyncButton onClick={() => router.visit(route('products.sync.all'))}>
                                        MF同期
                                    </SyncButton>
                                </div>
                                <div className="flex justify-end space-x-2">
                                    <Button variant="outline" onClick={() => setCategoryDialogOpen(true)}>分類を管理</Button>
                                    <Link href={route('products.create')}>
                                        <Button>新規追加</Button>
                                    </Link>
                                </div>
                            </div>

                            <form onSubmit={handleSearch} className="flex items-center space-x-4 mb-4 p-4 bg-gray-50 rounded-lg">
                                <Input
                                    type="text"
                                    placeholder="商品名"
                                    value={data.search_name}
                                    onChange={(e) => setData('search_name', e.target.value)}
                                    className="max-w-xs"
                                />
                                <Input
                                    type="text"
                                    placeholder="商品コード"
                                    value={data.search_sku}
                                    onChange={(e) => setData('search_sku', e.target.value)}
                                    className="max-w-xs"
                                />
                                <Select onValueChange={(value) => setData('search_tax_category', value)} value={data.search_tax_category}>
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="税区分" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">全て</SelectItem>
                                        {taxCategories.map(cat => (
                                            <SelectItem key={cat.value} value={cat.value}>{cat.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select onValueChange={(value) => setData('search_category_id', value)} value={data.search_category_id}>
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="分類" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">全て</SelectItem>
                                        {categories.map(cat => (
                                            <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="submit">検索</Button>
                            </form>

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>商品コード</TableHead>
                                        <TableHead>商品名</TableHead>
                                        <TableHead>単位</TableHead>
                                        <TableHead>単価</TableHead>
                                        <TableHead>原価</TableHead>
                                        <TableHead>税区分</TableHead>
                                        <TableHead>MF最終更新</TableHead>
                                        <TableHead>操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>{product.sku}</TableCell>
                                            <TableCell>{product.name}</TableCell>
                                            <TableCell>{product.unit}</TableCell>
                                            <TableCell>{parseInt(product.price, 10).toLocaleString('ja-JP', { style: 'currency', currency: 'JPY' })}</TableCell>
                                            <TableCell>{parseInt(product.cost, 10).toLocaleString('ja-JP', { style: 'currency', currency: 'JPY' })}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">{taxCategories.find(c => c.value === product.tax_category)?.label || product.tax_category}</Badge>
                                            </TableCell>
                                            <TableCell>{product.mf_updated_at ? new Date(product.mf_updated_at).toLocaleString() : 'N/A'}</TableCell>
                                            <TableCell>
                                                <div className="flex space-x-2">
                                                    <Link href={route('products.edit', product.id)}>
                                                        <Button variant="outline" size="sm">編集</Button>
                                                    </Link>
                                                    <Button variant="destructive" size="sm" onClick={() => handleDelete(product.id)}>
                                                        削除
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            <div className="mt-4 flex justify-center">
                                {products.links.map((link, index) => {
                                    const isNull = link.url === null;
                                    const classNames = `mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded ${
                                        link.active ? "bg-blue-700 text-white" : "bg-white"
                                    } ${isNull ? "text-gray-400" : "hover:bg-gray-100"}`;

                                    if (isNull) {
                                        return (
                                            <div
                                                key={index}
                                                className={classNames}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    } else {
                                        return (
                                            <Link
                                                key={index}
                                                className={classNames}
                                                href={link.url}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }
                                })}
                            </div>
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
