@extends('seller.layouts.app')

@section('title', translate('Order').' '.($order->code ?: '#'.$order->id))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div><h1 class="h4 mb-1">{{ translate('Order') }} {{ $order->code ?: $order->combined_order?->code ?: '#'.$order->id }}</h1><p class="text-muted mb-0">{{ optional($order->created_at)->format('d M Y H:i') }}</p></div>
        <div><a class="btn btn-light" target="_blank" href="{{ route('seller.orders.invoice.print', $order) }}">{{ translate('Print') }}</a> <a class="btn btn-primary" href="{{ route('seller.orders.invoice.download', $order) }}">{{ translate('Download Invoice') }}</a></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="seller-card mb-3">
                <h2 class="h6 mb-3">{{ translate('Your order items') }}</h2>
                <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Quantity') }}</th><th>{{ translate('Total') }}</th></tr></thead><tbody>
                    @foreach ($order->orderDetails as $detail)
                        <tr><td>{{ $detail->product?->getTranslation('name') ?: translate('Archived product') }}@if ($detail->variation?->sku)<br><small>{{ translate('SKU') }}: {{ $detail->variation->sku }}</small>@endif</td><td>{{ $detail->quantity }}</td><td>{{ format_price($detail->total) }}</td></tr>
                    @endforeach
                    <tr><th colspan="2" class="text-right">{{ translate('Seller item total') }}</th><th>{{ format_price($order->seller_items_total) }}</th></tr>
                </tbody></table></div>
            </div>

            <div class="seller-card">
                <h2 class="h6 mb-3">{{ translate('Order progress') }}</h2>
                @forelse ($order->order_udpates as $update)<div class="border-left pl-3 mb-3"><strong>{{ $update->note }}</strong><br><small class="text-muted">{{ optional($update->created_at)->format('d M Y H:i') }}</small></div>@empty<p class="text-muted">{{ translate('No progress updates yet.') }}</p>@endforelse
            </div>
        </div>
        <div class="col-lg-4">
            <div class="seller-card mb-3">
                <h2 class="h6">{{ translate('Fulfillment') }}</h2>
                <p><strong>{{ translate('Current status') }}:</strong> {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}<br><strong>{{ translate('Payment') }}:</strong> {{ ucfirst($order->payment_status) }}</p>
                @if ($allowedTransitions)
                    <form method="POST" action="{{ route('seller.orders.update_delivery_status', $order) }}">@csrf @method('PATCH')
                        <label for="next-status">{{ translate('Next authorized state') }}</label>
                        <select id="next-status" class="form-control mb-2" name="status">@foreach ($allowedTransitions as $state)<option value="{{ $state }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</option>@endforeach</select>
                        <button class="btn btn-primary btn-block">{{ translate('Update Fulfillment') }}</button>
                    </form>
                @endif
            </div>

            <div class="seller-card mb-3">
                <h2 class="h6">{{ translate('Delivery address') }}</h2>
                <p class="mb-0">{{ $shippingAddress['name'] ?? $order->user?->name }}<br>{{ $shippingAddress['address'] ?? '' }}<br>{{ collect([$shippingAddress['city'] ?? null, $shippingAddress['state'] ?? null, $shippingAddress['country'] ?? null])->filter()->join(', ') }}<br>{{ $shippingAddress['phone'] ?? '' }}</p>
            </div>

            <div class="seller-card">
                <h2 class="h6">{{ translate('Tracking') }}</h2>
                @if ($shop->isPubliclyVisible() && ! in_array($order->delivery_status, ['delivered', 'cancelled'], true))
                    <form method="POST" action="{{ route('seller.orders.tracking.update', $order) }}">@csrf @method('PATCH')
                        <label for="courier-name">{{ translate('Courier') }}</label><input id="courier-name" class="form-control mb-2" name="courier_name" required maxlength="120" value="{{ old('courier_name', $order->courier_name) }}">
                        <label for="tracking-number">{{ translate('Tracking number') }}</label><input id="tracking-number" class="form-control mb-2" name="tracking_number" required maxlength="191" value="{{ old('tracking_number', $order->tracking_number) }}">
                        <label for="tracking-url">{{ translate('Tracking URL') }}</label><input id="tracking-url" type="url" class="form-control mb-2" name="tracking_url" value="{{ old('tracking_url', $order->tracking_url) }}">
                        <button class="btn btn-outline-primary btn-block">{{ translate('Save Tracking') }}</button>
                    </form>
                @else
                    <p class="mb-0">{{ $order->courier_name }}<br>{{ $order->tracking_number }}@if ($order->tracking_url)<br><a rel="noopener noreferrer" target="_blank" href="{{ $order->tracking_url }}">{{ translate('Track shipment') }}</a>@endif</p>
                @endif
            </div>
        </div>
    </div>
@endsection
