<?php

namespace App\Addons\Multivendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionHistory;

class CommissionController extends Controller
{
    public function commission_history()
    {
        $user = request()->user();
        abort_unless($user && ($user->user_type === 'admin' || $user->can('view_seller_commissions')), 403);
        $commissions = CommissionHistory::query()->with(['order', 'shop.user'])->latest()->paginate(25);

        return view('backend.sellers.commissions', compact('commissions'));
    }
}
