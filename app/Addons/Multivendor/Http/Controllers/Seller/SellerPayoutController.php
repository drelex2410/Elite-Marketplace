<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StorePayoutRequest;
use App\Http\Requests\Seller\UpdatePayoutSettingsRequest;
use App\Models\SellerPayout;
use App\Services\Sellers\SellerPayoutService;
use Illuminate\Http\Request;

class SellerPayoutController extends Controller
{
    public function __construct(private readonly SellerPayoutService $payoutService) {}

    public function index(Request $request)
    {
        return $this->payout_requests($request);
    }

    public function payout_requests(Request $request)
    {
        $shop = $request->user()->shop;
        $payouts = SellerPayout::query()->where('shop_id', $shop->id)->latest()->paginate(20);
        $reserved = SellerPayout::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['requested', 'pending', 'approved', 'processing'])
            ->sum('requested_amount');

        return view('seller.payouts.index', compact('shop', 'payouts', 'reserved'));
    }

    public function create()
    {
        return redirect()->route('seller.payouts.index');
    }

    public function store(StorePayoutRequest $request)
    {
        return $this->store_withdraw_request($request);
    }

    public function store_withdraw_request(StorePayoutRequest $request)
    {
        $this->payoutService->request(
            $request->user()->shop,
            $request->validated('amount'),
            $request->validated('seller_note')
        );

        return back()->with('success', translate('Payout request submitted.'));
    }

    public function show(Request $request, int $id)
    {
        $shop = $request->user()->shop;
        $payout = SellerPayout::query()->where('shop_id', $shop->id)->findOrFail($id);
        $this->authorize('view', $payout);

        return view('seller.payouts.show', compact('shop', 'payout'));
    }

    public function edit(): never
    {
        abort(404);
    }

    public function update(): never
    {
        abort(405);
    }

    public function destroy(Request $request, int $id)
    {
        $shop = $request->user()->shop;
        $payout = SellerPayout::query()->where('shop_id', $shop->id)->findOrFail($id);
        $this->payoutService->cancel($payout, $shop);

        return back()->with('success', translate('Payout request cancelled.'));
    }

    public function payout_settings(Request $request)
    {
        $shop = $request->user()->shop;

        return view('seller.payouts.settings', compact('shop'));
    }

    public function payout_settings_update(UpdatePayoutSettingsRequest $request)
    {
        $shop = $request->user()->shop;
        $shop->cash_payout_status = $request->boolean('cash_payout_status');
        $shop->bank_payout_status = $request->boolean('bank_payout_status');
        $shop->bank_name = $request->validated('bank_name');
        $shop->bank_acc_name = $request->validated('bank_acc_name');
        $shop->bank_acc_no = $request->validated('bank_acc_no');
        $shop->bank_routing_no = $request->validated('bank_routing_no');
        $shop->save();

        return back()->with('success', translate('Payout settings updated.'));
    }
}
