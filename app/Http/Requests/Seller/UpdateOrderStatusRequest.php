<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'seller';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'confirmed',
                'processed',
                'shipped',
                'ready_for_pickup',
                'picked_up',
                'delivered',
            ])],
        ];
    }
}
