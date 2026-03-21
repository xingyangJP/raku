import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { useEffect } from 'react';
import { Input } from '@/Components/ui/input';

export default function Admin({ settings, capacityUsers = [] }) {
    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        operational_staff_count: settings?.operational_staff_count ?? 1,
        user_capacities: (capacityUsers ?? []).map((user) => ({
            id: user.id,
            work_capacity_person_days: user.is_capacity_configured
                ? String(user.work_capacity_person_days ?? '')
                : '',
        })),
    });

    useEffect(() => {
        setData('operational_staff_count', settings?.operational_staff_count ?? 1);
        setData(
            'user_capacities',
            (capacityUsers ?? []).map((user) => ({
                id: user.id,
                work_capacity_person_days: user.is_capacity_configured
                    ? String(user.work_capacity_person_days ?? '')
                    : '',
            })),
        );
    }, [capacityUsers, setData, settings?.operational_staff_count]);

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('admin.update'));
    };

    const updateUserCapacity = (index, value) => {
        setData(
            'user_capacities',
            data.user_capacities.map((row, rowIndex) => (
                rowIndex === index
                    ? { ...row, work_capacity_person_days: value }
                    : row
            )),
        );
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">設定</h2>}
        >
            <Head title="設定" />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>工数基準設定</CardTitle>
                        <CardDescription>ユーザーごとの月間開発キャパを設定します。人数ではなく、各ユーザーの人日合計を工数基準として使います。</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                <div className="font-medium text-slate-900">標準1人月の扱い</div>
                                <p className="mt-1">
                                    個別キャパ未設定ユーザーだけ、標準 {Number(settings?.default_capacity_person_days ?? settings?.person_days_per_person_month ?? 20).toFixed(1)} 人日を使います。
                                    人数入力は使わず、下のユーザー別設定の合計を月間キャパとして扱います。
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <Label>ユーザー別の月間開発キャパ</Label>
                                    <p className="text-sm text-slate-500">
                                        0は「開発キャパなし」、空欄は標準 {Number(settings?.default_capacity_person_days ?? settings?.person_days_per_person_month ?? 20).toFixed(1)} 人日を使います。
                                    </p>
                                </div>
                                <div className="space-y-3">
                                    {(capacityUsers ?? []).map((user, index) => (
                                        <div
                                            key={user.id}
                                            className="grid gap-3 rounded-lg border border-slate-200 p-4 lg:grid-cols-[minmax(0,1.4fr)_160px_180px]"
                                        >
                                            <div className="min-w-0">
                                                <p className="font-medium text-slate-900">{user.name}</p>
                                                <p className="text-sm text-slate-500 truncate">
                                                    {user.email || 'メール未設定'}
                                                    {user.external_user_id ? ` / 外部ID ${user.external_user_id}` : ''}
                                                </p>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`user_capacity_${user.id}`}>月間開発キャパ</Label>
                                                <Input
                                                    id={`user_capacity_${user.id}`}
                                                    type="number"
                                                    min="0"
                                                    max="31"
                                                    step="0.1"
                                                    value={data.user_capacities[index]?.work_capacity_person_days ?? ''}
                                                    onChange={(event) => updateUserCapacity(index, event.target.value)}
                                                    placeholder={String(user.resolved_capacity_person_days ?? settings?.default_capacity_person_days ?? 20)}
                                                />
                                                {errors[`user_capacities.${index}.work_capacity_person_days`] && (
                                                    <p className="text-sm text-red-500">{errors[`user_capacities.${index}.work_capacity_person_days`]}</p>
                                                )}
                                            </div>
                                            <div className="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                                <p>現在の採用値</p>
                                                <p className="mt-1 font-semibold text-slate-900">
                                                    {Number(user.resolved_capacity_person_days ?? settings?.default_capacity_person_days ?? 20).toFixed(1)} 人日
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {user.is_capacity_configured ? '個別設定あり' : '標準値を使用中'}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? '保存中...' : '保存'}
                                </Button>
                                {recentlySuccessful && <span className="text-sm text-emerald-600">保存しました</span>}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>現在の計算結果</CardTitle>
                        <CardDescription>設定保存後はこの値が工数キャパの基準になります。</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-700">
                        <div className="flex items-center justify-between">
                            <span>開発キャパ対象人数</span>
                            <span className="font-semibold">{settings?.operational_staff_count ?? 1}人</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>標準1人月</span>
                            <span className="font-semibold">{settings?.person_days_per_person_month ?? 20}人日</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>1人日</span>
                            <span className="font-semibold">{settings?.person_hours_per_person_day ?? 8}時間</span>
                        </div>
                        <div className="border-t pt-3 flex items-center justify-between">
                            <span>月間キャパ</span>
                            <span className="font-semibold">{Number(settings?.monthly_capacity_person_days ?? 0).toFixed(1)}人日</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>月間キャパ</span>
                            <span className="font-semibold">{Number(settings?.monthly_capacity_person_hours ?? 0).toFixed(1)}時間</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
