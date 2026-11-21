<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function view(User $user, Shop $shop): bool
    {
        return $this->staff($user) || (int) $shop->user_id === (int) $user->id;
    }

    public function update(User $user, Shop $shop): bool
    {
        return $this->view($user, $shop);
    }

    private function staff(User $user): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('manage_sellers'));
    }
}
