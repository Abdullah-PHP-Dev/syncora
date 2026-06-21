<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerBundle extends Model
{
	protected $fillable = [
		'seller_id',
		'package_id',
		'price',
		'currency',
		'status',
		'payment_status',
		'starts_at',
		'expires_at',
		'payment_gateway',
		'transaction_id',
		'auto_renew'
	];

	protected $casts = [
		'starts_at' => 'datetime',
		'expires_at' => 'datetime',
		'auto_renew' => 'boolean',
	];

	public function seller()
	{
		return $this->belongsTo(Seller::class);
	}

	public function package()
	{
		return $this->belongsTo(Package::class);
	}
}