<?php

namespace App\Http\Middleware;

use App\Enums\ShopStatus;
use Closure;
use Illuminate\Http\Request;

class EnsureSellerOperational
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $shop = $user?->shop;

        if (! $user || $user->user_type !== 'seller' || ! $shop) {
            abort(404);
        }

        $status = $shop->status instanceof ShopStatus
            ? $shop->status->value
            : (string) ($shop->status ?? '');

        if (
            $user->banned ||
            ! $shop->approval ||
            ! $shop->published ||
            ($status !== '' && $status !== ShopStatus::Active->value)
        ) {
            return redirect()
                ->route('seller.dashboard')
                ->with('warning', translate('Your shop is not active. Complete onboarding or contact support.'));
        }

        return $next($request);
    }
}
