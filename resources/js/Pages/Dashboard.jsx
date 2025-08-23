import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header="ダッシュボード"
        >
            <Head title="Dashboard" />

            <div className="grid gap-4 md:grid-cols-2 md:gap-8 lg:grid-cols-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">
                            売掛サマリー
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">¥1,234,567</div>
                        <p className="text-xs text-muted-foreground">
                            前月比 +10.5%
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">
                            仕入サマリー
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">¥890,123</div>
                        <p className="text-xs text-muted-foreground">
                            前月比 +5.2%
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">当月売上</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">¥2,345,678</div>
                        <p className="text-xs text-muted-foreground">
                            前月比 +20.1%
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">やることリスト</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">5件</div>
                        <p className="text-xs text-muted-foreground">
                            3件の未処理請求書あり
                        </p>
                    </CardContent>
                </Card>
            </div>
            <div className="grid gap-4 md:gap-8 lg:grid-cols-2 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>販売状況サマリー</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* This would be a chart */}
                        <div className="h-64 w-full bg-gray-200 rounded-md flex items-center justify-center">
                            <p className="text-gray-500">グラフ表示エリア</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>売上ランキング</CardTitle>
                        <CardDescription>
                            当月の得意先別売上ランキングです。
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>株式会社A</div>
                                <div className="font-semibold">¥543,210</div>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>B商事</div>
                                <div className="font-semibold">¥432,109</div>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>株式会社C</div>
                                <div className="font-semibold">¥321,098</div>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>D工業</div>
                                <div className="font-semibold">¥210,987</div>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>Eデザイン</div>
                                <div className="font-semibold">¥109,876</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}