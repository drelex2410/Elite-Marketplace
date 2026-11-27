@extends('seller.layouts.app')

@section('title', translate('Product Reviews'))

@section('content')
    <div class="mb-3"><h1 class="h3 mb-1">{{ translate('Product Reviews') }}</h1><p class="text-muted">{{ translate('Customer feedback for products in your shop.') }}</p></div>
    <div class="seller-card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('Product') }}</th><th>{{ translate('Customer') }}</th><th>{{ translate('Rating') }}</th><th>{{ translate('Comment') }}</th><th>{{ translate('Date') }}</th></tr></thead><tbody>
    @forelse ($reviews as $review)
        <tr><td>{{ $review->product?->getTranslation('name') }}</td><td>{{ $review->user?->name }}</td><td>{{ $review->rating }}/5</td><td>{{ $review->comment }}</td><td>{{ optional($review->created_at)->format('M j, Y') }}</td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted py-5">{{ translate('No reviews yet.') }}</td></tr>
    @endforelse
    </tbody></table></div>{{ $reviews->links() }}</div>
@endsection
