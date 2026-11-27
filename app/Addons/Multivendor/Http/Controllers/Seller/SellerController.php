<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController;
use App\Http\Requests\Seller\UpdateOrderStatusRequest;
use App\Http\Requests\Seller\UpdateOrderTrackingRequest;
use App\Models\Product;
use App\Models\Review;
use App\Services\Sellers\SellerDashboardService;
use App\Services\Sellers\SellerOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PDF;

class SellerController extends Controller
{
    public function __construct(
        private readonly SellerDashboardService $dashboardService,
        private readonly ProductController $productController,
        private readonly SellerOrderService $orderService
    ) {}

    public function seller_dashboard(Request $request)
    {
        $shop = $request->user()->shop;
        $dashboard = $this->dashboardService->build($shop, (int) $request->integer('days', 30));

        return view('seller.dashboard', compact('shop', 'dashboard'));
    }

    public function profile(Request $request)
    {
        $shop = $request->user()->shop;

        return view('seller.profile', compact('shop'));
    }

    public function profile_update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar' => ['nullable', 'integer', 'exists:uploads,id'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        if (! empty($validated['avatar'])) {
            abort_unless(\App\Models\Upload::query()->where('id', $validated['avatar'])->where('user_id', $request->user()->id)->exists(), 403);
        }
        $user = $request->user();
        $user->name = $validated['name'];
        $user->phone = $validated['phone'] ?? null;
        $user->avatar = $validated['avatar'] ?? $user->avatar;
        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        $user->save();

        return back()->with('success', translate('Profile updated.'));
    }

    public function seller_products_list(Request $request)
    {
        $shop = $request->user()->shop;
        $products = Product::query()
            ->with(['categories', 'brand', 'variations.combinations.attribute_value'])
            ->ownedByShop($shop->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('name', 'like', '%'.$request->string('search')->toString().'%');
            })
            ->when($request->filled('status'), fn ($query) => $query->where('review_status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.products.index', compact('shop', 'products'));
    }

    public function show_product_upload_form(Request $request)
    {
        $this->assertProductCapacity($request);

        return $this->productController->create()->with([
            'sellerMode' => true,
            'shop' => $request->user()->shop,
        ]);
    }

    public function seller_product_store(Request $request)
    {
        $this->assertProductCapacity($request);

        return $this->productController->store($request);
    }

    public function show_product_edit_form(Request $request, int $id)
    {
        $this->ownedProduct($request, $id);

        return $this->productController->edit($request, $id)->with([
            'sellerMode' => true,
            'shop' => $request->user()->shop,
        ]);
    }

    public function seller_product_update(Request $request, int $id)
    {
        $this->ownedProduct($request, $id);

        return $this->productController->update($request, $id);
    }

    public function seller_product_show(Request $request, int $id)
    {
        $shop = $request->user()->shop;
        $product = $this->ownedProduct($request, $id)->load([
            'categories',
            'brand',
            'variations.combinations.attribute_value',
        ]);

        return view('seller.products.show', compact('shop', 'product'));
    }

    public function seller_product_published(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['required', 'boolean'],
        ]);
        $this->ownedProduct($request, (int) $request->integer('id'));

        return $this->productController->updatePublished($request);
    }

    public function seller_product_duplicate(Request $request, int $id)
    {
        $this->assertProductCapacity($request);
        $this->ownedProduct($request, $id);

        return $this->productController->duplicate($request, $id);
    }

    public function seller_product_destroy(Request $request, int $id)
    {
        $this->ownedProduct($request, $id);

        return $this->productController->destroy($id);
    }

    public function bulk_product_delete(Request $request)
    {
        $ids = collect($request->input('id', []))->map(fn ($id) => (int) $id)->unique();
        $ownedIds = Product::query()
            ->ownedByShop($request->user()->shop_id)
            ->whereIn('id', $ids)
            ->pluck('id');

        abort_unless($ownedIds->count() === $ids->count(), 403);

        foreach ($ownedIds as $id) {
            $this->productController->destroy($id);
        }

        return response()->json(['success' => true]);
    }

    public function orders(Request $request)
    {
        $shop = $request->user()->shop;
        $orders = $this->orderService->paginate($shop, $request->only([
            'search',
            'payment_status',
            'delivery_status',
        ]));
        $pickupOnly = false;

        return view('seller.orders.index', compact('shop', 'orders', 'pickupOnly'));
    }

    public function pickup_orders(Request $request)
    {
        $shop = $request->user()->shop;
        $orders = $this->orderService->paginate($shop, $request->only([
            'search',
            'payment_status',
            'delivery_status',
        ]), true);
        $pickupOnly = true;

        return view('seller.orders.index', compact('shop', 'orders', 'pickupOnly'));
    }

    public function orders_show(Request $request, int $id)
    {
        $shop = $request->user()->shop;
        $order = $this->orderService->findForShop($shop, $id);
        $this->authorize('view', $order);
        $allowedTransitions = $shop->isPubliclyVisible()
            ? $this->orderService->allowedTransitionsForShop($order, $shop)
            : [];
        $shippingAddress = json_decode((string) $order->shipping_address, true) ?: [];

        return view('seller.orders.show', compact(
            'shop',
            'order',
            'allowedTransitions',
            'shippingAddress'
        ));
    }

    public function update_order_status(UpdateOrderStatusRequest $request, int $id)
    {
        $order = $this->orderService->findForShop($request->user()->shop, $id);
        $this->authorize('updateFulfillment', $order);
        $this->orderService->transition(
            $request->user()->shop,
            $id,
            $request->validated('status'),
            $request->user()->id
        );

        return back()->with('success', translate('Fulfillment status updated.'));
    }

    public function update_order_tracking(UpdateOrderTrackingRequest $request, int $id)
    {
        $order = $this->orderService->findForShop($request->user()->shop, $id);
        $this->authorize('updateFulfillment', $order);
        $this->orderService->updateTracking(
            $request->user()->shop,
            $id,
            $request->validated(),
            $request->user()->id
        );

        return back()->with('success', translate('Tracking information updated.'));
    }

    public function order_invoice_download(Request $request, int $id)
    {
        $order = $this->orderService->findForShop($request->user()->shop, $id);
        $this->authorize('view', $order);
        $shippingAddress = json_decode((string) $order->shipping_address, true) ?: [];

        return PDF::loadView('seller.orders.invoice', compact('order', 'shippingAddress'))
            ->download('seller-order-'.$order->code.'.pdf');
    }

    public function order_invoice_print(Request $request, int $id)
    {
        $order = $this->orderService->findForShop($request->user()->shop, $id);
        $this->authorize('view', $order);
        $shippingAddress = json_decode((string) $order->shipping_address, true) ?: [];

        return view('seller.orders.invoice', compact('order', 'shippingAddress'));
    }

    public function seller_product_reviews(Request $request)
    {
        $shop = $request->user()->shop;
        $reviews = Review::query()
            ->with(['product', 'user'])
            ->whereHas('product', fn ($query) => $query->where('shop_id', $shop->id))
            ->latest()
            ->paginate(15);

        return view('seller.reviews.index', compact('shop', 'reviews'));
    }

    private function ownedProduct(Request $request, int $id): Product
    {
        return Product::query()
            ->ownedByShop($request->user()->shop_id)
            ->findOrFail($id);
    }

    private function assertProductCapacity(Request $request): void
    {
        $shop = $request->user()->shop;
        $limit = (int) $shop->product_upload_limit;

        if ($limit > 0 && $shop->products()->count() >= $limit) {
            throw ValidationException::withMessages([
                'product_limit' => translate('Your current package product limit has been reached.'),
            ]);
        }
    }
}
