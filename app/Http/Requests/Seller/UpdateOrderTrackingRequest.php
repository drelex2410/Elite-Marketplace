<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'seller';
    }

    public function rules(): array
    {
        return [
            'courier_name' => ['required', 'string', 'max:120'],
            'tracking_number' => ['required', 'string', 'max:191'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
