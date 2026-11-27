<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $status = $this->input('status');
        $permission = $status === ShopStatus::Suspended->value ? 'suspend_sellers' : 'activate_sellers';

        return $user->user_type === 'admin' || $user->can($permission);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ShopStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($this->input('status'), ['suspended', 'rejected'], true))],
        ];
    }
}
