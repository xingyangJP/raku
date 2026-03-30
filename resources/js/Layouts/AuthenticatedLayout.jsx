import { useState, useEffect } from 'react';
import { router } from '@inertiajs/core';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Home, Package2, Users, LineChart, Settings, Package, FileText, Landmark, Boxes, ChevronsLeft, ChevronsRight, LifeBuoy, Brain } from 'lucide-react';

import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/Components/ui/sheet';
import { CircleUser, Menu } from 'lucide-react';
import Loading from '@/Components/Loading';

export default function AuthenticatedLayout({ header, children }) {
    const { auth: { user }, appVersion, releaseNotes } = usePage().props;
    console.log('Inertia Page Props:', usePage().props);
    const [loading, setLoading] = useState(false);
    const [isSidebarOpen, setSidebarOpen] = useState(true);
    const [isReleaseModalOpen, setReleaseModalOpen] = useState(Boolean(releaseNotes?.unread));

    useEffect(() => {
        const removeStartListener = router.on('start', () => setLoading(true));
        const removeFinishListener = router.on('finish', () => setLoading(false));

        return () => {
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    useEffect(() => {
        setReleaseModalOpen(Boolean(releaseNotes?.unread));
    }, [releaseNotes?.unread, releaseNotes?.latest?.version]);

    const menuItems = [
        { name: 'ダッシュボード', href: route('dashboard'), icon: Home, current: route().current('dashboard') },
        { name: '見積管理', href: route('quotes.index'), icon: FileText, current: route().current('quotes.index') },
        { name: '注文書一覧', href: route('orders.index'), icon: Landmark, current: route().current('orders.index') },
        { name: '保守売上管理', href: route('maintenance-fees.index'), icon: LineChart, current: route().current('maintenance-fees.index') },
        // { name: '在庫管理', href: route('inventory.index'), icon: Boxes, current: route().current('inventory.index') },
        { name: '商品管理', href: route('products.index'), icon: Package, current: route().current('products.index') },
        { name: '訪問前AIコーチ', href: route('sales-ai-coach.index'), icon: Brain, current: route().current('sales-ai-coach.index') },
        { name: '設定', href: route('admin.index'), icon: Settings, current: route().current('admin.index') },
        { name: '更新履歴', href: route('release-notes.index'), icon: Bell, current: route().current('release-notes.index') },
        { name: 'ヘルプ', href: route('help.index'), icon: LifeBuoy, current: route().current('help.index') },
    ];

    const latestRelease = releaseNotes?.latest ?? null;
    const releaseNotesUrl = route('release-notes.index');

    const handleConfirmLatestRelease = () => {
        router.post(route('release-notes.readLatest'), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setReleaseModalOpen(false);
                router.visit(releaseNotesUrl);
            },
        });
    };

    return (
        <>
            {loading && <Loading />}
            <Modal show={Boolean(latestRelease) && isReleaseModalOpen} onClose={() => {}} closeable={false} maxWidth="2xl">
                <div className="space-y-6 p-6 sm:p-8">
                    <div className="space-y-2">
                        <div className="inline-flex rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold text-white">
                            更新履歴★
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-2xl font-bold text-slate-900">{latestRelease?.title ?? '最新の更新'}</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {latestRelease?.version ?? appVersion} / {latestRelease?.released_at ?? ''}
                                </p>
                            </div>
                            <Link
                                href={releaseNotesUrl}
                                className="text-sm font-medium text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                            >
                                更新履歴ページを見る
                            </Link>
                        </div>
                        <p className="text-sm leading-7 text-slate-600">{latestRelease?.summary ?? ''}</p>
                        <p className="text-sm font-semibold leading-7 text-rose-600">
                            この更新は業務フローに影響します。確認後は、必ずヘルプ画面を熟読してください。
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <ul className="space-y-3 text-sm leading-7 text-slate-700">
                            {(latestRelease?.items ?? []).map((item) => (
                                <li key={item} className="flex gap-3">
                                    <span className="mt-2 h-1.5 w-1.5 rounded-full bg-slate-400" />
                                    <span>{item}</span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="flex justify-end">
                        <Button type="button" onClick={handleConfirmLatestRelease} className="bg-slate-950 text-white hover:bg-slate-800">
                            確認してヘルプを読みます
                        </Button>
                    </div>
                </div>
            </Modal>
            <div className={`grid min-h-screen w-full transition-all duration-300 ${isSidebarOpen ? 'md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr]' : 'md:grid-cols-[70px_1fr] lg:grid-cols-[80px_1fr]'}`}>
                <div className="hidden border-r bg-muted/40 md:block">
                    <div className="flex h-full max-h-screen flex-col gap-2 relative">
                        <div className="flex h-14 items-center border-b px-4 lg:h-[60px] lg:px-6">
                            <Link href="/" className="flex items-center gap-2 font-semibold">
                                <Package2 className="h-6 w-6" />
                                {isSidebarOpen && (
                                    <div className="flex items-center gap-2">
                                        <span>KCS販売管理</span>
                                        <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                            {appVersion}
                                        </span>
                                    </div>
                                )}
                            </Link>
                        </div>
                        <div className="flex-1">
                            <nav className={`grid items-start px-2 text-sm font-medium lg:px-4 ${!isSidebarOpen ? 'justify-center' : ''}`}>
                                {menuItems.map((item) => (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary ${
                                            item.current ? 'bg-muted text-primary' : ''
                                        } ${!isSidebarOpen ? 'justify-center' : ''}`}
                                    >
                                        <item.icon className="h-4 w-4" />
                                        {isSidebarOpen && item.name}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="absolute -right-5 top-1/2 -translate-y-1/2 rounded-full bg-muted hover:bg-muted-foreground/20"
                            onClick={() => setSidebarOpen(!isSidebarOpen)}
                        >
                            {isSidebarOpen ? <ChevronsLeft className="h-4 w-4" /> : <ChevronsRight className="h-4 w-4" />}
                        </Button>
                    </div>
                </div>
                <div className="flex flex-col">
                    <header className="flex h-14 items-center gap-4 border-b bg-muted/40 px-4 lg:h-[60px] lg:px-6">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="shrink-0 md:hidden"
                                >
                                    <Menu className="h-5 w-5" />
                                    <span className="sr-only">Toggle navigation menu</span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="flex flex-col">
                                <nav className="grid gap-2 text-lg font-medium">
                                    <Link
                                        href="#"
                                        className="flex items-center gap-2 text-lg font-semibold"
                                    >
                                        <Package2 className="h-6 w-6" />
                                        <span className="sr-only">KCS販売管理</span>
                                    </Link>
                                    {menuItems.map((item) => (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className="mx-[-0.65rem] flex items-center gap-4 rounded-xl px-3 py-2 text-muted-foreground hover:text-foreground"
                                        >
                                            <item.icon className="h-5 w-5" />
                                            {item.name}
                                        </Link>
                                    ))}
                                </nav>
                            </SheetContent>
                        </Sheet>
                        <div className="w-full flex-1">
                            {header && (
                                <div className="text-lg font-semibold">{header}</div>
                            )}
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="secondary" size="icon" className="rounded-full">
                                    <CircleUser className="h-5 w-5" />
                                    <span className="sr-only">Toggle user menu</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>{user.name}</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={route('profile.edit')}>Profile</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={route('logout')} method="post" as="button">Logout</Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </header>
                    <main className="flex flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
