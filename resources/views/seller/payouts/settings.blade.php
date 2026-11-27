@extends('seller.layouts.app')
@section('title', translate('Payout Settings'))
@section('content')
    <div class="seller-card"><h1 class="h5">{{ translate('Payout Settings') }}</h1><p class="text-muted">{{ translate('These details are visible only to authorized payout staff.') }}</p>
        <form method="POST" action="{{ route('seller.payout_settings.update') }}">@csrf
            <div class="form-check mb-3"><input type="checkbox" value="1" name="bank_payout_status" id="bank-payout" class="form-check-input" @checked($shop->bank_payout_status)><label class="form-check-label" for="bank-payout">{{ translate('Enable bank payout') }}</label></div>
            <div class="form-group"><label for="bank-name">{{ translate('Bank name') }}</label><input id="bank-name" name="bank_name" class="form-control" maxlength="191" value="{{ old('bank_name', $shop->bank_name) }}"></div>
            <div class="form-group"><label for="account-name">{{ translate('Account name') }}</label><input id="account-name" name="bank_acc_name" class="form-control" maxlength="191" value="{{ old('bank_acc_name', $shop->bank_acc_name) }}"></div>
            <div class="form-group"><label for="account-number">{{ translate('Account number') }}</label><input id="account-number" name="bank_acc_no" class="form-control" maxlength="100" value="{{ old('bank_acc_no', $shop->bank_acc_no) }}"></div>
            <div class="form-group"><label for="routing-number">{{ translate('Routing number') }}</label><input id="routing-number" name="bank_routing_no" class="form-control" maxlength="100" value="{{ old('bank_routing_no', $shop->bank_routing_no) }}"></div>
            <button class="btn btn-primary">{{ translate('Save Details') }}</button>
        </form>
    </div>
@endsection
