<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->user_type === 'admin' || $user->can('create_sellers'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
            'email_verified' => ['nullable', 'boolean'],
            'shop_name' => ['required', 'string', 'max:200'],
            'shop_phone' => ['required', 'string', 'max:20'],
            'shop_address' => ['required', 'string', 'max:255'],
            'shop_description' => ['nullable', 'string', 'max:5000'],
            'shop_logo' => ['nullable', 'integer', 'exists:uploads,id'],
            'shop_banner' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ShopStatus::class)],
            'verification_status' => ['nullable', 'boolean'],
            'seller_package_id' => ['nullable', 'integer', 'exists:seller_packages,id'],
            'commission' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
