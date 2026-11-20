@extends('seller.layouts.app')

@section('title', translate('Seller dashboard'))

@section('content')
    @php
        $status = $shop->status instanceof \App\Enums\ShopStatus ? $shop->status->value : ($shop->status ?: ($shop->approval ? 'approved' : 'pending'));
        $operational = $status === 'active' && $shop->approval && $shop->published;
    @endphp
    <div class="seller-status {{ $operational ? '' : 'is-blocked' }}">
        <strong>{{ $operational ? translate('Shop active') : translate('Application pending') }}</strong>
        — {{ $operational ? translate('Your shop is active and can receive orders.') : translate('You can review your status and complete onboarding while an administrator reviews your application.') }}
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div><h1 class="h3 mb-1">{{ translate('Seller dashboard') }}</h1><p class="text-muted mb-0">{{ translate('A clear view of your shop performance and approval status.') }}</p></div>
        <form method="GET" class="form-inline mt-2 mt-md-0">
            <label for="dashboard-days" class="mr-2">{{ translate('Sales period') }}</label>
            <select id="dashboard-days" name="days" class="form-control" onchange="this.form.submit()">
                @foreach ([30 => '30 days', 90 => '90 days', 365 => '12 months'] as $value => $label)
                    <option value="{{ $value }}" @selected($dashboard['days'] === $value)>{{ translate($label) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <section class="seller-grid" aria-label="{{ translate('Shop summary') }}">
        <article class="seller-card seller-summary"><span>{{ translate('Total Products') }}</span><strong>{{ $dashboard['summary']['total_products'] }}</strong></article>
        <article class="seller-card seller-summary"><span>{{ translate('Total Orders') }}</span><strong>{{ $dashboard['summary']['total_orders'] }}</strong></article>
        <article class="seller-card seller-summary"><span>{{ translate('Net Earnings') }}</span><strong>{{ $dashboard['formatted']['net_earnings'] }}</strong></article>
        <article class="seller-card seller-summary"><span>{{ translate('Gross Sales') }}</span><strong>{{ $dashboard['formatted']['gross_sales'] }}</strong></article>
    </section>

    <section class="seller-content-grid">
        <article class="seller-card">
            <h2 class="h5">{{ translate('Approval status') }}</h2>
            <dl class="mb-0 mt-4">
                <dt>{{ translate('Seller approval') }}</dt><dd>{{ $shop->approval ? translate('Approved') : translate('Pending') }}</dd>
                <dt>{{ translate('Shop activation') }}</dt><dd>{{ ucfirst($status) }}</dd>
                <dt>{{ translate('Shop verification') }}</dt><dd>{{ $shop->verification_status ? translate('Verified') : translate('Pending') }}</dd>
                <dt>{{ translate('Pending product reviews') }}</dt><dd>{{ $dashboard['pending_product_reviews'] }}</dd>
            </dl>
        </article>
        <article class="seller-card">
            <h2 class="h5">{{ translate('Current package') }}</h2>
            <p class="h4 mt-4">{{ $dashboard['package']?->name ?: translate('No package assigned') }}</p>
            <p>{{ translate('Expires') }}: {{ $dashboard['package_expiry']?->format('Y-m-d') ?: translate('Not set') }}</p>
            <p>{{ translate('Product upload') }}: {{ $dashboard['summary']['total_products'] }}/{{ $dashboard['product_upload_limit'] ?: '∞' }}</p>
            <p>{{ translate('Remaining slots') }}: {{ $dashboard['remaining_product_slots'] ?? '∞' }}</p>
            <p>{{ translate('Commission') }}: {{ number_format($dashboard['commission_percentage'], 2) }}%</p>
        </article>
        <div class="seller-status-stack">
            @foreach (['order_placed' => 'Order Placed', 'confirmed' => 'Confirmed Orders', 'processed' => 'Processed Orders', 'delivered' => 'Delivered Orders'] as $key => $label)
                <article class="seller-card seller-status-card"><span>{{ translate($label) }}</span><strong>{{ $dashboard['order_statuses'][$key] }}</strong></article>
            @endforeach
        </div>
        <article class="seller-card">
            <h2 class="h5">{{ translate('Recent Orders') }}</h2>
            @forelse ($dashboard['recent_orders'] as $order)
                <div class="seller-order"><strong>{{ $order->code ?: '#'.$order->id }}</strong><br><small>{{ $order->created_at?->format('M d, Y') }} · {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}</small></div>
            @empty
                <p class="text-muted mt-4">{{ translate('No orders yet.') }}</p>
            @endforelse
        </article>
    </section>

    <section class="seller-card seller-wide">
        <div class="d-flex justify-content-between"><h2 class="h5">{{ translate('Sales statistics') }}</h2><span>{{ translate('Available balance') }}: {{ $dashboard['formatted']['available_balance'] }}</span></div>
        <div class="seller-chart" aria-label="{{ translate('Gross sales chart') }}">
            @forelse ($dashboard['sales_statistics'] as $point)
                @php $height = max(8, min(190, (float) $point->gross_sales)); @endphp
                <div class="seller-chart-bar" style="height: {{ $height }}px" title="{{ $point->sale_date }}: {{ $point->gross_sales }}"></div>
            @empty
                <p class="text-muted align-self-center">{{ translate('Sales data will appear after your first paid order.') }}</p>
            @endforelse
        </div>
    </section>
@endsection
