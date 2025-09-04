import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError';

export default function Edit({ auth, product, categories }) {
    const { data, setData, put, processing, errors } = useForm({
        sku: product.sku,
        name: product.name,
        category_id: product.category_id,
        unit: product.unit,
        price: product.price,
        cost: product.cost,
        tax_category: product.tax_category,
        is_active: product.is_active,
        description: product.description,
        attributes: product.attributes || {},
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">製品編集</h2>}
        >
            <Head title="製品編集" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <Label htmlFor="sku">SKU</Label>
                                    <Input
                                        id="sku"
                                        type="text"
                                        name="sku"
                                        value={data.sku}
                                        className="mt-1 block w-full"
                                        autoComplete="sku"
                                        onChange={(e) => setData('sku', e.target.value)}
                                    />
                                    <InputError message={errors.sku} className="mt-2" />
                                </div>

                                <div>
                                    <Label htmlFor="name">品目名</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        value={data.name}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                <div>
                                    <Label htmlFor="category_id">分類</Label>
                                    <Select onValueChange={(value) => setData('category_id', value)} value={data.category_id}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="分類を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem key={category.id} value={category.id}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category_id} className="mt-2" />
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
                                    <Label htmlFor="price">定価</Label>
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

                                <div>
                                    <Label htmlFor="description">詳細説明</Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={data.description}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('description', e.target.value)}
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>変更を保存</Button>
                                    <Link href={route('products.index')} className="text-sm text-gray-600 hover:text-gray-900">
                                        キャンセル
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
