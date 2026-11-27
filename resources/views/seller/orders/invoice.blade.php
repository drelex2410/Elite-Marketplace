<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><title>{{ translate('Seller Invoice') }} {{ $order->code }}</title><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:12px}.header{display:flex;justify-content:space-between;border-bottom:2px solid #f5a000;padding-bottom:14px;margin-bottom:20px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left}.total{text-align:right;font-size:14px;margin-top:16px}.muted{color:#666}</style></head>
<body>
    <div class="header"><div><h1>{{ config('app.name') }}</h1><div>{{ $order->shop?->name }}</div></div><div><strong>{{ translate('Seller order invoice') }}</strong><br>{{ $order->code ?: '#'.$order->id }}<br><span class="muted">{{ optional($order->created_at)->format('d M Y') }}</span></div></div>
    <div><strong>{{ translate('Deliver to') }}</strong><br>{{ $shippingAddress['name'] ?? $order->user?->name }}<br>{{ $shippingAddress['address'] ?? '' }}<br>{{ collect([$shippingAddress['city'] ?? null, $shippingAddress['state'] ?? null, $shippingAddress['country'] ?? null])->filter()->join(', ') }}<br>{{ $shippingAddress['phone'] ?? '' }}</div>
    <table><thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Quantity') }}</th><th>{{ translate('Total') }}</th></tr></thead><tbody>@foreach ($order->orderDetails as $detail)<tr><td>{{ $detail->product?->getTranslation('name') ?: translate('Archived product') }}</td><td>{{ $detail->quantity }}</td><td>{{ format_price($detail->total) }}</td></tr>@endforeach</tbody></table>
    <div class="total"><strong>{{ translate('Seller item total') }}: {{ format_price($order->seller_items_total) }}</strong></div>
</body>
</html>
