<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>見積書</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #2C3E50;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .page-break {
            page-break-after: always;
        }
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .text-xl { font-size: 1.25rem; }
        .text-2xl { font-size: 1.5rem; }
        .text-3xl { font-size: 1.875rem; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }

        .teal { color: #00A693; }
        .teal-bg { background-color: #00A693; }
        .orange { color: #FF8C42; }
        .dark-gray { color: #2C3E50; }
        .light-gray { color: #ECF0F1; }
        .light-gray-bg { background-color: #f8f9fa; }

        /* ヘッダースタイル */
        .header-section {
            padding: 24px;
            border-bottom: 3px solid #00A693;
            margin-bottom: 24px;
            position: relative;
        }
        
        .header-layout {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .customer-info {
            flex: 1;
            padding-right: 80px;
        }
        
        .company-info {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
            max-width: 300px;
        }
        
        .estimate-number {
            font-size: 12px;
            color: #00A693;
            margin-bottom: 8px;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #00A693;
            margin-bottom: 4px;
        }
        
        .date-info {
            display: flex;
            gap: 24px;
            margin-top: 20px;
            font-size: 11px;
        }
        
        .subject-info {
            margin-top: 16px;
        }

        /* タイトルスタイル */
        .title-section {
            text-align: center;
            padding: 0 0 24px 0; /* top | right | bottom | left */
            margin-bottom: 24px;
        }
        
        .title-underline {
            width: 128px;
            height: 4px;
            background-color: #FF8C42;
            margin: 8px auto 0;
        }

        /* 件名セクション */
        .subject-section {
            padding: 24px;
            border-bottom: 1px solid #ECF0F1;
            margin-bottom: 24px;
        }

        /* 明細テーブル */
        .details-section {
            padding: 0 24px;
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2C3E50;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        
        .title-bar {
            width: 4px;
            height: 24px;
            background-color: #00A693;
            margin-right: 12px;
        }
        
        .quotation-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .quotation-table th {
            border: 1px solid #ECF0F1;
            border-bottom: 2px solid #00A693;
            padding: 12px 8px;
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2C3E50;
        }
        
        .quotation-table td {
            border: 1px solid #ECF0F1;
            padding: 12px 8px;
            vertical-align: top;
        }
        
        .quotation-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* 合計テーブル */
        .total-section {
            padding: 0 24px;
            margin-bottom: 24px;
        }

        /* フッター */
        .footer-section {
            padding: 24px;
            font-size: 11px;
            clear: both;
        }
        
        .footer-grid {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .footer-column {
            flex: 1;
        }
        
        .footer-title {
            font-weight: bold;
            color: #2C3E50;
            border-bottom: 2px solid #00A693;
            padding-bottom: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .footer-title-bar {
            width: 4px;
            height: 16px;
            background-color: #FF8C42;
            margin-right: 8px;
        }
        
        .footer-full-width {
            margin-top: 24px;
        }
        
        .decorative-line {
            text-align: center;
            margin-top: 32px;
        }
        
        .line-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .line-teal {
            width: 48px;
            height: 2px;
            background-color: #00A693;
        }
        
        .line-orange {
            width: 24px;
            height: 2px;
            background-color: #FF8C42;
        }

        @media print {
            body {
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container {
                max-width: none;
                margin: 0;
            }
        }
        
        @page {
            size: A4;
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ヘッダー -->
        <div class="header-section">
            <div class="header-layout">
                <!-- 左側：顧客情報 -->
                <div class="customer-info">
                    <div class="text-2xl font-bold dark-gray">{{ $estimateData['customer_name'] ?? '' }} 御中</div>
                    @if(!empty($estimateData['client_contact_name']) || !empty($estimateData['client_contact_title']))
                    <div class="text-sm teal" style="margin-top: 8px;">
                        ご担当者様：{{ trim(($estimateData['client_contact_title'] ?? '') . ' ' . ($estimateData['client_contact_name'] ?? '')) }} 様
                    </div>
                    @endif
                    
                    <!-- 発行日・有効期限 -->
                    <div class="date-info">
                        <div>
                            <span class="font-bold teal">発行日：</span>
                            <span class="dark-gray">{{ $estimateData['issue_date'] ?? '' }}</span>
                        </div>
                        <div>
                            <span class="font-bold teal">有効期限：</span>
                            <span class="font-bold orange">{{ $estimateData['expiry_date'] ?? '' }}</span>
                        </div>
                    </div>
                    
                    <!-- 件名 -->
                    <div class="subject-info">
                        <span class="font-bold teal">件名：</span>
                        <span class="text-lg dark-gray">{{ $estimateData['project_name'] ?? '' }}</span>
                    </div>
                </div>

                <!-- 右側：自社情報 -->
                <div class="company-info">
                    <div class="estimate-number">見積番号：{{ $estimateData['estimate_number'] ?? '' }}</div>
                    <div class="company-name">株式会社テックソリューション</div>
                    <div>〒100-0001 東京都千代田区千代田1-1-1</div>
                    <div>TEL: 03-1234-5678</div>
                    <div>FAX: 03-1234-5679</div>
                    <div class="orange">info@techsolution.co.jp</div>
                    <div class="orange">https://www.techsolution.co.jp</div>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ECF0F1;">
                        <span class="teal">担当者：</span>営業部 {{ $estimateData['staff_name'] ?? '' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- タイトル -->
        <div class="title-section">
            <h1 class="text-3xl font-bold dark-gray" style="margin-top: 0;">見 積 書</h1>
            <div class="title-underline"></div>
        </div>

        <!-- 明細 -->
        <div class="details-section">
            <h2 class="section-title">
                <div class="title-bar"></div>
                明細
            </h2>
            <table class="quotation-table">
                <thead>
                    <tr>
                        <th style="width: 32px;" class="text-center">No.</th>
                        <th style="min-width: 200px;">品目名・詳細</th>
                        <th style="width: 64px;" class="text-center">数量</th>
                        <th style="width: 64px;" class="text-center">単位</th>
                        <th style="width: 96px;" class="text-right">単価</th>
                        <th style="width: 96px;" class="text-right">金額</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($estimateData['lineItems'] ?? [] as $index => $item)
                    @php
                        $calcQty = (float) ($item['qty'] ?? 0);
                        $calcPrice = (float) ($item['price'] ?? 0);
                        $lineAmount = $calcQty * $calcPrice;
                        $displayQty = $calcQty;
                        $displayUnit = $item['unit'] ?? '';
                        $displayPrice = $calcPrice;
                        if (($item['display_mode'] ?? 'calculated') === 'lump') {
                            $displayQty = (float) ($item['display_qty'] ?? 1);
                            if ($displayQty <= 0) {
                                $displayQty = 1;
                            }
                            $displayUnit = $item['display_unit'] ?? '式';
                            $displayPrice = $displayQty !== 0.0 ? $lineAmount / $displayQty : $lineAmount;
                        }
                        $formattedQty = rtrim(rtrim(number_format($displayQty, 2, '.', ''), '0'), '.');
                    @endphp
                    <tr>
                        <td class="text-center teal">{{ $index + 1 }}</td>
                        <td>
                            <div class="font-bold dark-gray">{{ $item['name'] ?? '' }}</div>
                            @if (!empty($item['description']))
                                <div class="text-sm teal">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td class="text-center dark-gray">{{ $formattedQty }}</td>
                        <td class="text-center dark-gray">{{ $displayUnit }}</td>
                        <td class="text-right dark-gray">¥{{ number_format($displayPrice) }}</td>
                        <td class="text-right font-bold orange">¥{{ number_format($lineAmount) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- 合計 -->
        <div class="total-section">
             <table style="width: 100%; border-collapse: collapse; float: right;">
                <tr>
                    <td style="width: 40%;"></td>
                    <td style="width: 60%; padding: 0;">
                        <table style="width: 100%; border-collapse: collapse; border: 2px solid #00A693;">
                            <tr>
                                <td style="padding: 8px; text-align: center; border-right: 1px solid #ECF0F1;">
                                    <span style="font-size: 0.8rem;">小計（税抜）</span><br>
                                    <span style="font-weight: bold;">¥{{ number_format($estimateData['subtotal'] ?? 0) }}</span>
                                </td>
                                <td style="padding: 8px; text-align: center; border-right: 1px solid #ECF0F1;">
                                    <span style="font-size: 0.8rem;">消費税（10%）</span><br>
                                    <span style="font-weight: bold;">¥{{ number_format($estimateData['tax'] ?? 0) }}</span>
                                </td>
                                <td style="padding: 8px; text-align: center; background-color: black; color: white;">
                                    <span style="font-size: 0.8rem;">合計金額</span><br>
                                    <span style="font-weight: bold; font-size: 1.2rem;">¥{{ number_format($estimateData['total'] ?? 0) }}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- フッター -->
        <div class="footer-section">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3 class="footer-title">
                        <div class="footer-title-bar"></div>
                        支払条件
                    </h3>
                    <div class="dark-gray">{{ $estimateData['payment_terms'] ?? '' }}</div>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">
                        <div class="footer-title-bar"></div>
                        納入場所
                    </h3>
                    <div class="dark-gray">{{ $estimateData['delivery_location'] ?? '' }}</div>
                </div>
            </div>
            
            <div class="footer-full-width">
                <h3 class="footer-title">
                    <div class="footer-title-bar"></div>
                    備考
                </h3>
                <div class="dark-gray" style="white-space: pre-wrap;">{{ $estimateData['external_remarks'] ?? '' }}</div>
            </div>

            <!-- 装飾的なライン -->
            <div class="decorative-line">
                <div class="line-group">
                    <div class="line-teal"></div>
                    <div class="line-orange"></div>
                    <div class="line-teal"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
