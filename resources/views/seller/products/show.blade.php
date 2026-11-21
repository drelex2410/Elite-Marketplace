@extends('seller.layouts.app')

@section('title', $product->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h4 mb-1">{{ $product->getTranslation('name') }}</h1><span class="badge badge-inline badge-soft-info">{{ ucfirst(str_replace('_', ' ', $product->review_status?->value ?? 'draft')) }}</span></div>
        <a href="{{ route('seller.products.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}" class="btn btn-primary">{{ translate('Edit Product') }}</a>
    </div>
    <div class="seller-card">
        <div class="row">
            <div class="col-md-4"><img class="img-fluid rounded" src="{{ uploaded_asset($product->thumbnail_img) }}" onerror="this.src='{{ static_asset('assets/img/placeholder.jpg') }}'" alt="{{ $product->name }}"></div>
            <div class="col-md-8">
                <dl class="row">
                    <dt class="col-sm-4">{{ translate('Price') }}</dt><dd class="col-sm-8">{{ format_price($product->lowest_price) }}</dd>
                    <dt class="col-sm-4">{{ translate('Approved') }}</dt><dd class="col-sm-8">{{ $product->approved ? translate('Yes') : translate('No') }}</dd>
                    <dt class="col-sm-4">{{ translate('Published') }}</dt><dd class="col-sm-8">{{ $product->published ? translate('Yes') : translate('No') }}</dd>
                    <dt class="col-sm-4">{{ translate('Categories') }}</dt><dd class="col-sm-8">{{ $product->categories->pluck('name')->join(', ') }}</dd>
                    @if ($product->review_reason)<dt class="col-sm-4">{{ translate('Review feedback') }}</dt><dd class="col-sm-8 text-danger">{{ $product->review_reason }}</dd>@endif
                </dl>
                <hr>{!! $product->description !!}
            </div>
        </div>
    </div>
@endsection
