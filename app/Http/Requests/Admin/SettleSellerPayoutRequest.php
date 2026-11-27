<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettleSellerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->user_type === 'admin' || $user->can('manage_seller_payouts'));
    }

    public function rules(): array
    {
        return [
            'payout_id' => ['required', 'integer', 'exists:seller_payouts,id'],
            'payment_method' => ['required', 'string', 'max:100'],
            'txn_code' => ['required', 'string', 'max:191'],
            'payment_details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
