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
        .items-table td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        
        /* Width Definitions */
        .col-desc { width: 40%; }
        .col-sku { width: 15%; text-align: left; }
        .col-ordered { width: 15%; text-align: center; }
        .col-shipped { width: 15%; text-align: center; font-weight: bold; color: #e96a25; }
        .col-remaining { width: 15%; text-align: center; color: #6b7280; }
        
        .footer { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 15px; }
        
        .status-badge {
            background-color: #e96a25;
            color: white;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <!-- Header Rigid Layout -->
    <div class="header-wrapper">
        <table class="layout-table">
            <tr>
                <td style="width: 50%;">
                    <h1 class="company-name">{{ $settings->name ?? 'E-PROCUREMENT SYSTEM' }}</h1>
                    <p style="color: #6b7280; margin-top: 5px; font-size: 11px;">Official Sourcing & Delivery Document</p>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="document-title">DELIVERY NOTE</div>
                    <div style="margin-top: 5px; color: #4b5563; font-size: 12px;">
                        <strong>Shipment Ref:</strong> #DN-{{ str_pad($shipment->id, 6, '0', STR_PAD_LEFT) }}<br>
                        <strong>Order ID:</strong> #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
                        <strong>Date Shipped:</strong> {{ $shipment->created_at->format('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Info Grid Rigid Layout -->
    <div class="info-wrapper">
        <table class="layout-table">
            <tr>
                <td style="width: 55%;">
                    <div class="info-label">SHIP TO CONSIGNEE</div>
                    <p class="info-text bold" style="font-size: 15px; color: #1a2b4c;">{{ $order->shipping_name ?? ($order->company->name ?? 'N/A') }}</p>
                    @if($order->shipping_email)
                        <p class="info-text" style="color: #6b7280;">Email: {{ $order->shipping_email }}</p>
                    @endif
                    <p class="info-text" style="color: #4b5563; margin-top: 5px;">
                        {!! nl2br(e($order->shipping_address ?? ($order->company->address ?? ''))) !!}
                    </p>
                </td>
                <td style="width: 45%; padding-left: 20px;">
                    <div class="info-label">SHIPPING METHOD & CARRIER</div>
                    <div style="background-color: #f9fafb; border: 1px solid #f3f4f6; padding: 15px; rounded-xl; border-radius: 8px; margin-top: 5px;">
                        <table class="layout-table">
                            <tr>
                                <td style="padding-bottom: 6px; font-weight: bold; color: #4b5563;">Carrier:</td>
                                <td style="padding-bottom: 6px; text-align: right; font-weight: bold; color: #111827;">{{ $shipment->carrier->name ?? 'Manual/Custom Delivery' }}</td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 6px; font-weight: bold; color: #4b5563;">Tracking No:</td>
                                <td style="padding-bottom: 6px; text-align: right; font-mono; font-weight: bold; color: #e96a25;">{{ $shipment->tracking_number }}</td>
                            </tr>
                            @if($shipment->notes)
                            <tr>
                                <td colspan="2" style="padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; font-style: italic;">
                                    <strong>Notes:</strong> {{ $shipment->notes }}
                                </td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Items Packing Table -->
    <h3 style="color: #1a2b4c; margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; font-weight: 800;">Fulfillment Packing Slip</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-desc">Item Description</th>
                <th class="col-sku">SKU</th>
                <th class="col-ordered">Ordered Qty</th>
                <th class="col-shipped">Shipped Now</th>
                <th class="col-remaining">Remaining Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shipment->items as $item)
                @php
                    $orderItem = $item->orderItem;
                    $orderedQty = $orderItem->quantity;
                    $shippedInThisDN = $item->quantity;
                    
                    // Calculate total shipped including this shipment to get accurate remaining count
                    $totalShipped = \App\Models\ShipmentItem::where('order_item_id', $orderItem->id)
                        ->where('id', '<=', $item->id)
                        ->sum('quantity');
                        
                    $remainingQty = max(0, $orderedQty - $totalShipped);
                @endphp
                <tr>
                    <td class="col-desc">
                        <div class="bold" style="color: #111827;">{{ $orderItem->product->name ?? 'N/A' }}</div>
                        <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">{{ $orderItem->product->brand ?? '' }}</div>
                    </td>
                    <td class="col-sku">{{ $orderItem->product->sku ?? 'N/A' }}</td>
                    <td class="col-ordered">{{ number_format($orderedQty) }}</td>
                    <td class="col-shipped">{{ number_format($shippedInThisDN) }}</td>
                    <td class="col-remaining">{{ number_format($remainingQty) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 40px; padding: 15px; background-color: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
        <h4 style="margin: 0 0 5px 0; color: #1a2b4c; font-size: 11px; font-weight: bold; text-transform: uppercase;">Consignee Acknowledgment</h4>
        <p style="margin: 0; color: #6b7280; font-size: 10px; line-height: 1.4;">
            Please inspect all received goods carefully upon delivery. Any damage, discrepancies, or missing items must be reported to the supplier within 24 hours of receipt. Kindly sign and return a copy of this delivery note or confirm receipt on your E-Procurement buyer dashboard.
        </p>
        <table class="layout-table" style="margin-top: 30px;">
            <tr>
                <td style="width: 50%; padding-bottom: 20px;">
                    <div style="width: 200px; border-bottom: 1px solid #9ca3af; height: 40px;"></div>
                    <div style="font-size: 10px; color: #6b7280; margin-top: 5px;">Received By (Signature)</div>
                </td>
                <td style="width: 50%; text-align: right; padding-bottom: 20px;">
                    <div style="width: 200px; border-bottom: 1px solid #9ca3af; height: 40px; margin-left: auto;"></div>
                    <div style="font-size: 10px; color: #6b7280; margin-top: 5px;">Date & Time</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer Sticky -->
    <div class="footer">
        Generated autonomously by {{ $settings->name ?? 'E-Procurement Portal' }} on {{ date('d M Y, H:i') }}. This Delivery Note serves as an official proof of packaging and transit.
    </div>
</body>
</html>
