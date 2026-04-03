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
    return value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
}

function formatQuantity(value) {
    if (!Number.isFinite(value)) return '-';
    if (Number.isInteger(value)) {
        return value.toLocaleString('ja-JP');
    }
    const fixed = value.toFixed(2);
    return fixed.replace(/\.?0+$/, '');
}

export default function AcceptancePreview() {
    const { props } = usePage();
    const { estimate, company, client, purchaseOrderNumber, acceptanceNumber } = props;

    const items = Array.isArray(estimate?.items) ? estimate.items : [];
    const totals = useMemo(() => {
        const total = Number(estimate?.total_amount ?? 0);
        const tax = Number(estimate?.tax_amount ?? 0);
        const subtotal = Math.max(total - tax, 0);
        return { subtotal, tax, total };
    }, [estimate?.total_amount, estimate?.tax_amount]);

    const companyAddressLines = toLines(company?.address);
    const clientAddressLines = toLines(client?.address);
    const deliveryLocationLines = toLines(estimate?.delivery_location);

    const issueDateDisplay = formatDate(estimate?.issue_date) || formatDate(new Date());
    const deliveryDateDisplay = formatDate(estimate?.delivery_date) || '-';
    const startDateDisplay = formatDate(estimate?.start_date) || '-';

    return (
        <>
            <Head title="検収書プレビュー" />
            <div className="acceptance-preview">
                <header className="acceptance-header">
                    <div className="acceptance-header-left">
                        <div className="acceptance-recipient-title">
                            {client?.name ?? ''} 御中
                        </div>
                        {clientAddressLines.length > 0 && (
                            <div className="acceptance-recipient-address">
                                {clientAddressLines.map((line, index) => (
                                    <div key={index}>{line}</div>
                                ))}
                            </div>
                        )}
                        <div className="acceptance-meta-lines">
                            <div>検収書番号：{acceptanceNumber}</div>
                            <div>注文書番号：{purchaseOrderNumber}</div>
                            <div>見積番号：{estimate?.estimate_number ?? '-'}</div>
                            <div>発行日：{issueDateDisplay}</div>
                        </div>
                    </div>
                    <div className="acceptance-header-right">
                        {company?.logoUrl && (
                            <img src={company.logoUrl} alt="Company logo" className="acceptance-logo" />
                        )}
                        <div className="acceptance-header-contact">
                            <div className="acceptance-company-name">{company?.name ?? ''}</div>
                            {companyAddressLines.map((line, index) => (
                                <div key={index}>{line}</div>
                            ))}
                            {company?.phone && <div>TEL: {company.phone}</div>}
                            {company?.email && <div>MAIL: {company.email}</div>}
                            {company?.website && <div>URL: {company.website}</div>}
                            {estimate?.staff_name && <div>担当：{estimate.staff_name}</div>}
                        </div>
                    </div>
                </header>

                <section className="acceptance-intro">
                    <h2>検 収 書</h2>
                    <p>下記内容について納品・作業完了いたしましたので、ご確認をお願いいたします。</p>
                </section>

                <section className="acceptance-summary">
                    <div className="acceptance-summary-card">
                        <div className="acceptance-summary-label">件名</div>
                        <div className="acceptance-summary-value">{estimate?.title || '—'}</div>
                    </div>
                    <div className="acceptance-summary-grid">
                        <div>
                            <div className="acceptance-summary-label">着手日</div>
                            <div className="acceptance-summary-value">{startDateDisplay}</div>
                        </div>
                        <div>
                            <div className="acceptance-summary-label">納品日</div>
                            <div className="acceptance-summary-value">{deliveryDateDisplay}</div>
                        </div>
                        <div>
                            <div className="acceptance-summary-label">ご担当者</div>
                            <div className="acceptance-summary-value">
                                {client?.contact_name
                                    ? `${client?.contact_title ? `${client.contact_title} ` : ''}${client.contact_name} 様`
                                    : '—'}
                            </div>
                        </div>
                    </div>
                    {deliveryLocationLines.length > 0 && (
                        <div className="acceptance-summary-card">
                            <div className="acceptance-summary-label">納品場所</div>
                            <div className="acceptance-summary-value">
                                {deliveryLocationLines.map((line, idx) => (
                                    <div key={idx}>{line}</div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>

                <section className="acceptance-section">
                    <h3>検収対象明細</h3>
                    <table className="acceptance-table">
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
                                    <td colSpan={6} className="acceptance-empty">検収対象の明細がありません。</td>
                                </tr>
                            )}
                            {items.map((item, idx) => {
                                const qty = Number(item?.qty ?? item?.quantity ?? 0);
                                const price = Number(item?.price ?? 0);
                                const amount = Number.isFinite(price * qty) ? price * qty : null;
                                let displayQty = qty;
                                let displayUnit = item?.unit ?? '';
                                let displayPrice = price;
                                if (item?.display_mode === 'lump') {
                                    const displayQtyCandidate = Number(item?.display_qty ?? 1);
                                    displayQty = displayQtyCandidate > 0 ? displayQtyCandidate : 1;
                                    displayUnit = item?.display_unit ?? '式';
                                    displayPrice = displayQty !== 0 ? amount / displayQty : amount;
                                }
                                return (
                                    <tr key={`${item?.id ?? idx}`}>
                                        <td>{idx + 1}</td>
                                        <td>
                                            <div className="acceptance-item-name">{item?.name ?? ''}</div>
                                            {item?.description && <div className="acceptance-item-desc">{item.description}</div>}
                                        </td>
                                        <td>{formatQuantity(displayQty)}</td>
                                        <td>{displayUnit || '式'}</td>
                                        <td>{Number.isFinite(displayPrice) ? Math.round(displayPrice).toLocaleString('ja-JP') : '-'}</td>
                                        <td>{Number.isFinite(amount) ? Math.round(amount).toLocaleString('ja-JP') : '-'}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <table className="acceptance-totals">
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
                                <td className="acceptance-grand-total">{totals.total.toLocaleString('ja-JP')} 円</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section className="acceptance-section">
                    <h3>確認欄</h3>
                    <div className="acceptance-confirm-grid">
                        <div className="acceptance-confirm-box">
                            <div className="acceptance-summary-label">検収日</div>
                            <div className="acceptance-blank-line" />
                        </div>
                        <div className="acceptance-confirm-box">
                            <div className="acceptance-summary-label">ご確認者</div>
                            <div className="acceptance-blank-line" />
                        </div>
                        <div className="acceptance-confirm-box">
                            <div className="acceptance-summary-label">署名 / 押印</div>
                            <div className="acceptance-stamp-box" />
                        </div>
                    </div>
                </section>

                <section className="acceptance-section">
                    <h3>備考</h3>
                    <div className="acceptance-notes">
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
                .acceptance-preview {
                    background: #fff;
                    max-width: 210mm;
                    margin: 0 auto;
                    padding: 24px 32px 40px;
                    color: #1f2937;
                }
                .acceptance-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 3px solid #0f766e;
                    padding-bottom: 16px;
                    margin-bottom: 24px;
                    gap: 32px;
                }
                .acceptance-header-left {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    text-align: left;
                    max-width: 58%;
                }
                .acceptance-recipient-title {
                    font-size: 22px;
                    font-weight: 700;
                    margin-bottom: 4px;
                }
                .acceptance-recipient-address,
                .acceptance-meta-lines,
                .acceptance-header-contact {
                    font-size: 12px;
                    line-height: 1.7;
                }
                .acceptance-header-right {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 12px;
                    text-align: right;
                    min-width: 220px;
                }
                .acceptance-company-name {
                    font-size: 15px;
                    font-weight: 700;
                }
                .acceptance-logo {
                    max-width: 180px;
                    max-height: 72px;
                    object-fit: contain;
                }
                .acceptance-intro {
                    text-align: center;
                    margin-bottom: 24px;
                }
                .acceptance-intro h2 {
                    font-size: 32px;
                    letter-spacing: 0.4em;
                    margin: 0 0 12px;
                    color: #0f172a;
                }
                .acceptance-intro p {
                    margin: 0;
                    font-size: 13px;
                    color: #475569;
                }
                .acceptance-summary {
                    display: grid;
                    gap: 12px;
                    margin-bottom: 24px;
                }
                .acceptance-summary-card,
                .acceptance-summary-grid,
                .acceptance-confirm-box,
                .acceptance-notes {
                    border: 1px solid #cbd5e1;
                    border-radius: 12px;
                    padding: 14px 16px;
                    background: #f8fafc;
                }
                .acceptance-summary-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 12px;
                }
                .acceptance-summary-label {
                    font-size: 11px;
                    color: #475569;
                    margin-bottom: 6px;
                }
                .acceptance-summary-value {
                    font-size: 14px;
                    font-weight: 600;
                    color: #0f172a;
                    line-height: 1.6;
                }
                .acceptance-section {
                    margin-top: 24px;
                }
                .acceptance-section h3 {
                    font-size: 18px;
                    margin: 0 0 12px;
                    color: #0f172a;
                }
                .acceptance-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                }
                .acceptance-table th,
                .acceptance-table td {
                    border: 1px solid #cbd5e1;
                    padding: 10px 8px;
                    vertical-align: top;
                }
                .acceptance-table th {
                    background: #e2e8f0;
                    font-weight: 700;
                }
                .acceptance-empty {
                    text-align: center;
                    color: #64748b;
                    padding: 20px 12px;
                }
                .acceptance-item-name {
                    font-weight: 700;
                    margin-bottom: 4px;
                }
                .acceptance-item-desc {
                    color: #475569;
                    white-space: pre-wrap;
                }
                .acceptance-totals {
                    width: 280px;
                    margin-left: auto;
                    margin-top: 16px;
                    border-collapse: collapse;
                    font-size: 12px;
                }
                .acceptance-totals th,
                .acceptance-totals td {
                    border: 1px solid #cbd5e1;
                    padding: 10px 12px;
                }
                .acceptance-totals th {
                    background: #f8fafc;
                    text-align: left;
                    width: 40%;
                }
                .acceptance-grand-total {
                    font-size: 18px;
                    font-weight: 700;
                    color: #0f766e;
                }
                .acceptance-confirm-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 12px;
                }
                .acceptance-blank-line {
                    height: 32px;
                    border-bottom: 1px solid #94a3b8;
                }
                .acceptance-stamp-box {
                    height: 72px;
                    border: 1px dashed #94a3b8;
                    border-radius: 8px;
                    background: #fff;
                }
                .acceptance-notes {
                    min-height: 84px;
                    white-space: pre-wrap;
                    font-size: 12px;
                    line-height: 1.8;
                }
                @media print {
                    body {
                        background: #fff;
                        padding: 0;
                    }
                    .acceptance-preview {
                        margin: 0;
                        max-width: none;
                        padding: 0;
                    }
                }
            `}</style>
        </>
    );
}
