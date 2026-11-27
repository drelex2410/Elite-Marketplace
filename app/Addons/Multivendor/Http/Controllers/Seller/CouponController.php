<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\CouponController as BaseCouponController;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;

class CouponController extends BaseCouponController
{
    public function __construct() {}

    public function store(Request $request)
    {
        $this->validatePayload($request);
        parent::store($request);

        return redirect()->route('seller.coupons.index');
    }

    public function edit($id)
    {
        $coupon = Coupon::whereKey((int) decrypt($id))
            ->where('shop_id', auth()->user()->shop_id)
            ->firstOrFail();

        return view('backend.marketing.coupons.edit', compact('coupon'));
    }

    public function update(Request $request, $id)
    {
        Coupon::whereKey($id)->where('shop_id', auth()->user()->shop_id)->firstOrFail();
        $this->validatePayload($request);
        parent::update($request, $id);

        return redirect()->route('seller.coupons.index');
    }

    public function destroy($id)
    {
        Coupon::whereKey($id)->where('shop_id', auth()->user()->shop_id)->firstOrFail();
        parent::destroy($id);

        return redirect()->route('seller.coupons.index');
    }

    public function get_coupon_form_edit(Request $request)
    {
        Coupon::whereKey($request->integer('id'))
            ->where('shop_id', auth()->user()->shop_id)
            ->firstOrFail();

        return parent::get_coupon_form_edit($request);
    }

    private function validatePayload(Request $request): void
    {
        $request->validate([
            'coupon_type' => ['required', 'in:product_base,cart_base'],
            'coupon_code' => ['required', 'string', 'max:100'],
            'discount' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['required', 'in:amount,percent'],
            'date_range' => ['required', 'string', 'max:100'],
            'product_ids' => ['required_if:coupon_type,product_base', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        if ($request->coupon_type === 'product_base') {
            $requested = collect($request->product_ids)->map(fn ($id) => (int) $id)->unique();
            $owned = Product::where('shop_id', auth()->user()->shop_id)
                ->whereIn('id', $requested)
                ->count();
            abort_unless($owned === $requested->count(), 403);
        }
    }
}
