<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSellerProductRequest;
use App\Models\Product;
use App\Services\Sellers\ProductReviewService;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(private readonly ProductReviewService $reviewService) {}

    public function index(Request $request)
    {
        $this->authorizePermission('view_seller_products');

        $status = $request->string('status')->toString() ?: ProductReviewStatus::PendingApproval->value;
        $products = Product::query()
            ->with(['shop.user', 'categories'])
            ->whereHas('shop.user', fn ($query) => $query->where('user_type', 'seller'))
            ->when($status !== 'all', fn ($query) => $query->where('review_status', $status))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhereHas('shop', fn ($shop) => $shop->where('name', 'like', $search));
                });
            })
            ->latest('submitted_for_review_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('backend.sellers.product-reviews.index', compact('products', 'status'));
    }

    public function update(ReviewSellerProductRequest $request, Product $product)
    {
        $status = ProductReviewStatus::from($request->validated('status'));
        $permission = $status === ProductReviewStatus::Approved
            ? 'approve_seller_products'
            : 'reject_seller_products';
        $this->authorizePermission($permission);

        $this->reviewService->review($product, $status, $request->user(), $request->validated('reason'));

        return back()->with('success', translate('Product review decision saved.'));
    }

    private function authorizePermission(string $permission): void
    {
        $user = request()->user();
        abort_unless($user && ($user->user_type === 'admin' || $user->can($permission)), 403);
    }
}
