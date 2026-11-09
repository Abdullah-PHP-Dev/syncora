<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
	use HasApiTokens;

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
		'workspace_owner_id',
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
            'is_active' => 'boolean',
		];
	}

    /** Workspace accounts (WorkspaceService::create) belong to, and are billed to, their owner. */
    public function workspaceOwner()
    {
        return $this->belongsTo(User::class, 'workspace_owner_id');
    }

    public function isWorkspaceAccount(): bool
    {
        return $this->workspace_owner_id !== null;
    }

    /** Workspace accounts have no inbox of their own: mail goes to the owner. */
    public function routeNotificationForMail($notification = null): string
    {
        return $this->isWorkspaceAccount() && $this->workspaceOwner ? $this->workspaceOwner->email : $this->email;
    }

    public function isTeamMember(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('customer_support');
    }

    /** At least one connected account Posts / Inbox / Comments can use (SocialAccount::scopeEligible). */
    public function hasEligibleConnection(): bool
    {
        return SocialAccount::where('user_id', $this->id)->eligible()->exists();
    }

    public function aiCopilotSetting()
    {
        return $this->hasOne(AiCopilotSetting::class);
    }

    public function businessProfile()
    {
        return $this->hasOne(BusinessProfile::class);
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
		$access = app(\App\Services\PackageAccessService::class);
        return $access->active($access->subscription($this));
	}

}
