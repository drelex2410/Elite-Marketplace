<aside class="seller-sidebar">
    <div class="seller-shop-card">
        <div class="seller-shop-avatar">
            @if ($shop->logo)<img src="{{ uploaded_asset($shop->logo) }}" alt="{{ $shop->name }}" class="w-100 h-100">@else{{ strtoupper(substr($shop->name, 0, 1)) }}@endif
        </div>
        <div class="font-weight-bold fs-16">{{ $shop->name }}</div>
        <small>{{ $shop->phone ?: auth()->user()->phone }}</small>
    </div>
    <nav aria-label="{{ translate('Seller navigation') }}">
        <ul class="seller-nav">
            <li><a class="{{ request()->routeIs('seller.dashboard') ? 'active' : '' }}" href="{{ route('seller.dashboard') }}"><i class="las la-th-large"></i>{{ translate('Dashboard') }}</a></li>
            <li><a href="{{ route('seller.products') }}"><i class="las la-box"></i>{{ translate('Products') }}</a></li>
            <li><a href="{{ route('seller.package_select') }}"><i class="las la-shield-alt"></i>{{ translate('Packages') }}</a></li>
            <li><a href="{{ route('seller.orders') }}"><i class="las la-list"></i>{{ translate('Orders') }}</a></li>
            <li><a href="{{ route('seller.commission_log.index') }}"><i class="las la-wallet"></i>{{ translate('Earnings') }}</a></li>
            <li><a href="{{ route('seller.payouts.index') }}"><i class="las la-money-check-alt"></i>{{ translate('Payouts') }}</a></li>
            <li><a href="{{ route('seller.coupons.index') }}"><i class="las la-ticket-alt"></i>{{ translate('Coupons') }}</a></li>
            <li><a href="{{ route('seller.querries.index') }}"><i class="las la-comment-alt"></i>{{ translate('Product Queries') }}</a></li>
            <li><a href="{{ route('seller.uploaded_files') }}"><i class="las la-folder"></i>{{ translate('Uploaded Files') }}</a></li>
            <li><a href="{{ route('seller.product_reviews.index') }}"><i class="las la-star"></i>{{ translate('Reviews') }}</a></li>
            <li><a href="{{ route('seller.shop.index') }}"><i class="las la-cog"></i>{{ translate('Shop Settings') }}</a></li>
            <li><a href="{{ route('seller.notification.list') }}"><i class="las la-bell"></i>{{ translate('Notifications') }}</a></li>
        </ul>
    </nav>
</aside>
