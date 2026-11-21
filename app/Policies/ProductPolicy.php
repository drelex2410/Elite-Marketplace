<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $this->staffCan($user, 'view_seller_products') || $this->owns($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->staffCan($user, 'manage_seller_products') || ($user->user_type === 'seller' && $user->shop_id !== null);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->staffCan($user, 'manage_seller_products') || $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function review(User $user, Product $product): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('approve_seller_products'));
    }

    private function owns(User $user, Product $product): bool
    {
        return $user->user_type === 'seller' && (int) $user->shop_id === (int) $product->shop_id;
    }

    private function staffCan(User $user, string $permission): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can($permission));
    }
}
