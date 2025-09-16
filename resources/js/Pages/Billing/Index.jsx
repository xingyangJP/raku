import React, { useState, useEffect, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function BillingIndex({ auth, moneyForwardInvoices, moneyForwardConfig, error }) {
    const billings = moneyForwardInvoices.data || [];
    const [filteredBillings, setFilteredBillings] = useState(billings);

    // State for filters
    const [filters, setFilters] = useState({
        keyword: '',
        billing_date_from: '',
        billing_date_to: '',
        due_date_from: '',
        due_date_to: '',
        sales_date_from: '',
        sales_date_to: '',
        email_status: [],
        posting_status: [],
        payment_status: [],
        operator_id: '',
        department_id: '',
        member_id: '',
        partner_id: '',
        office_id: '',
        is_downloaded: null,
    });

    const summary = useMemo(() => {
        const total = filteredBillings.reduce((sum, b) => sum + Number(b.total_price || 0), 0);
        const unpaid = filteredBillings
            .filter(b => b.payment_status === '未入金')
            .reduce((sum, b) => sum + Number(b.total_price || 0), 0);

        return {
            count: filteredBillings.length,
            total,
            unpaid,
        };
    }, [filteredBillings]);

    // Construct the Money Forward authorization URL
    const moneyForwardAuthUrl = (() => {
        const params = new URLSearchParams({
            response_type: 'code',
            client_id: moneyForwardConfig.client_id,
            redirect_uri: moneyForwardConfig.redirect_uri,
            scope: moneyForwardConfig.scope,
            state: Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15), // Simple random state
        });
        return `${moneyForwardConfig.authorization_url}?${params.toString()}`;
    })();

    const handleFilterChange = (e) => {
        const { name, value, type, checked } = e.target;
        if (type === 'checkbox') {
            setFilters((prev) => ({
                ...prev,
                [name]: checked
            }));
        } else if (type === 'select-multiple') {
            const options = Array.from(e.target.options).filter(option => option.selected).map(option => option.value);
            setFilters((prev) => ({
                ...prev,
                [name]: options
            }));
        } else {
            setFilters((prev) => ({
                ...prev,
                [name]: value
            }));
        }
    };

    const handleStatusChange = (statusType, value) => {
        setFilters((prev) => {
            const currentStatus = prev[statusType];
            if (currentStatus.includes(value)) {
                return { ...prev, [statusType]: currentStatus.filter((s) => s !== value) };
            } else {
                return { ...prev, [statusType]: [...currentStatus, value] };
            }
        });
    };

    const applyFilters = () => {
        let tempBillings = [...billings];

        // Keyword
        if (filters.keyword) {
            const lowercasedKeyword = filters.keyword.toLowerCase();
            tempBillings = tempBillings.filter(b =>
                (b.billing_number && b.billing_number.toLowerCase().includes(lowercasedKeyword)) ||
                (b.title && b.title.toLowerCase().includes(lowercasedKeyword)) ||
                (b.partner_name && b.partner_name.toLowerCase().includes(lowercasedKeyword))
            );
        }

        // Dates
        if (filters.billing_date_from) {
            tempBillings = tempBillings.filter(b => new Date(b.billing_date) >= new Date(filters.billing_date_from));
        }
        if (filters.billing_date_to) {
            tempBillings = tempBillings.filter(b => new Date(b.billing_date) <= new Date(filters.billing_date_to));
        }
        if (filters.due_date_from) {
            tempBillings = tempBillings.filter(b => new Date(b.due_date) >= new Date(filters.due_date_from));
        }
        if (filters.due_date_to) {
            tempBillings = tempBillings.filter(b => new Date(b.due_date) <= new Date(filters.due_date_to));
        }
        if (filters.sales_date_from) {
            tempBillings = tempBillings.filter(b => new Date(b.sales_date) >= new Date(filters.sales_date_from));
        }
        if (filters.sales_date_to) {
            tempBillings = tempBillings.filter(b => new Date(b.sales_date) <= new Date(filters.sales_date_to));
        }

        // Statuses
        if (filters.payment_status.length > 0) {
            tempBillings = tempBillings.filter(b => filters.payment_status.includes(b.payment_status));
        }
        if (filters.email_status.length > 0) {
            tempBillings = tempBillings.filter(b => filters.email_status.includes(b.email_status));
        }
        if (filters.posting_status.length > 0) {
            tempBillings = tempBillings.filter(b => filters.posting_status.includes(b.posting_status));
        }

        // Other text inputs
        if (filters.operator_id) {
            tempBillings = tempBillings.filter(b => b.operator_id && b.operator_id.toString() === filters.operator_id);
        }
        if (filters.partner_id) {
            tempBillings = tempBillings.filter(b => b.partner_id && b.partner_id.toString() === filters.partner_id);
        }

        // Downloaded
        if (filters.is_downloaded !== null && filters.is_downloaded !== '') {
            const isDownloadedBool = filters.is_downloaded === 'true';
            tempBillings = tempBillings.filter(b => b.is_downloaded === isDownloadedBool);
        }


        setFilteredBillings(tempBillings);
    };

    const resetFilters = () => {
        setFilters({
            keyword: '',
            billing_date_from: '',
            billing_date_to: '',
            due_date_from: '',
            due_date_to: '',
            sales_date_from: '',
            sales_date_to: '',
            email_status: [],
            posting_status: [],
            payment_status: [],
            operator_id: '',
            department_id: '',
            member_id: '',
            partner_id: '',
            office_id: '',
            is_downloaded: null,
        });
        setFilteredBillings(billings);
    };

    // Dummy data for select options (replace with actual data from backend)
    const paymentStatusOptions = ['未設定', '未入金', '一部入金', '入金済'];
    const emailStatusOptions = ['未送信', '送信済', '送信失敗'];
    const postingStatusOptions = ['未郵送', '郵送中', '郵送済'];

    const getStatusBadgeColor = (statusType, status) => {
        if (statusType === 'payment_status') {
            switch (status) {
                case '入金済': return 'bg-green-100 text-green-800';
                case '一部入金': return 'bg-blue-100 text-blue-800';
                case '未入金': return 'bg-orange-100 text-orange-800';
                case '未設定': return 'bg-gray-100 text-gray-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        } else if (statusType === 'email_status') {
            switch (status) {
                case '送信済': return 'bg-blue-100 text-blue-800';
                case '未送信': return 'bg-gray-100 text-gray-800';
                case '送信失敗': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        } else if (statusType === 'posting_status') {
            switch (status) {
                case '郵送済': return 'bg-blue-100 text-blue-800';
                case '未郵送': return 'bg-gray-100 text-gray-800';
                case '郵送中': return 'bg-purple-100 text-purple-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
        return 'bg-gray-100 text-gray-800';
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
    }

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

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        {/* Header */}
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-2xl font-bold text-gray-900">請求書一覧</h3>
                            <div className="space-x-2">
                                <a
                                    href={moneyForwardAuthUrl} // Use constructed URL
                                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    マネーフォワードから取得
                                </a>
                                <Link
                                    href={route('billing.create')} // Placeholder for create route
                                    className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    新規作成
                                </Link>
                                {/* CSVエクスポートは非表示 */}
                            </div>
                        </div>

                        {/* Conditions Panel */}
                        <div className="mb-6 p-4 border rounded-lg bg-gray-50">
                            <h4 className="font-semibold text-gray-700 mb-3">検索条件</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label htmlFor="keyword" className="block text-sm font-medium text-gray-700">キーワード</label>
                                    <input
                                        type="text"
                                        name="keyword"
                                        id="keyword"
                                        value={filters.keyword}
                                        onChange={handleFilterChange}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                    />
                                </div>
                                {/* Date Filters */}
                                <div>
                                    <label htmlFor="billing_date_from" className="block text-sm font-medium text-gray-700">請求日 From</label>
                                    <input type="date" name="billing_date_from" id="billing_date_from" value={filters.billing_date_from} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label htmlFor="billing_date_to" className="block text-sm font-medium text-gray-700">請求日 To</label>
                                    <input type="date" name="billing_date_to" id="billing_date_to" value={filters.billing_date_to} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label htmlFor="due_date_from" className="block text-sm font-medium text-gray-700">支払期日 From</label>
                                    <input type="date" name="due_date_from" id="due_date_from" value={filters.due_date_from} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label htmlFor="due_date_to" className="block text-sm font-medium text-gray-700">支払期日 To</label>
                                    <input type="date" name="due_date_to" id="due_date_to" value={filters.due_date_to} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                {/* 売上日 From/To は削除 */}

                                {/* Status Filters */}
                                <div className="col-span-full grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">支払ステータス</label>
                                        <div className="flex flex-wrap gap-2">
                                            {paymentStatusOptions.map(status => (
                                                <span
                                                    key={status}
                                                    onClick={() => handleStatusChange('payment_status', status)}
                                                    className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer ${filters.payment_status.includes(status) ? getStatusBadgeColor('payment_status', status) : 'bg-gray-200 text-gray-700'}`}
                                                >
                                                    {status}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">送信ステータス</label>
                                        <div className="flex flex-wrap gap-2">
                                            {emailStatusOptions.map(status => (
                                                <span
                                                    key={status}
                                                    onClick={() => handleStatusChange('email_status', status)}
                                                    className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer ${filters.email_status.includes(status) ? getStatusBadgeColor('email_status', status) : 'bg-gray-200 text-gray-700'}`}
                                                >
                                                    {status}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">郵送ステータス</label>
                                        <div className="flex flex-wrap gap-2">
                                            {postingStatusOptions.map(status => (
                                                <span
                                                    key={status}
                                                    onClick={() => handleStatusChange('posting_status', status)}
                                                    className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer ${filters.posting_status.includes(status) ? getStatusBadgeColor('posting_status', status) : 'bg-gray-200 text-gray-700'}`}
                                                >
                                                    {status}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* Other Filters */}
                                <div>
                                    <label htmlFor="operator_id" className="block text-sm font-medium text-gray-700">担当</label>
                                    <input type="text" name="operator_id" id="operator_id" value={filters.operator_id} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label htmlFor="partner_id" className="block text-sm font-medium text-gray-700">取引先</label>
                                    <input type="text" name="partner_id" id="partner_id" value={filters.partner_id} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label htmlFor="is_downloaded" className="block text-sm font-medium text-gray-700">ダウンロード済み</label>
                                    <select name="is_downloaded" id="is_downloaded" value={filters.is_downloaded === null ? '' : filters.is_downloaded} onChange={handleFilterChange} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="">全て</option>
                                        <option value="true">はい</option>
                                        <option value="false">いいえ</option>
                                    </select>
                                </div>
                            </div>
                            <div className="mt-6 flex justify-end space-x-3">
                                <button
                                    onClick={resetFilters}
                                    className="inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    リセット
                                </button>
                                <button
                                    onClick={applyFilters}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    検索
                                </button>
                            </div>
                        </div>

                        {/* Summary Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 my-6">
                            <div className="bg-white p-4 shadow rounded-lg">
                                <h4 className="text-gray-500 text-sm font-medium">請求件数</h4>
                                <p className="text-gray-900 text-2xl font-semibold">{summary.count}件</p>
                            </div>
                            <div className="bg-white p-4 shadow rounded-lg">
                                <h4 className="text-gray-500 text-sm font-medium">請求額合計</h4>
                                <p className="text-gray-900 text-2xl font-semibold">{formatCurrency(summary.total)}</p>
                            </div>
                            <div className="bg-white p-4 shadow rounded-lg">
                                <h4 className="text-gray-500 text-sm font-medium">未入金合計</h4>
                                <p className="text-orange-600 text-2xl font-semibold">{formatCurrency(summary.unpaid)}</p>
                            </div>
                        </div>

                        {/* Table */}
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
                                            <th className="px-6 py-3"></th> {/* Quick Actions */}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredBillings.map((billing) => (
                                            <tr key={billing.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <input type="checkbox" className="rounded" />
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {billing.source === 'local' ? (
                                          <Link href={route('invoices.edit', { invoice: billing.local_invoice_id })} className="text-indigo-600 hover:text-indigo-900">
                                            {billing.billing_number}
                                          </Link>
                                        ) : (
                                          <a href={`https://invoice.moneyforward.com/billings/${billing.id}/edit`} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900">
                                            {billing.billing_number}
                                          </a>
                                        )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {billing.source === 'local' ? (
                                                      <Link href={route('invoices.edit', { invoice: billing.local_invoice_id })} className="text-indigo-600 hover:text-indigo-900">
                                                        {billing.partner_name}<br/>{billing.title}
                                                      </Link>
                                                    ) : (
                                                      <a href={`https://invoice.moneyforward.com/billings/${billing.id}/edit`} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900">
                                                        {billing.partner_name}<br/>{billing.title}
                                                      </a>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(billing.billing_date)}<br/>{formatDate(billing.due_date)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                                    {formatCurrency(billing.total_price || billing.total_amount || 0)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor('email_status', billing.email_status)}`}>{billing.email_status}</span><br/>
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor('posting_status', billing.posting_status)}`}>{billing.posting_status}</span><br/>
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeColor('payment_status', billing.payment_status)}`}>{billing.payment_status}</span>
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
                                                                    <Link href={route('invoices.edit', { invoice: billing.local_invoice_id })} className="text-indigo-600 hover:text-indigo-900 ml-2">詳細</Link>
                                                                    <a href={`https://invoice.moneyforward.com/billings/${billing.mf_billing_id}/edit`} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900 ml-2">→ MF</a>
                                                                    <a href={route('invoices.viewPdf.start', { invoice: billing.local_invoice_id })} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900 ml-2">PDF</a>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Link href={route('invoices.edit', { invoice: billing.local_invoice_id })} className="text-indigo-600 hover:text-indigo-900 ml-2">詳細</Link>
                                                                    <span className="text-gray-400 ml-2">MF未生成</span>
                                                                </>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="text-green-600 ml-2">✅</span>
                                                            <a href={`https://invoice.moneyforward.com/billings/${billing.id}/edit`} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900 ml-2">→ MF</a>
                                                            <a href={route('billing.downloadPdf', { billing: billing.id })} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900 ml-2">PDF</a>
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="p-6 text-gray-900">請求データがありません。</p>
                            )}
                        </div>

                        {/* Pagination */}
                        {moneyForwardInvoices.pagination && (
                            <div className="mt-4 flex justify-between items-center">
                                <div>
                                    表示中: {moneyForwardInvoices.pagination.current_page} / {moneyForwardInvoices.pagination.total_pages} ページ ({moneyForwardInvoices.pagination.total_count} 件中)
                                </div>
                                <div className="flex space-x-2">
                                    {/* Add pagination links here */}
                                    {/* Example: <Link href={...}>Previous</Link> <Link href={...}>Next</Link> */}
                                </div>
                            </div>
                        )}

                        {/* Bulk Operations (hidden by default) */}
                        {/* <div className="mt-6 p-4 border rounded-lg bg-gray-50">
                            <h4 className="font-semibold text-gray-700 mb-3">一括操作</h4>
                            <p>選択された項目に対して操作を行います。</p>
                        </div> */}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
