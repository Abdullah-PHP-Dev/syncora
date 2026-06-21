<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
	protected $casts = [
		'features' => 'array',
		'is_active' => 'boolean',
	];

	public function sellerBundles()
	{
		return $this->hasMany(SellerBundle::class);
	}
}
