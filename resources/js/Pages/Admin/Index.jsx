import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useEffect } from 'react';

export default function Admin({ settings }) {
    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        operational_staff_count: settings?.operational_staff_count ?? 1,
    });

    useEffect(() => {
        setData('operational_staff_count', settings?.operational_staff_count ?? 1);
    }, [setData, settings?.operational_staff_count]);

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('admin.update'));
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
                        <CardDescription>月間キャパの基準人数を設定します。ダッシュボードと注文書一覧の工数キャパ計算に反映されます。</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="operational_staff_count">稼働人数</Label>
                                <Input
                                    id="operational_staff_count"
                                    type="number"
                                    min="1"
                                    max="500"
                                    value={data.operational_staff_count}
                                    onChange={(event) => setData('operational_staff_count', event.target.value)}
                                />
                                <p className="text-sm text-slate-500">
                                    1人月={settings?.person_days_per_person_month ?? 20}日、
                                    1人日={settings?.person_hours_per_person_day ?? 8}時間の前提で月間キャパを算出します。
                                </p>
                                {errors.operational_staff_count && <p className="text-sm text-red-500">{errors.operational_staff_count}</p>}
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
                            <span>稼働人数</span>
                            <span className="font-semibold">{settings?.operational_staff_count ?? 1}人</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>1人月</span>
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
