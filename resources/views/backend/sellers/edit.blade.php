@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><div class="row align-items-center"><div class="col"><h1 class="h3">{{ translate('Edit Seller') }}: {{ $seller->name }}</h1></div><div class="col text-right"><span class="badge badge-inline badge-info">{{ $seller->shop?->status?->value ?? $seller->shop?->status }}</span></div></div></div>
<form method="POST" action="{{ route('admin.seller.update') }}">@csrf<input type="hidden" name="seller_id" value="{{ $seller->id }}">@include('backend.sellers.partials.form')</form>
@endsection
