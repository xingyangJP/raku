import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/core';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';

export default function Index({ auth, products }) {
    const { flash } = usePage().props;

    const handleDelete = (productId) => {
        if (confirm('Are you sure you want to delete this product?')) {
            router.delete(route('products.destroy', productId));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Product Master</h2>}
        >
            <Head title="Product Master" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {flash.success && (
                                <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    <span className="block sm:inline">{flash.success}</span>
                                </div>
                            )}
                            <div className="flex justify-end mb-4">
                                <Link href={route('products.create')}>
                                    <Button>新規追加</Button>
                                </Link>
                            </div>

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>SKU</TableHead>
                                        <TableHead>品目名</TableHead>
                                        <TableHead>分類</TableHead>
                                        <TableHead>原価</TableHead>
                                        <TableHead>定価</TableHead>
                                        <TableHead>詳細説明</TableHead>
                                        <TableHead>更新日</TableHead>
                                        <TableHead>操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>{product.sku}</TableCell>
                                            <TableCell>{product.name}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">{product.category ? product.category.name : 'N/A'}</Badge>
                                            </TableCell>
                                            <TableCell>{product.cost}</TableCell>
                                            <TableCell>{product.price}</TableCell>
                                            <TableCell>{product.description}</TableCell>
                                            <TableCell>{new Date(product.updated_at).toLocaleDateString()}</TableCell>
                                            <TableCell>
                                                <Link href={route('products.edit', product.id)} className="mr-2">
                                                    <Button variant="outline" size="sm">編集</Button>
                                                </Link>
                                                <Button variant="destructive" size="sm" onClick={() => handleDelete(product.id)}>
                                                    削除
                                                </Button>
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
        </AuthenticatedLayout>
    );
}
