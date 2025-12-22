import { useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return date;
    return d.toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function toLines(value) {
    if (!value || typeof value !== 'string') return [];
    return value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
}

export default function PurchaseOrderPreview() {
    const { props } = usePage();
    const { estimate, company, client, purchaseOrderNumber } = props;

    const items = Array.isArray(estimate?.items) ? estimate.items : [];
    const totals = useMemo(() => {
        const total = Number(estimate?.total_amount ?? 0);
        const tax = Number(estimate?.tax_amount ?? 0);
        const subtotal = Math.max(total - tax, 0);
        return { subtotal, tax, total };
    }, [estimate?.total_amount, estimate?.tax_amount]);

    const recipientAddressLines = toLines(company?.address);
    const clientAddressLines = toLines(client?.address);
    const deliveryLocationLines = toLines(estimate?.delivery_location);

    const issueDateDisplay = formatDate(estimate?.issue_date) || formatDate(new Date());
    const dueDateDisplay = formatDate(estimate?.delivery_date) || '-';

    return (
        <>
            <Head title="注文書プレビュー" />
            <div className="po-preview">
                <header className="po-header">
                    <div className="po-header-left">
                        <div className="po-recipient-title">
                            {company?.name ?? ''} 御中
                        </div>
                        {recipientAddressLines.length > 0 && (
                            <div className="po-recipient-address">
                                {recipientAddressLines.map((line, index) => (
                                    <div key={index}>{line}</div>
                                ))}
                            </div>
                        )}
                        <div className="po-meta-lines">
                            <div>注文書番号：{purchaseOrderNumber}</div>
                            <div>見積番号：{estimate?.estimate_number ?? '-'}</div>
                            <div>発行日：{issueDateDisplay}</div>
                            <div>納期：{dueDateDisplay}</div>
                        </div>
                    </div>
                    <div className="po-header-right">
                        {company?.logoUrl && (
                            <img src={company.logoUrl} alt="Company logo" className="po-logo" />
                        )}
                        <div className="po-header-contact">
                            {company?.phone && <div>TEL: {company.phone}</div>}
                            {company?.email && <div>MAIL: {company.email}</div>}
                            {company?.website && <div>URL: {company.website}</div>}
                            {estimate?.staff_name && <div>弊社担当：{estimate.staff_name}</div>}
                        </div>
                    </div>
                </header>

                <section className="po-intro">
                    <h2>注 文 書</h2>
                    <p>下記の通りご注文申し上げます。ご確認のうえ、ご対応をお願いいたします。</p>
                </section>

                <section className="po-section">
                    <h3>発注者 <span>Ordering Party</span></h3>
                    <div className="po-two-columns">
                        <div>
                            <dl className="po-info-list">
                                <div className="po-info-row">
                                    <dt>会社名</dt>
                                    <dd>
                                        <div>{client?.name || '（未設定）'}</div>
                                        {clientAddressLines.length > 0 && (
                                            <div className="po-client-inline-address">
                                                {clientAddressLines.map((line, idx) => (
                                                    <div key={idx}>{line}</div>
                                                ))}
                                            </div>
                                        )}
                                    </dd>
                                </div>
                                {client?.contact_name && (
                                    <div className="po-info-row">
                                        <dt>ご担当者</dt>
                                        <dd>
                                            {client?.contact_title ? `${client.contact_title} ` : ''}
                                            {client.contact_name} 様
                                        </dd>
                                    </div>
                                )}
                                {deliveryLocationLines.length > 0 && (
                                    <div className="po-info-row">
                                        <dt>納品場所</dt>
                                        <dd>
                                            {deliveryLocationLines.map((line, idx) => (
                                                <div key={idx}>{line}</div>
                                            ))}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                        <div className="po-order-right">
                            <div className="po-seal-wrapper">
                                <div className="po-seal-box">発注者押印欄</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="po-section">
                    <h3>注文内容 <span>Order Items</span></h3>
                    <table className="po-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>品目 / 内容</th>
                                <th>数量</th>
                                <th>単位</th>
                                <th>単価 (円)</th>
                                <th>金額 (円)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="po-empty">発注する品目が指定されていません。</td>
                                </tr>
                            )}
                            {items.map((item, idx) => {
                                const qty = Number(item?.qty ?? item?.quantity ?? 0);
                                const price = Number(item?.price ?? 0);
                                const amount = Number.isFinite(price * qty) ? price * qty : null;
                                return (
                                    <tr key={`${item?.id ?? idx}`}>
                                        <td>{idx + 1}</td>
                                        <td>
                                            <div className="po-item-name">{item?.name ?? ''}</div>
                                            {item?.description && <div className="po-item-desc">{item.description}</div>}
                                        </td>
                                        <td>{Number.isFinite(qty) ? qty.toLocaleString('ja-JP') : '-'}</td>
                                        <td>{item?.unit ?? '式'}</td>
                                        <td>{Number.isFinite(price) ? price.toLocaleString('ja-JP') : '-'}</td>
                                        <td>{Number.isFinite(amount) ? amount.toLocaleString('ja-JP') : '-'}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <table className="po-totals">
                        <tbody>
                            <tr>
                                <th>小計</th>
                                <td>{totals.subtotal.toLocaleString('ja-JP')} 円</td>
                            </tr>
                            <tr>
                                <th>消費税</th>
                                <td>{totals.tax.toLocaleString('ja-JP')} 円</td>
                            </tr>
                            <tr>
                                <th>合計</th>
                                <td className="po-grand-total">{totals.total.toLocaleString('ja-JP')} 円</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section className="po-section">
                    <h3>備考 <span>Notes</span></h3>
                    <div className="po-notes">
                        {estimate?.notes ? estimate.notes : '特記事項がございましたらご記入ください。'}
                    </div>
                </section>
            </div>
            <style>{`
                @page { size: A4; margin: 15mm; }
                body {
                    font-family: 'Noto Sans JP', 'Yu Gothic', 'Hiragino Sans', sans-serif;
                    background: #f3f4f6;
                    margin: 0;
                    padding: 24px;
                }
                .po-preview {
                    background: #fff;
                    max-width: 210mm;
                    margin: 0 auto;
                    padding: 24px 32px 40px;
                    color: #1f2937;
                }
                .po-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 3px solid #2563eb;
                    padding-bottom: 16px;
                    margin-bottom: 24px;
                    gap: 32px;
                }
                .po-header-left {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    text-align: left;
                    max-width: 60%;
                }
                .po-recipient-title {
                    font-size: 22px;
                    font-weight: 700;
                    margin-bottom: 4px;
                }
                .po-recipient-address {
                    font-size: 12px;
                    line-height: 1.6;
                }
                .po-meta-lines {
                    font-size: 12px;
                    line-height: 1.6;
                }
                .po-meta-lines div {
                    margin: 0;
                }
                .po-header-right {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 12px;
                    text-align: right;
                    min-width: 200px;
                }
                .po-logo {
                    width: 60px;
                    height: auto;
                }
                .po-header-contact {
                    font-size: 11px;
                    line-height: 1.5;
                }
                .po-intro {
                    text-align: center;
                    margin-bottom: 32px;
                }
                .po-intro h2 {
                    font-size: 28px;
                    letter-spacing: 8px;
                    margin: 0;
                }
                .po-intro p {
                    margin-top: 8px;
                    color: #4b5563;
                }
                .po-section {
                    margin-bottom: 28px;
                }
                .po-section h3 {
                    font-size: 15px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 12px;
                    border-left: 4px solid #2563eb;
                    padding-left: 8px;
                }
                .po-section h3 span {
                    font-size: 11px;
                    color: #6b7280;
                    font-weight: 500;
                }
                .po-two-columns {
                    display: flex;
                    justify-content: space-between;
                    gap: 24px;
                    align-items: flex-start;
                }
                .po-info-list {
                    display: grid;
                    gap: 6px;
                    font-size: 12px;
                }
                .po-info-row {
                    display: grid;
                    grid-template-columns: 80px 1fr;
                    gap: 12px;
                    align-items: start;
                }
                .po-info-row dt {
                    font-weight: 600;
                    color: #1f2937;
                }
                .po-info-row dd {
                    margin: 0;
                    color: #111827;
                    line-height: 1.6;
                }
                .po-client-inline-address {
                    margin-top: 6px;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .po-order-right {
                    display: flex;
                    align-items: center;
                    margin-left: auto;
                    padding-left: 16px;
                    border-left: 4px solid #0f172a;
                }
                .po-seal-box {
                    border: 1px dashed #9ca3af;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 140px;
                    height: 70px;
                    color: #9ca3af;
                    font-size: 12px;
                }
                .po-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                    margin-top: 4px;
                }
                .po-table th,
                .po-table td {
                    border: 1px solid #d1d5db;
                    padding: 8px;
                    vertical-align: top;
                }
                .po-table th {
                    background: #f3f4f6;
                    text-align: left;
                }
                .po-table td:nth-child(3),
                .po-table td:nth-child(4),
                .po-table td:nth-child(5),
                .po-table td:nth-child(6),
                .po-table th:nth-child(3),
                .po-table th:nth-child(4),
                .po-table th:nth-child(5),
                .po-table th:nth-child(6) {
                    text-align: right;
                }
                .po-item-name {
                    font-weight: 600;
                }
                .po-item-desc {
                    color: #4b5563;
                    font-size: 11px;
                    margin-top: 4px;
                }
                .po-empty {
                    text-align: center;
                    color: #9ca3af;
                    padding: 24px 0;
                }
                .po-totals {
                    margin-top: 16px;
                    margin-left: auto;
                    width: 40%;
                    border-collapse: collapse;
                }
                .po-totals th,
                .po-totals td {
                    border: 1px solid #d1d5db;
                    padding: 8px;
                }
                .po-totals th {
                    background: #f9fafb;
                    text-align: left;
                }
                .po-totals td {
                    text-align: right;
                }
                .po-grand-total {
                    font-size: 14px;
                    font-weight: 700;
                }
                .po-notes {
                    min-height: 80px;
                    border: 1px solid #d1d5db;
                    background: #f9fafb;
                    padding: 12px;
                    white-space: pre-wrap;
                    font-size: 11px;
                    line-height: 1.6;
                }
                @media print {
                    body {
                        background: #fff;
                        padding: 0;
                    }
                    .po-preview {
                        padding: 16mm;
                        box-shadow: none;
                        max-width: unset;
                    }
                }
            `}</style>
        </>
    );
}
