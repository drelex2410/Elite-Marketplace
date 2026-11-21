@extends('seller.layouts.app')

@section('title', translate('Products'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">{{ translate('Products') }}</h1>
            <p class="text-muted mb-0">{{ translate('Create drafts, submit products for review, and manage approved listings.') }}</p>
        </div>
        <a href="{{ route('seller.products.create') }}" class="btn btn-primary">{{ translate('Add New Product') }}</a>
    </div>

    <div class="seller-card">
        <form method="GET" class="row gutters-5 mb-3">
            <div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('Search products') }}"></div>
            <div class="col-md-4">
                <select class="form-control" name="status">
                    <option value="">{{ translate('All review statuses') }}</option>
                    @foreach (\App\Enums\ProductReviewStatus::cases() as $reviewStatus)
                        <option value="{{ $reviewStatus->value }}" @selected(request('status') === $reviewStatus->value)>{{ ucfirst(str_replace('_', ' ', $reviewStatus->value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-outline-secondary btn-block">{{ translate('Filter') }}</button></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Price') }}</th><th>{{ translate('Review') }}</th><th>{{ translate('Visibility') }}</th><th class="text-right">{{ translate('Actions') }}</th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    @php($status = $product->review_status?->value ?? 'draft')
                    <tr>
                        <td><div class="d-flex align-items-center"><img class="size-50px rounded mr-2" src="{{ uploaded_asset($product->thumbnail_img) }}" onerror="this.src='{{ static_asset('assets/img/placeholder.jpg') }}'" alt=""><div><strong>{{ $product->getTranslation('name') }}</strong><br><small class="text-muted">#{{ $product->id }}</small></div></div></td>
                        <td>{{ format_price($product->lowest_price) }}</td>
                        <td><span class="badge badge-inline badge-soft-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>@if ($product->review_reason)<div class="small text-danger mt-1">{{ $product->review_reason }}</div>@endif</td>
                        <td>
                            @if ($product->approved)
                                <label class="aiz-switch aiz-switch-success mb-0"><input type="checkbox" class="js-product-visibility" value="{{ $product->id }}" @checked($product->published)><span class="slider round"></span></label>
                            @else
                                <span class="text-muted">{{ translate('Private') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ route('seller.product.show', $product) }}" title="{{ translate('View') }}"><i class="las la-eye"></i></a>
                            <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="{{ route('seller.products.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}" title="{{ translate('Edit') }}"><i class="las la-edit"></i></a>
                            <form class="d-inline" method="POST" action="{{ route('seller.product.duplicate', $product) }}">@csrf<button class="btn btn-soft-success btn-icon btn-circle btn-sm" title="{{ translate('Duplicate') }}"><i class="las la-copy"></i></button></form>
                            <form class="d-inline" method="POST" action="{{ route('seller.product.destroy', $product) }}" onsubmit="return confirm('{{ translate('Delete this product?') }}')">@csrf @method('DELETE')<button class="btn btn-soft-danger btn-icon btn-circle btn-sm" title="{{ translate('Delete') }}"><i class="las la-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">{{ translate('No products found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
    </div>
@endsection

@section('script')
<script>
document.querySelectorAll('.js-product-visibility').forEach(function (input) {
    input.addEventListener('change', function () {
        fetch(@json(route('seller.product.published')), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
            body: JSON.stringify({id: this.value, status: this.checked ? 1 : 0})
        }).then(function (response) {
            if (!response.ok) throw new Error('Request failed');
        }).catch(function () { window.location.reload(); });
    });
});
</script>
@endsection
