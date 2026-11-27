@extends('seller.layouts.app')

@section('title', $pickupOnly ? translate('Pickup Orders') : translate('Orders'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div><h1 class="h4 mb-1">{{ $pickupOnly ? translate('Pickup Orders') : translate('Orders') }}</h1><p class="text-muted mb-0">{{ translate('Only order items belonging to your shop are shown.') }}</p></div>
        <div><a class="btn {{ $pickupOnly ? 'btn-light' : 'btn-primary' }}" href="{{ route('seller.orders') }}">{{ translate('All Orders') }}</a> <a class="btn {{ $pickupOnly ? 'btn-primary' : 'btn-light' }}" href="{{ route('seller.pickup.orders') }}">{{ translate('Pickup Orders') }}</a></div>
    </div>

    <div class="seller-card">
        <form method="GET" class="row gutters-5 mb-3">
            <div class="col-lg-4"><label class="sr-only" for="order-search">{{ translate('Search orders') }}</label><input id="order-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('Order code') }}"></div>
            <div class="col-lg-3"><label class="sr-only" for="delivery-status">{{ translate('Delivery status') }}</label><select id="delivery-status" class="form-control" name="delivery_status"><option value="">{{ translate('All fulfillment states') }}</option>@foreach (['order_placed','confirmed','processed','shipped','ready_for_pickup','picked_up','delivered','cancelled'] as $state)<option value="{{ $state }}" @selected(request('delivery_status') === $state)>{{ ucfirst(str_replace('_', ' ', $state)) }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label class="sr-only" for="payment-status">{{ translate('Payment status') }}</label><select id="payment-status" class="form-control" name="payment_status"><option value="">{{ translate('All payment states') }}</option><option value="paid" @selected(request('payment_status') === 'paid')>{{ translate('Paid') }}</option><option value="unpaid" @selected(request('payment_status') === 'unpaid')>{{ translate('Unpaid') }}</option></select></div>
            <div class="col-lg-2"><button class="btn btn-outline-secondary btn-block">{{ translate('Filter') }}</button></div>
        </form>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>{{ translate('Order') }}</th><th>{{ translate('Customer') }}</th><th>{{ translate('Your item total') }}</th><th>{{ translate('Payment') }}</th><th>{{ translate('Fulfillment') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><strong>{{ $order->code ?: $order->combined_order?->code ?: '#'.$order->id }}</strong></td>
                        <td>{{ $order->user?->name ?: translate('Guest customer') }}</td>
                        <td>{{ format_price($order->orderDetails->sum('total')) }}</td>
                        <td><span class="badge badge-inline badge-soft-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($order->payment_status) }}</span></td>
                        <td>{{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}</td>
                        <td>{{ optional($order->created_at)->format('d M Y') }}</td>
                        <td class="text-right"><a class="btn btn-soft-primary btn-sm" href="{{ route('seller.orders_show', $order) }}">{{ translate('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">{{ translate('No seller orders match these filters.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
@endsection
