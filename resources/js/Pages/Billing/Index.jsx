import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatCurrency, formatDate } from '@/lib/utils';

const computeDefaultBillingMonth = (value) => {
    if (value) return value;
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${now.getFullYear()}-${month}`;
};

const resolvePaymentStatus = (billing) => {
    const raw = (billing?.payment_status || '').trim();
    if (!raw) {
        return '未入金';
    }

    const normalized = raw.replace(/\s+/g, '').toLowerCase();
    const unpaidKeywords = ['未入金', '未収', '未設定', '未支払', '未支払い', '未払い', 'unpaid', 'pending'];
    if (unpaidKeywords.some((keyword) => normalized.includes(keyword))) {
        return '未入金';
    }

    const paidKeywords = ['入金済', '入金済み', '入金完了', 'paid', '支払済', '支払い済'];
    if (paidKeywords.some((keyword) => normalized.includes(keyword))) {
        return '入金';
    }

    return normalized.includes('入金') ? '入金' : '未入金';
};

const resolveSource = (row) => {
    if (row?.source) return row.source;
    if (row?.id?.startsWith('local-')) return 'local';
    if (row?.id?.startsWith('mf-')) return 'money_forward';
    return 'money_forward';
};

const billingLink = (row) => {
    if (!row?.billing_number) return '-';
    const source = resolveSource(row);

    if (source === 'local' && row?.local_invoice_id) {
        const href = route('invoices.viewPdf.start', { invoice: row.local_invoice_id });
        return (
            <a href={href} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900">
                {row.billing_number}
            </a>
        );
    }

    const billingId = row?.money_forward_id || (row?.id?.startsWith('mf-') ? row.id.replace('mf-', '') : null);
    if (billingId) {
        return (
            <a href={`https://invoice.moneyforward.com/billings/${billingId}/edit`} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900">
                {row.billing_number}
            </a>
        );
    }

    return row.billing_number;
};

const filterBillingsList = (source, filters) => {
    let temp = [...source];

    if (filters.title) {
        const lower = filters.title.toLowerCase();
        temp = temp.filter((b) => (b.title || '').toLowerCase().includes(lower));
    }

    if (filters.billing_month_from || filters.billing_month_to) {
        const fromMonth = filters.billing_month_from ? new Date(`${filters.billing_month_from}-01`) : null;
        const toMonth = filters.billing_month_to ? new Date(`${filters.billing_month_to}-01`) : null;

        temp = temp.filter((b) => {
            if (!b.billing_date) return false;
            const billingDate = new Date(b.billing_date);
            if (Number.isNaN(billingDate.getTime())) return false;

            const monthStart = new Date(billingDate.getFullYear(), billingDate.getMonth(), 1);
            const monthEnd = new Date(billingDate.getFullYear(), billingDate.getMonth() + 1, 0, 23, 59, 59, 999);

            const afterFrom = fromMonth ? monthEnd >= fromMonth : true;
            const beforeTo = toMonth
                ? monthStart <= new Date(toMonth.getFullYear(), toMonth.getMonth() + 1, 0, 23, 59, 59, 999)
                : true;

            return afterFrom && beforeTo;
        });
    }

    if (filters.partner) {
        const partnerLower = filters.partner.toLowerCase();
        temp = temp.filter((b) => (b.partner_name || '').toLowerCase().includes(partnerLower));
    }

    if (filters.status) {
        temp = temp.filter((b) => resolvePaymentStatus(b) === filters.status);
    }

    return temp;
};

