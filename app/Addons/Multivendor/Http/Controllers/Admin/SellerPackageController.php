<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSellerPackageRequest;
use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class SellerPackageController extends Controller
{
    public function index()
    {
        $this->authorizePermission('view_seller_packages', 'show_seller_packages');
        $packages = SellerPackage::query()->orderBy('display_order')->paginate(20);

        return view('backend.sellers.packages.index', compact('packages'));
    }

    public function create()
    {
        $this->authorizePermission('create_seller_packages');

        return view('backend.sellers.packages.form', ['package' => new SellerPackage]);
    }

    public function store(StoreSellerPackageRequest $request)
    {
        $package = SellerPackage::query()->create($this->payload($request));

        return redirect()->route('admin.seller_packages.edit', $package)->with('success', translate('Seller package created.'));
    }

    public function show(int $seller_package)
    {
        return redirect()->route('admin.seller_packages.edit', $seller_package);
    }

    public function edit(int $id)
    {
        $this->authorizePermission('edit_seller_packages');
        $package = SellerPackage::query()->findOrFail($id);

        return view('backend.sellers.packages.form', compact('package'));
    }

    public function update(StoreSellerPackageRequest $request, int $seller_package)
    {
        $package = SellerPackage::query()->findOrFail($seller_package);
        $package->update($this->payload($request));

        return back()->with('success', translate('Seller package updated.'));
    }

    public function destroy(int $id)
    {
        $this->authorizePermission('delete_seller_packages');
        $package = SellerPackage::query()->findOrFail($id);
        abort_if(Shop::query()->where('seller_package_id', $package->id)->exists(), 422, translate('Assigned packages cannot be deleted.'));
        abort_if(SellerPackagePayment::query()->where('seller_package_id', $package->id)->exists(), 422, translate('Packages with payment history cannot be deleted.'));
        $package->delete();

        return back()->with('success', translate('Seller package deleted.'));
    }

    public function package_purchase_history()
    {
        $this->authorizePermission('view_seller_packages', 'show_seller_package_payments');
        $payments = SellerPackagePayment::query()->with(['user.shop', 'seller_package'])->latest()->paginate(25);

        return view('backend.sellers.packages.payments', compact('payments'));
    }

    public function check_seller_package_validation()
    {
        $this->authorizePermission('edit_seller_packages');
        $expiredShopIds = Shop::query()
            ->whereNotNull('seller_package_id')
            ->whereDate('package_invalid_at', '<', today())
            ->pluck('id');

        DB::transaction(function () use ($expiredShopIds): void {
            DB::table('products')->whereIn('shop_id', $expiredShopIds)->update(['published' => 0]);
        });

        return back()->with('success', translate('Expired package products were made private.'));
    }

    private function payload(StoreSellerPackageRequest $request): array
    {
        $payload = $request->safe()->except('features_text');

        return array_merge($payload, [
            'status' => $request->boolean('status'),
            'currency' => strtoupper((string) ($request->validated('currency') ?: config('app.currency', 'NGN'))),
            'features' => collect(preg_split('/\r\n|\r|\n/', (string) $request->validated('features_text')))
                ->map(fn (string $feature) => trim($feature))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    private function authorizePermission(string ...$permissions): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->user_type === 'admin' || collect($permissions)->contains(fn ($permission) => $user->can($permission))), 403);
    }
}
