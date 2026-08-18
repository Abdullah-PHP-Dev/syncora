<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCycle extends Model
{
	protected $fillable = [
		'subscription_id',
		'user_id',
		'bundle_id',
		'start_date',
		'end_date',
		'type',
		'status',
	];

	protected $casts = [
		'start_date' => 'date',
		'end_date' => 'date',
	];

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function subscription()
	{
		return $this->belongsTo(Subscription::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function bundle()
	{
		return $this->belongsTo(Bundle::class);
	}
}
