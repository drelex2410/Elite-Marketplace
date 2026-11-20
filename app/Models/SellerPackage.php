<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App;

class SellerPackage extends Model
{
	protected $fillable = [
		'name',
		'description',
		'amount',
		'currency',
		'commission',
		'product_upload_limit',
		'max_active_products',
		'logo',
		'duration',
		'features',
		'status',
		'display_order',
		'renewal_settings',
	];

	protected $casts = [
		'amount' => 'decimal:2',
		'commission' => 'decimal:2',
		'features' => 'array',
		'status' => 'boolean',
		'renewal_settings' => 'array',
	];

	public function getTranslation($field = '', $lang = false){
		$lang = $lang == false ? App::getLocale() : $lang;
		$seller_package_translation = $this->hasMany(SellerPackageTranslation::class)->where('lang', $lang)->first();
		return $seller_package_translation != null ? $seller_package_translation->$field : $this->$field;
	}

	public function seller_package_translations(){
		return $this->hasMany(SellerPackageTranslation::class);
	}

}
