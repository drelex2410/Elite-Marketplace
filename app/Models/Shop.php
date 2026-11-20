<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $casts = [
        'approval' => 'boolean',
        'published' => 'boolean',
        'verification_status' => 'boolean',
        'status' => ShopStatus::class,
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'package_invalid_at' => 'date',
        'commission' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller_package()
    {
        return $this->belongsTo(SellerPackage::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function top_3_products()
    {
        return $this->hasMany(Product::class)->where('published', 1)->orderBy('num_of_sale', 'desc')->limit(3);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function commission_histories()
    {
        return $this->hasMany(CommissionHistory::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('status', 1);
    }

    public function shop_categories()
    {
        return $this->hasMany(ShopCategory::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'shop_categories', 'shop_id', 'category_id');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'shop_brands', 'shop_id', 'brand_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'shop_followers', 'shop_id', 'user_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->published
            && $this->approval
            && $this->verification_status
            && ($this->status === null || $this->status === ShopStatus::Active);
    }
}
