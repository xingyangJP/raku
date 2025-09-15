import { useState, useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/react';
import axios from 'axios';

export function CategoryDialog({ open, onOpenChange, onSave }) {
    const [categories, setCategories] = useState([]);
    const [editingCategory, setEditingCategory] = useState(null);
    const { data, setData, post, put, reset, errors } = useForm({ name: '' });

    const fetchCategories = () => {
        axios.get(route('categories.index')).then(res => setCategories(res.data));
    };

    useEffect(() => {
        if (open) {
            fetchCategories();
        }
    }, [open]);

    const handleSave = (e) => {
        e.preventDefault();
        const url = editingCategory ? route('categories.update', editingCategory.id) : route('categories.store');
        const method = editingCategory ? 'put' : 'post';
        
        axios[method](url, { name: data.name })
            .then(() => {
                fetchCategories();
                reset();
                setEditingCategory(null);
                onSave(); // Notify parent to refetch categories for filter
            })
            .catch(err => {
                if (err.response.status === 422) {
                    alert(err.response.data.message);
                }
            });
    };

    const handleDelete = (id) => {
        if (confirm('本当にこの分類を削除しますか？')) {
            axios.delete(route('categories.destroy', id))
                .then(() => fetchCategories())
                .catch(err => {
                    if (err.response.status === 422) {
                        alert(err.response.data.message);
                    }
                });
        }
    };

    const startEdit = (category) => {
        setEditingCategory(category);
        setData('name', category.name);
    };

    const cancelEdit = () => {
        setEditingCategory(null);
        reset();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>分類の管理</DialogTitle>
                </DialogHeader>
                <div className="py-4">
                    <form onSubmit={handleSave} className="flex items-center space-x-2 mb-4">
                        <Input
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            placeholder="新しい分類名"
                            className="flex-grow"
                        />
                        {editingCategory ? (
                            <div className="flex space-x-2">
                                <Button type="submit">更新</Button>
                                <Button type="button" variant="outline" onClick={cancelEdit}>キャンセル</Button>
                            </div>
                        ) : (
                            <Button type="submit">追加</Button>
                        )}
                    </form>
                    <div className="max-h-60 overflow-y-auto">
                        <ul className="space-y-2">
                            {categories.map(cat => (
                                <li key={cat.id} className="flex items-center justify-between p-2 border rounded-md">
                                    <div>
                                        <span className="font-medium">{cat.name}</span>
                                        <span className="text-sm text-gray-500 ml-2">({cat.code})</span>
                                    </div>
                                    <div className="flex space-x-2">
                                        <Button variant="outline" size="sm" onClick={() => startEdit(cat)}>編集</Button>
                                        <Button variant="destructive" size="sm" onClick={() => handleDelete(cat.id)}>削除</Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">閉じる</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
