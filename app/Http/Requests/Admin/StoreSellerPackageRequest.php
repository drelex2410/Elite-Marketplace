<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = $this->route('seller_package') ? 'edit_seller_packages' : 'create_seller_packages';

        return $user && ($user->user_type === 'admin' || $user->can($permission));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'commission' => ['required', 'numeric', 'min:0', 'max:100'],
            'product_upload_limit' => ['required', 'integer', 'min:0'],
            'max_active_products' => ['nullable', 'integer', 'min:0'],
            'duration' => ['required', 'integer', 'min:1', 'max:3650'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:191'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
