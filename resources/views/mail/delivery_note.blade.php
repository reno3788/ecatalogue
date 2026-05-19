<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f4f5f7; color: #1f2937; margin: 0; padding: 20px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }
        .header { background-color: #1a2b4c; padding: 40px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 24px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { color: #93c5fd; font-size: 14px; margin: 5px 0 0 0; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 700; color: #1a2b4c; margin: 0 0 15px 0; }
        .message { font-size: 15px; color: #4b5563; margin-bottom: 30px; }
        
        .tracking-card { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 30px; }
        .tracking-title { font-size: 11px; font-weight: bold; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .tracking-grid { display: table; width: 100%; }
        .tracking-row { display: table-row; }
        .tracking-cell-label { display: table-cell; padding: 6px 0; font-weight: 600; color: #4b5563; font-size: 14px; width: 40%; }
        .tracking-cell-val { display: table-cell; padding: 6px 0; font-weight: 700; color: #111827; font-size: 14px; text-align: right; }
        
        .btn-track { display: inline-block; background-color: #e96a25; color: #ffffff !important; text-decoration: none; padding: 12px 24px; font-size: 14px; font-weight: 700; border-radius: 6px; text-align: center; margin-top: 15px; transition: background-color 0.2s; }
        
        .items-header { font-size: 14px; font-weight: bold; color: #1a2b4c; text-transform: uppercase; border-bottom: 2px solid #f3f4f6; padding-bottom: 8px; margin-bottom: 15px; }
        .item-row { display: table; width: 100%; border-bottom: 1px solid #f3f4f6; padding: 10px 0; }
        .item-name { display: table-cell; font-size: 14px; color: #111827; font-weight: 600; }
        .item-qty { display: table-cell; font-size: 14px; color: #e96a25; font-weight: 700; text-align: right; width: 80px; }
        
        .footer { background-color: #f9fafb; padding: 30px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Shipment Dispatched</h1>
            <p>Order Reference: #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>
        <div class="content">
            <div class="greeting">Hello {{ $order->user->name ?? 'Buyer' }},</div>
            <div class="message">
                Great news! A shipment has been dispatched for your purchase order **#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}**. We have compiled and attached the official **Delivery Note PDF** matching this specific fulfillment batch to this email.
            </div>
            
            <div class="tracking-card">
                <div class="tracking-title">Shipment & Delivery Details</div>
                <div class="tracking-grid">
                    <div class="tracking-row">
                        <div class="tracking-cell-label">Carrier:</div>
                        <div class="tracking-cell-val">{{ $shipment->carrier->name ?? 'Manual Delivery' }}</div>
                    </div>
                    <div class="tracking-row">
                        <div class="tracking-cell-label">Tracking Code:</div>
                        <div class="tracking-cell-val" style="color: #e96a25;">{{ $shipment->tracking_number }}</div>
                    </div>
                    @if($shipment->notes)
                    <div class="tracking-row">
                        <div class="tracking-cell-label">Supplier Notes:</div>
                        <div class="tracking-cell-val">{{ $shipment->notes }}</div>
                    </div>
                    @endif
                </div>
                
                @php
                    $trackingUrl = '';
                    if ($shipment->carrier && $shipment->carrier->tracking_url_pattern) {
                        $trackingUrl = str_replace('{tracking_number}', urlencode($shipment->tracking_number), $shipment->carrier->tracking_url_pattern);
                    }
                @endphp
                
                @if($trackingUrl)
                    <div style="text-align: center;">
                        <a href="{{ $trackingUrl }}" target="_blank" class="btn-track">Track Package Realtime</a>
                    </div>
                @endif
            </div>

            <div class="items-header">Items Included In This Shipment</div>
            @foreach($shipment->items as $item)
                <div class="item-row">
                    <div class="item-name">
                        {{ $item->orderItem->product->name ?? 'N/A' }}
                        <div style="font-size: 11px; color: #9ca3af; font-weight: normal; margin-top: 2px;">SKU: {{ $item->orderItem->product->sku ?? 'N/A' }}</div>
                    </div>
                    <div class="item-qty">Qty: {{ number_format($item->quantity) }}</div>
                </div>
            @endforeach
            
            <div style="margin-top: 30px; font-size: 14px; color: #4b5563;">
                You can view the full details, track delivery status, or download additional documents directly from your E-Procurement Dashboard.
            </div>
        </div>
        <div class="footer">
            This is an automated transaction notification from your E-Procurement Portal. Please do not reply directly to this email.
        </div>
    </div>
</body>
</html>
