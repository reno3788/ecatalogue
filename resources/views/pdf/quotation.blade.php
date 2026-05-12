<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.5; font-size: 12px; margin: 0; padding: 0; }
        
        /* Explicit Table Resets for Rigidity */
        .layout-table { width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0; }
        .layout-table td { vertical-align: top; border: none; padding: 0; }

        .header-wrapper { padding: 20px 0; border-bottom: 2px solid #f3f4f6; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: 900; color: #1a2b4c; margin: 0; text-transform: uppercase; }
        .document-title { font-size: 28px; font-weight: 800; color: #e96a25; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-wrapper { margin-bottom: 40px; }
        .info-label { font-size: 10px; font-weight: bold; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .info-text { font-size: 13px; color: #111827; margin: 0 0 4px 0; line-height: 1.4; }
        .bold { font-weight: bold; }

        /* Main Item Table */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th { background-color: #1a2b4c; color: white; text-transform: uppercase; font-size: 10px; padding: 12px 15px; text-align: left; font-weight: bold; }
        .items-table td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        
        /* Width Definitions for Matrix Accuracy */
        .col-desc { width: 45%; }
        .col-qty { width: 15%; text-align: center; }
        .col-price { width: 20%; text-align: right; }
        .col-total { width: 20%; text-align: right; font-weight: bold; }
        
        /* Rigidity-First Totals Matrix (Bypassing float traps) */
        .totals-container-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .totals-matrix { width: 35%; border-collapse: collapse; margin-left: auto; }
        .totals-matrix td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .totals-matrix .label-cell { text-align: left; font-weight: bold; color: #4b5563; }
        .totals-matrix .value-cell { text-align: right; font-weight: bold; color: #111827; }
        
        .totals-matrix .grand-row td { border-bottom: none; background-color: #f9fafb; padding: 15px 10px; }
        .totals-matrix .grand-label { font-size: 14px; color: #1a2b4c; font-weight: 900; }
        .totals-matrix .grand-value { font-size: 20px; color: #1a2b4c; font-weight: 900; }
        
        .footer { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 15px; }
    </style>
</head>
<body>
    <!-- Header Rigid Layout -->
    <div class="header-wrapper">
        <table class="layout-table">
            <tr>
                <td style="width: 50%;">
                    <h1 class="company-name">{{ $settings->name ?? 'E-PROCUREMENT SYSTEM' }}</h1>
                    <p style="color: #6b7280; margin-top: 5px; font-size: 11px;">Your Strategic Sourcing Partner</p>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="document-title">OFFICIAL QUOTATION</div>
                    <div style="margin-top: 5px; color: #4b5563; font-size: 12px;">
                        <strong>Order Ref:</strong> #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
                        <strong>Date Issued:</strong> {{ \Carbon\Carbon::now()->format('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Info Grid Rigid Layout -->
    <div class="info-wrapper">
        <table class="layout-table">
            <tr>
                <td style="width: 60%;">
                    <div class="info-label">BILLED TO CLIENT</div>
                    <p class="info-text bold" style="font-size: 15px; color: #1a2b4c;">{{ $order->company->name ?? 'N/A' }}</p>
                    @if($order->company->abeta_id)
                        <p class="info-text">ID: {{ $order->company->abeta_id }}</p>
                    @endif
                    <p class="info-text" style="color: #4b5563;">
                        {!! nl2br(e($order->company->address ?? '')) !!}
                    </p>
                    <p class="info-text" style="margin-top: 10px;">
                        <strong>Attention:</strong> {{ $order->user->name ?? 'Representative' }}<br>
                        {{ $order->user->email ?? '' }}
                    </p>
                </td>
                <td style="width: 40%; text-align: right;">
                    <div class="info-label">STATUS</div>
                    <p class="info-text bold" style="color: #059669; font-size: 16px; margin-bottom: 15px;">VALID QUOTATION</p>
                    <p class="info-text" style="color: #6b7280;">
                        Please review the itemized proposal below.<br>
                        Prices listed are based on your preferred supplier rate.
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Dynamic Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-desc">Item Description</th>
                <th class="col-qty">Quantity</th>
                <th class="col-price">Unit Price</th>
                <th class="col-total">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $currency = $settings->currency ?? '$'; @endphp
            @foreach($order->items as $item)
                <tr>
                    <td class="col-desc">
                        <div class="bold" style="color: #111827;">{{ $item->product->name ?? 'N/A' }}</div>
                        <div style="font-size: 10px; color: #6b7280; margin-top: 3px;">SKU: {{ $item->product->sku ?? 'N/A' }}</div>
                    </td>
                    <td class="col-qty">{{ number_format($item->quantity) }}</td>
                    <td class="col-price">{{ $currency }}{{ number_format($item->price, 2) }}</td>
                    <td class="col-total">{{ $currency }}{{ number_format($item->quantity * $item->price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals Section Rigid Matrix (Forcing Right Alignment using margin-left:auto or wrapping table) -->
    <table class="totals-container-table">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%;">
                <table class="totals-matrix" style="width: 100%;">
                    <tr>
                        <td class="label-cell">Total Value</td>
                        <td class="value-cell">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                    </tr>
                    <tr class="grand-row">
                        <td class="label-cell grand-label">QUOTATION TOTAL</td>
                        <td class="value-cell grand-value">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Terms -->
    <div style="margin-top: 60px;">
        <div class="info-label">TERMS & CONDITIONS</div>
        <ul style="color: #6b7280; font-size: 11px; padding-left: 15px; margin-top: 5px;">
            <li>This quotation is valid for a period of 30 days from the date of issue.</li>
            <li>Payment terms are defined within the existing framework contract agreement.</li>
            <li>Delivery schedule will be finalized upon receipt of official Purchase Order (PO).</li>
        </ul>
    </div>

    <!-- Footer Sticky -->
    <div class="footer">
        Generated autonomously by {{ $settings->name ?? 'E-Procurement Portal' }} on {{ date('d M Y, H:i') }}. This document forms an official quotation reference.
    </div>
</body>
</html>
