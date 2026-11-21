<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayoutSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'seller';
    }

    public function rules(): array
    {
        return [
            'cash_payout_status' => ['nullable', 'boolean'],
            'bank_payout_status' => ['nullable', 'boolean'],
            'bank_name' => ['required_if:bank_payout_status,1', 'nullable', 'string', 'max:191'],
            'bank_acc_name' => ['required_if:bank_payout_status,1', 'nullable', 'string', 'max:191'],
            'bank_acc_no' => ['required_if:bank_payout_status,1', 'nullable', 'string', 'max:100'],
            'bank_routing_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}
