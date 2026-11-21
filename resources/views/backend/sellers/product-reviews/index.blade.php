@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3"><h1 class="h3">{{ translate('Seller Product Reviews') }}</h1></div>
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row gutters-5 w-100">
                <div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('Product or shop') }}"></div>
                <div class="col-md-4"><select class="form-control" name="status"><option value="all">{{ translate('All statuses') }}</option>@foreach (\App\Enums\ProductReviewStatus::cases() as $item)<option value="{{ $item->value }}" @selected($status === $item->value)>{{ ucfirst(str_replace('_', ' ', $item->value)) }}</option>@endforeach</select></div>
                <div class="col-md-3"><button class="btn btn-primary btn-block">{{ translate('Filter') }}</button></div>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table aiz-table mb-0">
                <thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Shop') }}</th><th>{{ translate('Submitted') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Decision') }}</th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td><strong>{{ $product->name }}</strong><br><small>#{{ $product->id }}</small></td>
                        <td>{{ $product->shop?->name }}<br><small>{{ $product->shop?->user?->email }}</small></td>
                        <td>{{ optional($product->submitted_for_review_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $product->review_status?->value ?? 'draft')) }}</td>
                        <td style="min-width:320px">
                            <form method="POST" action="{{ route('admin.seller_product_reviews.update', $product) }}">@csrf @method('PATCH')
                                <div class="input-group mb-2"><select class="form-control" name="status"><option value="approved">{{ translate('Approve') }}</option><option value="changes_requested">{{ translate('Request changes') }}</option><option value="rejected">{{ translate('Reject') }}</option></select><div class="input-group-append"><button class="btn btn-primary">{{ translate('Save') }}</button></div></div>
                                <textarea class="form-control" name="reason" rows="2" placeholder="{{ translate('Reason required for rejection or changes') }}"></textarea>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5">{{ translate('No products match this review queue.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="aiz-pagination mt-3">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
