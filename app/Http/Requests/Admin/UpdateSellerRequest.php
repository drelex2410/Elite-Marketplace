<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->user_type === 'admin' || $user->can('edit_sellers'));
    }

    public function rules(): array
    {
        $sellerId = (int) ($this->route('seller')?->id ?? $this->route('id') ?? $this->input('seller_id'));

        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($sellerId)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($sellerId)],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
            'shop_name' => ['required', 'string', 'max:200'],
            'shop_phone' => ['required', 'string', 'max:20'],
            'shop_address' => ['required', 'string', 'max:255'],
            'shop_description' => ['nullable', 'string', 'max:5000'],
            'shop_logo' => ['nullable', 'integer', 'exists:uploads,id'],
            'shop_banner' => ['nullable', 'string', 'max:255'],
            'seller_package_id' => ['nullable', 'integer', 'exists:seller_packages,id'],
            'commission' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
