<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerAdminAudit extends Model
{
    protected $fillable = [
        'actor_id',
        'seller_id',
        'shop_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];
}
