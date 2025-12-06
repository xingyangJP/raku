import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { useEffect } from 'react';

export default function Settings({ basePrompt, defaultPrompt }) {
    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        base_prompt: basePrompt || '',
    });

    useEffect(() => {
        setData('base_prompt', basePrompt || '');
    }, [basePrompt, setData]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('sales-ai-coach.settings.update'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-2xl font-semibold text-slate-800">AIコーチ設定</h2>}
        >
            <Head title="AIコーチ設定" />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>ベースプロンプト</CardTitle>
                        <CardDescription>標準の販売管理向けプロンプトに追記するカスタム指示を設定できます（ベースは残ります）。</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={handleSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="base_prompt">現在のカスタムプロンプト</Label>
                                <Textarea
                                    id="base_prompt"
                                    minRows={8}
                                    placeholder="カスタム指示を入力してください"
                                    value={data.base_prompt}
                                    onChange={(e) => setData('base_prompt', e.target.value)}
                                />
                                {errors.base_prompt && <p className="text-sm text-red-500">{errors.base_prompt}</p>}
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

                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>デフォルトプロンプト</CardTitle>
                        <CardDescription>上書きなしの場合に使用される標準の指示です。</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 whitespace-pre-wrap">
                            {defaultPrompt}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
