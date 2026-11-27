<?php

namespace App\Services\Sellers;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductReviewStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function __construct(private readonly SellerAuditService $auditService) {}

    public function prepareSellerSave(Product $product, string $action): void
    {
        $submit = $action === 'submit';

        $product->approved = false;
        $product->published = false;
        $product->review_status = $submit
            ? ProductReviewStatus::PendingApproval
            : ProductReviewStatus::Draft;
        $product->review_reason = null;
        $product->submitted_for_review_at = $submit ? now() : null;
        $product->reviewed_at = null;
        $product->reviewed_by = null;
    }

    public function submit(Product $product, User $seller): Product
    {
        $this->assertOwnership($product, $seller);

        return DB::transaction(function () use ($product): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->prepareSellerSave($product, 'submit');
            $product->save();
            cache_clear();

            return $product->refresh();
        }, 3);
    }

    public function review(
        Product $product,
        ProductReviewStatus $status,
        User $actor,
        ?string $reason = null
    ): Product {
        if (! in_array($status, [
            ProductReviewStatus::Approved,
            ProductReviewStatus::Rejected,
            ProductReviewStatus::ChangesRequested,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => translate('Invalid product review decision.'),
            ]);
        }

        if ($status !== ProductReviewStatus::Approved && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => translate('A reason is required for this decision.'),
            ]);
        }

        return DB::transaction(function () use ($product, $status, $actor, $reason): Product {
            $product = Product::query()->with('shop.user')->lockForUpdate()->findOrFail($product->id);
            $before = $this->snapshot($product);

            $product->review_status = $status;
            $product->review_reason = $reason;
            $product->reviewed_at = now();
            $product->reviewed_by = $actor->id;
            $product->approved = $status === ProductReviewStatus::Approved;
            $product->published = $status === ProductReviewStatus::Approved;
            $product->save();

            $this->auditService->record(
                'product.review.'.str_replace('_', '.', $status->value),
                $product->shop?->user,
                $product->shop,
                $product,
                $before,
                $this->snapshot($product),
                $reason
            );

            DB::afterCommit(function () use ($product, $status, $reason): void {
                try {
                    $seller = $product->shop?->user;
                    if ($seller) {
                        $seller->notify(new ProductReviewStatusNotification($product, $status, $reason));
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            cache_clear();

            return $product->refresh();
        }, 3);
    }

    private function assertOwnership(Product $product, User $seller): void
    {
        abort_unless($seller->shop_id && (int) $product->shop_id === (int) $seller->shop_id, 403);
    }

    private function snapshot(Product $product): array
    {
        return [
            'review_status' => $product->review_status instanceof ProductReviewStatus
                ? $product->review_status->value
                : $product->review_status,
            'approved' => (bool) $product->approved,
            'published' => (bool) $product->published,
            'review_reason' => $product->review_reason,
            'reviewed_by' => $product->reviewed_by,
        ];
    }
}
