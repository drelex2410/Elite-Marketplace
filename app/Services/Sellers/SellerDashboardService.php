<?php

namespace App\Services\Sellers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerPayout;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class SellerDashboardService
{
    public function build(Shop $shop, int $days = 30): array
    {
        $days = in_array($days, [30, 90, 365], true) ? $days : 30;
        $orders = Order::query()->where('shop_id', $shop->id);
        $paidEligibleOrders = (clone $orders)
            ->where('payment_status', 'paid')
            ->whereNotIn('delivery_status', ['cancelled']);

        $grossSales = (string) (clone $paidEligibleOrders)->sum('grand_total');
        $commission = (string) (clone $paidEligibleOrders)->sum('admin_commission');
        $sellerEarning = (string) (clone $paidEligibleOrders)->sum('seller_earning');
        $refunds = Schema::hasColumn('orders', 'refund_amount')
            ? (string) (clone $paidEligibleOrders)->sum('refund_amount')
            : '0';
        $netEarnings = max(0, (float) $sellerEarning - (float) $refunds);

        $paidPayouts = SellerPayout::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['paid', 'completed'])
            ->sum('paid_amount');
        $pendingPayouts = SellerPayout::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['requested', 'pending', 'approved', 'processing'])
            ->sum('requested_amount');

        $productQuery = Product::query()->where('shop_id', $shop->id);
        $pendingProducts = Schema::hasColumn('products', 'review_status')
            ? (clone $productQuery)->where('review_status', 'pending_approval')->count()
            : (clone $productQuery)->where('approved', 0)->count();

        $package = Schema::hasTable('seller_packages') ? $shop->seller_package : null;
        $uploadLimit = (int) ($shop->product_upload_limit ?: ($package?->product_upload_limit ?? 0));
        $productCount = (clone $productQuery)->count();

        return [
            'summary' => [
                'total_products' => $productCount,
                'total_orders' => (clone $orders)->count(),
                'gross_sales' => $grossSales,
                'commission' => $commission,
                'refunds' => $refunds,
                'net_earnings' => (string) $netEarnings,
                'available_balance' => (string) $shop->current_balance,
                'paid_payouts' => (string) $paidPayouts,
                'pending_payouts' => (string) $pendingPayouts,
            ],
            'formatted' => [
                'gross_sales' => $this->formatMoney($grossSales),
                'net_earnings' => $this->formatMoney((string) $netEarnings),
                'available_balance' => $this->formatMoney((string) $shop->current_balance),
            ],
            'order_statuses' => [
                'order_placed' => (clone $orders)->where('delivery_status', 'order_placed')->count(),
                'confirmed' => (clone $orders)->where('delivery_status', 'confirmed')->count(),
                'processed' => (clone $orders)->whereIn('delivery_status', ['processed', 'picked_up', 'on_the_way'])->count(),
                'delivered' => (clone $orders)->where('delivery_status', 'delivered')->count(),
            ],
            'recent_orders' => (clone $orders)->latest()->limit(6)->get(),
            'sales_statistics' => $this->salesStatistics($shop->id, $days),
            'package' => $package,
            'package_expiry' => $shop->package_invalid_at,
            'product_upload_limit' => $uploadLimit,
            'remaining_product_slots' => $uploadLimit > 0 ? max(0, $uploadLimit - $productCount) : null,
            'commission_percentage' => (float) ($shop->commission ?: ($package?->commission ?? 0)),
            'pending_product_reviews' => $pendingProducts,
            'days' => $days,
        ];
    }

    private function salesStatistics(int $shopId, int $days)
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->where('payment_status', 'paid')
            ->whereNotIn('delivery_status', ['cancelled'])
            ->where('created_at', '>=', Carbon::now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(created_at) as sale_date, SUM(grand_total) as gross_sales, SUM(seller_earning) as seller_earnings')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();
    }

    private function formatMoney(string $amount): string
    {
        try {
            return format_price((float) $amount);
        } catch (\Throwable) {
            return config('app.currency', 'NGN').' '.number_format((float) $amount, 2);
        }
    }
}
