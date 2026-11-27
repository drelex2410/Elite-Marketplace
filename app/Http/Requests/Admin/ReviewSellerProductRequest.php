<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSellerProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->user_type === 'admin'
            || $user->can('approve_seller_products')
            || $user->can('reject_seller_products')
        );
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductReviewStatus::class)->only([
                ProductReviewStatus::Approved,
                ProductReviewStatus::Rejected,
                ProductReviewStatus::ChangesRequested,
            ])],
            'reason' => [
                Rule::requiredIf($this->input('status') !== ProductReviewStatus::Approved->value),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
