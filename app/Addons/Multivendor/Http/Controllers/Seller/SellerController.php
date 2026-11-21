<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Services\Sellers\SellerDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function __construct(
        private readonly SellerDashboardService $dashboardService,
        private readonly ProductController $productController
    ) {}

    public function seller_dashboard(Request $request)
    {
        $shop = $request->user()->shop;
        $dashboard = $this->dashboardService->build($shop, (int) $request->integer('days', 30));

        return view('seller.dashboard', compact('shop', 'dashboard'));
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
