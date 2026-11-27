<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettleSellerPayoutRequest;
use App\Models\SellerPayout;
use App\Services\Sellers\SellerPayoutService;
use Illuminate\Http\Request;

class SellerPayoutController extends Controller
{
    public function __construct(private readonly SellerPayoutService $payoutService) {}

    public function payout_requests(Request $request)
    {
        $this->authorizePermission();
        $payouts = SellerPayout::query()
            ->with('shop.user')
            ->whereIn('status', ['requested', 'pending', 'approved', 'processing'])
            ->latest()
            ->paginate(20);
        $history = false;

        return view('backend.sellers.payouts.index', compact('payouts', 'history'));
    }

    public function index(Request $request)
    {
        $this->authorizePermission();
        $payouts = SellerPayout::query()
            ->with('shop.user')
            ->whereIn('status', ['paid', 'rejected', 'cancelled'])
            ->latest()
            ->paginate(20);
        $history = true;

        return view('backend.sellers.payouts.index', compact('payouts', 'history'));
    }

    public function payment_modal(Request $request)
    {
        $this->authorizePermission();
        $payout = SellerPayout::query()->with('shop.user')->findOrFail($request->integer('id'));

        return view('backend.sellers.payouts.payment-modal', compact('payout'));
    }

    public function pay_to_seller(SettleSellerPayoutRequest $request)
    {
        $this->authorizePermission();
        $payout = SellerPayout::query()->findOrFail($request->integer('payout_id'));
        $this->payoutService->settle($payout, $request->user(), $request->safe()->except('payout_id'));

        return back()->with('success', translate('Payout settled successfully.'));
    }

    private function authorizePermission(): void
    {
        $user = request()->user();
        abort_unless($user && ($user->user_type === 'admin' || $user->can('manage_seller_payouts')), 403);
    }
}
