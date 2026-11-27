@extends('seller.layouts.app')
@section('title', translate('Earnings'))
@section('content')
    <h1 class="h4">{{ translate('Earnings') }}</h1>
    <div class="seller-grid mb-3">@foreach (['gross_sales'=>'Gross sales','commission'=>'Platform commission','refunds'=>'Refunds','available_balance'=>'Available balance'] as $key => $label)<div class="seller-card"><small>{{ translate($label) }}</small><h2 class="h5">{{ format_price($summary[$key]) }}</h2></div>@endforeach</div>
    <div class="seller-card"><h2 class="h6">{{ translate('Commission History') }}</h2><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('Order') }}</th><th>{{ translate('Seller earning') }}</th><th>{{ translate('Commission') }}</th><th>{{ translate('Details') }}</th><th>{{ translate('Date') }}</th></tr></thead><tbody>@forelse ($commissions as $entry)<tr><td>{{ $entry->order?->code ?: '#'.$entry->order_id }}</td><td>{{ format_price($entry->seller_earning) }}</td><td>{{ format_price($entry->admin_commission) }}</td><td>{{ $entry->details }}</td><td>{{ optional($entry->created_at)->format('d M Y') }}</td></tr>@empty<tr><td colspan="5" class="text-center py-5 text-muted">{{ translate('No commission entries yet.') }}</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $commissions->links() }}</div></div>
@endsection
