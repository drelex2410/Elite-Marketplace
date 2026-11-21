<?php

namespace App\Policies;

use App\Models\SellerPayout;
use App\Models\User;

class SellerPayoutPolicy
{
    public function view(User $user, SellerPayout $payout): bool
    {
        return $this->staff($user) || (int) $payout->shop_id === (int) $user->shop_id;
    }

    public function create(User $user): bool
    {
        return $user->user_type === 'seller' && $user->shop_id !== null;
    }

    public function settle(User $user, SellerPayout $payout): bool
    {
        return $this->staff($user);
    }

    private function staff(User $user): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('manage_seller_payouts'));
    }
}
