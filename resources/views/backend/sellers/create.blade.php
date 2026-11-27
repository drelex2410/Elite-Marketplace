@extends('backend.layouts.app')
@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Add New Seller') }}</h1></div>
<form method="POST" action="{{ route('admin.seller.store') }}">@csrf @include('backend.sellers.partials.form')</form>
@endsection
