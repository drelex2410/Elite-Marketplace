<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', translate('Seller dashboard')) | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
    <link rel="stylesheet" href="{{ static_asset('assets/css/aiz-core.css') }}">
    <style>
        :root { --seller-accent: #f5a000; --seller-dark: #111827; --seller-bg: #f7f8fb; }
        body { background: var(--seller-bg); color: #252936; }
        .seller-shell { min-height: 100vh; }
        .seller-topbar { height: 82px; background: #fff; border-bottom: 1px solid #e7e9ef; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; position: sticky; top: 0; z-index: 1030; }
        .seller-brand { color: var(--seller-dark); font-size: 22px; font-weight: 800; }
        .seller-brand span { color: var(--seller-accent); }
        .seller-body { display: grid; grid-template-columns: 280px minmax(0, 1fr); }
        .seller-sidebar { background: #fff; border-right: 1px solid #e7e9ef; min-height: calc(100vh - 82px); padding: 18px; }
        .seller-shop-card { background: var(--seller-dark); color: #fff; border-radius: 8px; padding: 22px; text-align: center; margin-bottom: 18px; }
        .seller-shop-avatar { width: 70px; height: 70px; border-radius: 50%; background: var(--seller-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; margin-bottom: 10px; overflow: hidden; }
        .seller-nav { list-style: none; margin: 0; padding: 10px 0; }
        .seller-nav a { color: #555b66; display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 7px; font-weight: 600; }
        .seller-nav a:hover, .seller-nav a.active { color: #fff; background: var(--seller-accent); }
        .seller-main { padding: 26px; min-width: 0; }
        .seller-status { border-radius: 7px; padding: 14px 18px; margin-bottom: 20px; background: #d9f2f7; color: #176374; }
        .seller-status.is-blocked { background: #fff2d4; color: #805b00; }
        .seller-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 22px; }
        .seller-card { background: #fff; border: 1px solid #e4e6ec; border-radius: 8px; padding: 24px; box-shadow: 0 12px 30px rgba(24, 31, 52, .05); }
        .seller-summary { background: var(--seller-accent); color: #fff; min-height: 150px; display: flex; flex-direction: column; justify-content: center; border: 0; }
        .seller-summary strong { font-size: 32px; line-height: 1.15; }
        .seller-summary span { font-weight: 700; margin-bottom: 8px; }
        .seller-content-grid { display: grid; grid-template-columns: 1.1fr 1.1fr 1fr 1fr; gap: 22px; margin-top: 24px; }
        .seller-status-stack { display: grid; gap: 14px; }
        .seller-status-card { display: flex; justify-content: space-between; align-items: center; min-height: 86px; }
        .seller-status-card strong { color: var(--seller-accent); font-size: 22px; }
        .seller-order { padding: 12px 0; border-bottom: 1px solid #eceef2; }
        .seller-order:last-child { border-bottom: 0; }
        .seller-wide { grid-column: 1 / -1; margin-top: 24px; }
        .seller-chart { min-height: 220px; display: flex; align-items: end; gap: 7px; padding-top: 20px; }
        .seller-chart-bar { background: var(--seller-accent); min-width: 12px; flex: 1; border-radius: 4px 4px 0 0; opacity: .85; }
        @media (max-width: 1200px) { .seller-grid, .seller-content-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 768px) { .seller-topbar { height: auto; padding: 16px; flex-wrap: wrap; gap: 12px; } .seller-body { display: block; } .seller-sidebar { min-height: auto; } .seller-main { padding: 16px; } .seller-grid, .seller-content-grid { grid-template-columns: 1fr; } }
    </style>
    @stack('styles')
</head>
<body>
<div class="seller-shell">
    <header class="seller-topbar">
        <a class="seller-brand" href="{{ route('seller.dashboard') }}"><span>ELITE</span> MARKETPLACE</a>
        <div class="d-flex align-items-center gap-3">
            <a class="btn btn-outline-secondary" href="{{ route('home') }}">{{ translate('Browse Website') }}</a>
            <span class="font-weight-bold">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-light" type="submit">{{ translate('Logout') }}</button></form>
        </div>
    </header>
    <div class="seller-body">
        @include('seller.partials.sidebar')
        <main class="seller-main">
            @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ static_asset('assets/js/vendors.js') }}"></script>
<script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>
@yield('modal')
@yield('script')
@stack('scripts')
</body>
</html>
