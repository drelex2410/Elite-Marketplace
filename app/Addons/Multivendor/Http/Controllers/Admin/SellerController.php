<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSellerRequest;
use App\Http\Requests\Admin\UpdateSellerRequest;
use App\Http\Requests\Admin\UpdateShopStatusRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerPackage;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\DB\SellerApprovalNotification;
use App\Notifications\SellerApprovedNotification;
use App\Services\Sellers\SellerAuditService;
use App\Services\Sellers\SellerManagementService;
use App\Services\Sellers\ShopLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class SellerController extends Controller
{
    public function __construct(
        private readonly SellerManagementService $managementService,
        private readonly ShopLifecycleService $lifecycleService,
        private readonly SellerAuditService $auditService
    ) {}

    public function all_sellers(Request $request)
    {
        $this->authorizePermission('view_sellers', 'show_sellers');
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $sellers = User::query()
            ->where('user_type', 'seller')
            ->with(['shop.seller_package'])
            ->when($status !== '', function ($query) use ($status): void {
                $query->whereHas('shop', fn ($shopQuery) => $shopQuery->where('status', $status));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sellerQuery) use ($search): void {
                    $sellerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('shop', fn ($shopQuery) => $shopQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusCounts = Shop::query()
            ->whereHas('user', fn ($query) => $query->where('user_type', 'seller'))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('backend.sellers.index', compact('sellers', 'status', 'search', 'statusCounts'));
    }

    public function seller_create()
    {
        $this->authorizePermission('create_sellers');

        return view('backend.sellers.create', ['packages' => SellerPackage::query()->orderBy('display_order')->get()]);
    }

    public function seller_store(StoreSellerRequest $request)
    {
        $seller = $this->managementService->create($request->validated());

        flash(translate('Seller account created successfully.'))->success();

        return redirect()->route('admin.seller.edit', $seller->id);
    }

    public function seller_edit(int $id)
    {
        $this->authorizePermission('edit_sellers');
        $seller = $this->seller($id);

        return view('backend.sellers.edit', [
            'seller' => $seller,
            'packages' => SellerPackage::query()->orderBy('display_order')->get(),
        ]);
    }

    public function seller_update(UpdateSellerRequest $request)
    {
        $seller = $this->seller((int) $request->input('seller_id'));
        $this->managementService->update($seller, $request->validated());
        flash(translate('Seller account updated successfully.'))->success();

        return back();
    }

    public function updateStatus(UpdateShopStatusRequest $request, Shop $shop)
    {
        $shop = $this->lifecycleService->transition(
            $shop,
            ShopStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('reason')
        );
        $this->notifyLifecycleChange($shop);

        return back()->with('success', translate('Shop status updated successfully.'));
    }

    public function update_seller_approval(Request $request)
    {
        $this->authorizePermission('approve_sellers');
        $validated = $request->validate(['id' => ['required', 'integer', 'exists:shops,id'], 'status' => ['required', 'boolean']]);
        $shop = Shop::query()->findOrFail($validated['id']);
        $status = $validated['status'] ? ShopStatus::Approved : ShopStatus::Pending;
        $this->lifecycleService->transition($shop, $status, $request->user());

        return response()->json(['success' => true, 'status' => $status->value]);
    }

    public function update_shop_publish(Request $request)
    {
        $this->authorizePermission('activate_sellers');
        $validated = $request->validate(['id' => ['required', 'integer', 'exists:shops,id'], 'status' => ['required', 'boolean']]);
        $shop = Shop::query()->findOrFail($validated['id']);
        $status = $validated['status'] ? ShopStatus::Active : ShopStatus::Inactive;
        $this->lifecycleService->transition($shop, $status, $request->user());

        return response()->json(['success' => true, 'status' => $status->value]);
    }

    public function profile_modal(Request $request)
    {
        $this->authorizePermission('view_sellers', 'show_sellers');
        $seller = $this->seller((int) $request->validate(['id' => ['required', 'integer']])['id']);

        return view('backend.sellers.partials.profile', compact('seller'));
    }

    public function payment_modal(Request $request)
    {
        $this->authorizePermission('manage_seller_payouts', 'show_payouts');
        $seller = $this->seller((int) $request->validate(['id' => ['required', 'integer']])['id']);

        return view('backend.sellers.partials.payment', compact('seller'));
    }

    public function seller_destroy(int $id)
    {
        $this->authorizePermission('suspend_sellers');
        $seller = $this->seller($id);
        $seller->banned = true;
        $seller->save();
        $this->lifecycleService->transition($seller->shop, ShopStatus::Suspended, auth()->user(), translate('Archived by administrator'));
        $this->auditService->record('seller.archived', $seller, $seller->shop, $seller);

        return back()->with('success', translate('Seller access was suspended. Historical records were preserved.'));
    }

    public function login_as_seller(int $id)
    {
        $this->authorizePermission('edit_sellers');
        $seller = $this->seller($id);
        session(['impersonator_admin_id' => Auth::id()]);
        $this->auditService->record('seller.impersonation.started', $seller, $seller->shop, $seller);
        Auth::login($seller);
        request()->session()->regenerate();

        return redirect()->route('seller.dashboard');
    }

    public function bulk_seller_delete(Request $request)
    {
        $this->authorizePermission('suspend_sellers');
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        User::query()->where('user_type', 'seller')->whereIn('id', $ids)->get()->each(function (User $seller): void {
            $seller->update(['banned' => true]);
            if ($seller->shop) {
                $this->lifecycleService->transition($seller->shop, ShopStatus::Suspended, auth()->user(), translate('Bulk archived by administrator'));
            }
        });

        return back()->with('success', translate('Selected sellers were suspended.'));
    }

    public function seller_verification_form()
    {
        $this->authorizePermission('verify_sellers', 'seller_verification_form');

        return view('backend.seller_verification_form.index');
    }

    public function seller_verification_form_update(Request $request)
    {
        $this->authorizePermission('verify_sellers', 'seller_verification_form');

        return back()->with('success', translate('Verification form settings saved.'));
    }

    public function show_verification_request(int $id)
    {
        $this->authorizePermission('verify_sellers', 'show_sellers');
        $shop = Shop::query()->with('user')->findOrFail($id);

        return view('backend.sellers.verification', compact('shop'));
    }

    public function approve_seller(Request $request, int $id)
    {
        $this->authorizePermission('verify_sellers');
        $shop = $this->lifecycleService->setVerification(Shop::query()->findOrFail($id), true, $request->user());
        $this->notifyLifecycleChange($shop);

        return back()->with('success', translate('Seller verification approved.'));
    }

    public function reject_seller(Request $request, int $id)
    {
        $this->authorizePermission('verify_sellers');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $shop = $this->lifecycleService->setVerification(Shop::query()->findOrFail($id), false, $request->user(), $reason);

        return back()->with('success', translate('Seller verification rejected.'));
    }

    public function seller_products(Request $request, string $product_type)
    {
        $this->authorizePermission('view_seller_products', 'show_seller_products');
        $products = Product::query()
            ->with('shop.user')
            ->whereHas('shop.user', fn ($query) => $query->where('user_type', 'seller'))
            ->when($product_type === 'digital', fn ($query) => $query->where('digital', 1))
            ->when($product_type === 'physical', fn ($query) => $query->where('digital', 0))
            ->latest()
            ->paginate(20);

        return view('backend.sellers.products', compact('products', 'product_type'));
    }

    public function seller_orders()
    {
        $this->authorizePermission('view_seller_orders', 'show_seller_orders');
        $orders = Order::query()
            ->with(['shop.user'])
            ->whereHas('shop.user', fn ($query) => $query->where('user_type', 'seller'))
            ->latest()
            ->paginate(20);

        return view('backend.sellers.orders', compact('orders'));
    }

    public function seller_order_show(int $id)
    {
        $this->authorizePermission('view_seller_orders', 'show_seller_orders');
        $order = Order::query()->with(['shop.user', 'orderDetails.product'])->findOrFail($id);

        abort_unless($order->shop?->user?->user_type === 'seller', 404);

        return view('backend.sellers.order-show', compact('order'));
    }

    private function seller(int $id): User
    {
        return User::query()->where('user_type', 'seller')->with('shop')->findOrFail($id);
    }

    private function authorizePermission(string ...$permissions): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->user_type === 'admin' || collect($permissions)->contains(fn ($permission) => $user->can($permission))), 403);
    }

    private function notifyLifecycleChange(Shop $shop): void
    {
        try {
            Notification::send($shop->user, new SellerApprovalNotification($shop));
            if ($shop->approval) {
                Notification::send($shop->user, new SellerApprovedNotification($shop));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
