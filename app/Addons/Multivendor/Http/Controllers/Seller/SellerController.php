<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\Sellers\SellerDashboardService;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function __construct(private readonly SellerDashboardService $dashboardService) {}

    public function seller_dashboard(Request $request)
    {
        $shop = $request->user()->shop;
        $dashboard = $this->dashboardService->build($shop, (int) $request->integer('days', 30));

        return view('seller.dashboard', compact('shop', 'dashboard'));
    }
}
