<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($this->staffCan($user)) {
            return true;
        }

        if ($user->user_type !== 'seller' || ! $user->shop_id) {
            return false;
        }

        return (int) $order->shop_id === (int) $user->shop_id
            || $order->orderDetails()
                ->whereHas('product', fn ($query) => $query->where('shop_id', $user->shop_id))
                ->exists();
    }

    public function updateFulfillment(User $user, Order $order): bool
    {
        if ($user->user_type !== 'seller' || (int) $order->shop_id !== (int) $user->shop_id) {
            return false;
        }

        return ! $order->orderDetails()
            ->whereHas('product', fn ($query) => $query->where('shop_id', '!=', $user->shop_id))
            ->exists();
    }

    private function staffCan(User $user): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('view_seller_orders'));
    }
}
