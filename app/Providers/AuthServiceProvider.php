<?php

namespace App\Providers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerPayout;
use App\Models\Shop;
use App\Models\Upload;
use App\Policies\CouponPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SellerPayoutPolicy;
use App\Policies\ShopPolicy;
use App\Policies\UploadPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        Shop::class => ShopPolicy::class,
        Coupon::class => CouponPolicy::class,
        Upload::class => UploadPolicy::class,
        SellerPayout::class => SellerPayoutPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}
