<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'seller';
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'seller_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
