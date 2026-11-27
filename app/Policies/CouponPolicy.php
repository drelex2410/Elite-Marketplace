<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function view(User $user, Coupon $coupon): bool
    {
        return $this->staff($user) || (int) $coupon->shop_id === (int) $user->shop_id;
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->view($user, $coupon);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->view($user, $coupon);
    }

    private function staff(User $user): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('manage_seller_coupons'));
    }
}
