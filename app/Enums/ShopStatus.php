<?php

namespace App\Enums;

enum ShopStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function canReceiveOrders(): bool
    {
        return $this === self::Active;
    }
}
