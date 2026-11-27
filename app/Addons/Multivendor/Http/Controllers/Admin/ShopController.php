<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function shop_setting()
    {
        $this->authorizeAccess();

        return view('backend.sellers.shop-setting');
    }

    public function shop_setting_update(Request $request, int $id)
    {
        $this->authorizeAccess();
        $validated = $request->validate([
            'default_seller_commission' => ['required', 'numeric', 'min:0', 'max:100'],
            'seller_requires_approval' => ['nullable', 'boolean'],
            'seller_product_requires_approval' => ['nullable', 'boolean'],
        ]);

        Setting::query()->updateOrCreate(['type' => 'default_seller_commission'], ['value' => $validated['default_seller_commission']]);
        Setting::query()->updateOrCreate(['type' => 'seller_requires_approval'], ['value' => $request->boolean('seller_requires_approval')]);
        Setting::query()->updateOrCreate(['type' => 'seller_product_requires_approval'], ['value' => $request->boolean('seller_product_requires_approval')]);
        cache_clear();

        return back()->with('success', translate('Marketplace seller settings updated.'));
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->user_type === 'admin' || $user->can('show_shop_setting')), 403);
    }
}
