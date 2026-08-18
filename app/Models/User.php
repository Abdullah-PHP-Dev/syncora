<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
	use HasRoles;
	/** @use HasFactory<UserFactory> */
	use HasFactory, Notifiable;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'name',
		'email',
		'password',
		'mobile',
		'dob',
		'address',
		'onboarding_step',
		'onboarding_completed',
		'google_id',
		'facebook_id',
		'provider',
		'api_token',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var list<string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
		];
	}

	public function subscription()
	{
		return $this->hasOne(Subscription::class)->with('bundle');
	}

	public function subscriptionCycles()
	{
		return $this->hasManyThrough(
			SubscriptionCycle::class,
			Subscription::class
		);
	}

	public function shop()
	{
		return $this->hasOne(Shop::class);
	}

	// public function getDashboardRoute()
	// {
	//     if ($this->hasRole('admin')) {
	//         return 'admin.dashboard';
	//     }

	//     if ($this->hasRole('seller')) {
	//         return 'dashboard';
	//     }

	//     return 'dashboard';
	// }

	public function departments()
	{
		return $this->belongsToMany(
			Department::class,
			'department_user'
		)->withPivot('role')->withTimestamps();
	}

	public function wallet()
	{
		return $this->hasOne(Wallet::class, 'seller_id', 'id');
	}

	public function getWalletBalanceAttribute()
	{
		return $this->wallet?->available_balance;
	}
	public function addresses()
	{
		return $this->hasMany(Address::class);
	}

	public function orders()
	{
		return $this->hasMany(Order::class);
	}

	public function notifications()
	{
		return $this->belongsToMany(
			Notification::class,
			'notification_recipients'
		)
			->withPivot([
				            'read_at',
				            'created_at',
				            'updated_at',
			            ]);
	}

	public function hasActiveSubscription()
	{

		return $this->subscription &&
			($this->subscription->status === 'active' || $this->subscription->status === 'trial');
	}
}
