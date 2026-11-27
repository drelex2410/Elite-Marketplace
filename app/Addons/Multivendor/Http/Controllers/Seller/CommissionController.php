<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CommissionHistory;
use App\Models\Order;
use App\Models\SellerPayout;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function commission_history(Request $request)
    {
        $shop = $request->user()->shop;
        $commissions = CommissionHistory::query()
            ->with('order')
            ->where('shop_id', $shop->id)
            ->latest()
            ->paginate(20);
        $summary = [
            'gross_sales' => Order::query()->where('shop_id', $shop->id)->where('payment_status', 'paid')->where('delivery_status', '!=', 'cancelled')->sum('grand_total'),
            'commission' => CommissionHistory::query()->where('shop_id', $shop->id)->sum('admin_commission'),
            'seller_earnings' => CommissionHistory::query()->where('shop_id', $shop->id)->sum('seller_earning'),
            'refunds' => Order::query()->where('shop_id', $shop->id)->sum('refund_amount'),
            'paid_payouts' => SellerPayout::query()->where('shop_id', $shop->id)->where('status', 'paid')->sum('paid_amount'),
            'available_balance' => $shop->current_balance,
        ];

        return view('seller.earnings.index', compact('shop', 'commissions', 'summary'));
    }
}