export default function BillingIndex({ auth, moneyForwardInvoices, moneyForwardConfig, error, syncStatus, defaultBillingRange }) {
    const billings = moneyForwardInvoices.data || [];

    const [filters, setFilters] = useState(() => {
        const defaultFromMonth = computeDefaultBillingMonth(defaultBillingRange?.from);
        const defaultToMonth = computeDefaultBillingMonth(defaultBillingRange?.to ?? defaultBillingRange?.from);
        return {
            title: '',
            billing_month_from: defaultFromMonth,
            billing_month_to: defaultToMonth,
            partner: '',
            status: '',
        };
    });

    const [filteredBillings, setFilteredBillings] = useState(() => filterBillingsList(billings, filters));

    const summary = React.useMemo(() => {
        const total = filteredBillings.reduce((sum, b) => sum + Number(b.total_price || b.total_amount || 0), 0);
        const unpaid = filteredBillings
            .filter((b) => resolvePaymentStatus(b) === '未入金')
            .reduce((sum, b) => sum + Number(b.total_price || b.total_amount || 0), 0);
        return {
            count: filteredBillings.length,
            total,
            unpaid,
        };
    }, [filteredBillings]);

    useEffect(() => {
        setFilteredBillings(filterBillingsList(billings, filters));
    }, [billings]);

    const moneyForwardAuthUrl = (() => {
        const params = new URLSearchParams({
            response_type: 'code',
            client_id: moneyForwardConfig.client_id,
            redirect_uri: moneyForwardConfig.redirect_uri,
            scope: moneyForwardConfig.scope,
            state: Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
        });
        return `${moneyForwardConfig.authorization_url}?${params.toString()}`;
    })();

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilters((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const applyFilters = () => {
        setFilteredBillings(filterBillingsList(billings, filters));
    };

    const resetFilters = () => {
        const defaultFromMonth = computeDefaultBillingMonth(defaultBillingRange?.from);
        const defaultToMonth = computeDefaultBillingMonth(defaultBillingRange?.to ?? defaultBillingRange?.from);
        const reset = {
            title: '',
            billing_month_from: defaultFromMonth,
            billing_month_to: defaultToMonth,
            partner: '',
            status: '',
        };
        setFilters(reset);
        setFilteredBillings(filterBillingsList(billings, reset));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">請求書一覧</h2>}
        >
            <Head title="請求書一覧" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {error && (
                        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong className="font-bold">エラー:</strong>
                            <span className="block sm:inline"> {error}</span>
                        </div>
                    )}
                    {syncStatus?.status === 'synced' && syncStatus.synced_at && (
                        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded mb-4">
                            最終同期: {new Date(syncStatus.synced_at).toLocaleString('ja-JP')}
                        </div>
                    )}
                    {syncStatus?.status === 'skipped' && syncStatus.synced_at && (
                        <div className="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded mb-4">
                            前回同期: {new Date(syncStatus.synced_at).toLocaleString('ja-JP')}
                        </div>
                    )}

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-2xl font-bold text-gray-900">請求書一覧</h3>
                            <div className="space-x-2">
                                <a
                                    href={moneyForwardAuthUrl}
                                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    マネーフォワードから取得
                                </a>
                                <Link
                                    href={route('billing.create')}
                                    className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    新規作成
                                </Link>
                            </div>
                        </div>

                        <div className="mb-6 p-4 border rounded-lg bg-gray-50">
                            <h4 className="font-semibold text-gray-700 mb-3">検索条件</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label htmlFor="title" className="block text-sm font-medium text-gray-700">タイトル</label>
                                    <input
                                        type="text"
                                        name="title"
                                        id="title"
                                        value={filters.title}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="billing_month_from" className="block text-sm font-medium text-gray-700">請求月 From</label>
                                    <input
                                        type="month"
                                        name="billing_month_from"
                                        id="billing_month_from"
                                        value={filters.billing_month_from}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="billing_month_to" className="block text-sm font-medium text-gray-700">請求月 To</label>
                                    <input
                                        type="month"
                                        name="billing_month_to"
                                        id="billing_month_to"
                                        value={filters.billing_month_to}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="partner" className="block text-sm font-medium text-gray-700">取引先</label>
                                    <input
                                        type="text"
                                        name="partner"
                                        id="partner"
                                        value={filters.partner}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="status" className="block text-sm font-medium text-gray-700">ステータス</label>
                                    <select
                                        name="status"
                                        id="status"
                                        value={filters.status}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    >
                                        <option value="">全て</option>
                                        <option value="入金">入金</option>
                                        <option value="未入金">未入金</option>
                                    </select>
                                </div>
                            </div>
                            <div className="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    リセット
                                </button>
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    検索
                                </button>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div className="bg-gray-50 border rounded-lg p-4 shadow-sm">
                                <h4 className="text-sm font-medium text-gray-600">請求件数</h4>
                                <p className="text-2xl font-semibold text-gray-900 mt-2">{summary.count}件</p>
                            </div>
                            <div className="bg-gray-50 border rounded-lg p-4 shadow-sm">
                                <h4 className="text-sm font-medium text-gray-600">請求額合計</h4>
                                <p className="text-2xl font-semibold text-gray-900 mt-2">{formatCurrency(summary.total)}</p>
                            </div>
                            <div className="bg-gray-50 border rounded-lg p-4 shadow-sm">
                                <h4 className="text-sm font-medium text-gray-600">未入金合計</h4>
                                <p className="text-2xl font-semibold text-orange-600 mt-2">{formatCurrency(summary.unpaid)}</p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            {filteredBillings.length > 0 ? (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <input type="checkbox" className="rounded" />
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">請求書番号</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">取引先 / タイトル</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">請求日 / 支払期日</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">金額</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">更新日時</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MF</th>
                                            <th className="px-6 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredBillings.map((billing) => (
                                            <tr key={billing.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <input type="checkbox" className="rounded" />
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {billingLink(billing)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {billing.source === 'local' ? (
                                                        <Link href={route('invoices.edit', { invoice: billing.local_invoice_id })} className="text-indigo-600 hover:text-indigo-900">
                                                            {billing.partner_name}<br />{billing.title}
                                                        </Link>
                                                    ) : (
                                                        <a
                                                            href={`https://invoice.moneyforward.com/billings/${billing.id}/edit`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            {billing.partner_name}<br />{billing.title}
                                                        </a>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(billing.billing_date)}<br />{formatDate(billing.due_date)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500">
                                                    {formatCurrency(billing.total_price || billing.total_amount || 0)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {resolvePaymentStatus(billing)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(billing.updated_at)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    {billing.source === 'local' ? (
                                                        <>
                                                            {billing.mf_billing_id ? (
                                                                <>
                                                                    <span className="text-green-600 ml-2">✅</span>
                                                                    <Link
                                                                        href={route('invoices.edit', { invoice: billing.local_invoice_id })}
                                                                        className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                                    >
                                                                        詳細
                                                                    </Link>
                                                                    <a
                                                                        href={`https://invoice.moneyforward.com/billings/${billing.mf_billing_id}/edit`}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                                    >
                                                                        → MF
                                                                    </a>
                                                                    <a
                                                                        href={route('invoices.viewPdf.start', { invoice: billing.local_invoice_id })}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                                    >
                                                                        PDF
                                                                    </a>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Link
                                                                        href={route('invoices.edit', { invoice: billing.local_invoice_id })}
                                                                        className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                                    >
                                                                        詳細
                                                                    </Link>
                                                                    <span className="text-gray-400 ml-2">MF未生成</span>
                                                                </>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="text-green-600 ml-2">✅</span>
                                                            <a
                                                                href={`https://invoice.moneyforward.com/billings/${billing.id}/edit`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                            >
                                                                → MF
                                                            </a>
                                                            <a
                                                                href={route('billing.downloadPdf', { billing: billing.id })}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-indigo-600 hover:text-indigo-900 ml-2"
                                                            >
                                                                PDF
                                                            </a>
                                                        </>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    {/* 追加アクション領域 */}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="p-6 text-gray-900">請求データがありません。</p>
                            )}
                        </div>

                        {moneyForwardInvoices.pagination && (
                            <div className="mt-4 flex justify-between items-center">
                                <div>
                                    表示中: {moneyForwardInvoices.pagination.current_page} / {moneyForwardInvoices.pagination.total_pages} ページ ({moneyForwardInvoices.pagination.total_count} 件中)
                                </div>
                                <div className="flex space-x-2">
                                    {/* Pagination links placeholder */}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
