@extends('seller.layouts.app')

@section('title', translate('Payouts'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-1">{{ translate('Payouts') }}</h1><p class="text-muted mb-0">{{ translate('Request withdrawals from verified available earnings.') }}</p></div><a class="btn btn-light" href="{{ route('seller.payout_settings') }}">{{ translate('Payment Details') }}</a></div>
    <div class="row mb-3"><div class="col-md-4"><div class="seller-card"><small>{{ translate('Available balance') }}</small><h2 class="h4">{{ format_price($shop->current_balance) }}</h2></div></div><div class="col-md-4"><div class="seller-card"><small>{{ translate('Reserved for pending payouts') }}</small><h2 class="h4">{{ format_price($reserved) }}</h2></div></div></div>
    <div class="seller-card mb-3">
        <h2 class="h6">{{ translate('New payout request') }}</h2>
        <form method="POST" action="{{ route('seller.payouts.request.store') }}" class="row gutters-5">@csrf
            <div class="col-md-4"><label for="payout-amount">{{ translate('Amount') }}</label><input id="payout-amount" name="amount" inputmode="decimal" class="form-control" required pattern="\d+(\.\d{1,2})?" value="{{ old('amount') }}"></div>
            <div class="col-md-6"><label for="seller-note">{{ translate('Note') }}</label><input id="seller-note" name="seller_note" maxlength="1000" class="form-control" value="{{ old('seller_note') }}"></div>
            <div class="col-md-2 align-self-end"><button class="btn btn-primary btn-block">{{ translate('Request') }}</button></div>
        </form>
    </div>
    <div class="seller-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>#</th><th>{{ translate('Requested') }}</th><th>{{ translate('Paid') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Reference') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead><tbody>
        @forelse ($payouts as $payout)<tr><td>{{ $payout->id }}</td><td>{{ format_price($payout->requested_amount) }}</td><td>{{ format_price($payout->paid_amount) }}</td><td>{{ ucfirst($payout->status) }}</td><td>{{ $payout->txn_code ?: '—' }}</td><td>{{ optional($payout->created_at)->format('d M Y') }}</td><td class="text-right">@if (in_array($payout->status, ['requested','pending'], true))<form method="POST" action="{{ route('seller.payouts.destroy', $payout) }}" onsubmit="return confirm('{{ translate('Cancel this request?') }}')">@csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm">{{ translate('Cancel') }}</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-5">{{ translate('No payout requests yet.') }}</td></tr>@endforelse
    </tbody></table></div><div class="mt-3">{{ $payouts->links() }}</div></div>
@endsection
