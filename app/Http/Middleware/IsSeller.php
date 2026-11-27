<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsSeller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $shop = Auth::check() ? Auth::user()->shop : null;

        if (! Auth::check() || Auth::user()->user_type !== 'seller' || ! $shop) {
            abort(404);
        }

        if ((bool) Auth::user()->banned) {
            abort(403, 'This seller account has been suspended.');
        }

        return $next($request);
    }
}
