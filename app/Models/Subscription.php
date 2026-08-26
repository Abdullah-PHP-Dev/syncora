<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
	protected $fillable = [
		'user_id',
		'bundle_id',
		'billing_period',
		'bundle_name',
		'start_date',
		'end_date',
		'status',
		'is_active',
	];

	protected $casts = [
		'start_date' => 'date',
		'end_date' => 'date',
		'is_active' => 'boolean',
	];

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function cycles()
	{
		return $this->hasMany(SubscriptionCycle::class);
	}

	public function bundle()
	{
		return $this->belongsTo(Bundle::class);
	}

	public function getBillingPeriodLabelAttribute()
	{
		return match ($this->billing_period) {
			3 => 'Quarterly',
			6 => 'Half Year',
			12 => 'Yearly',
			default => 'Monthly',
		};
	}

}
