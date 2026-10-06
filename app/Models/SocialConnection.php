<?php

namespace App\Models;

use App\Casts\TolerantEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One consent a user gave a platform (docs/connection-hub-design.md §2).
 * Its assets - Pages, Instagram accounts, ad accounts, WhatsApp numbers -
 * are the social_accounts rows pointing at it.
 */
class SocialConnection extends Model
{
    public const ACTIVE = 'active';
    public const EXPIRING = 'expiring';
    public const EXPIRED = 'expired';
    public const NEEDS_REAUTH = 'needs_reauth';
    public const REVOKED = 'revoked';
    public const ERROR = 'error';

    public const CAPABILITIES = ['posting', 'messaging', 'ads', 'insights'];

    /** Days before expiry a connection shows as "expiring" in the Hub. */
    public const EXPIRING_WITHIN_DAYS = 7;

    protected $fillable = [
        'user_id', 'workspace_id', 'platform', 'step', 'provider_account_id', 'provider_app',
        'access_token', 'refresh_token', 'token_secret', 'expires_at', 'refresh_expires_at',
        'granted_scopes', 'capabilities', 'status', 'last_checked_at', 'last_refreshed_at',
        'last_error', 'revoked_at',
    ];

    protected $casts = [
        'access_token' => TolerantEncrypted::class,
        'refresh_token' => TolerantEncrypted::class,
        'token_secret' => TolerantEncrypted::class,
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'granted_scopes' => 'array',
        'capabilities' => 'array',
        'last_checked_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'token_secret'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::ACTIVE, self::EXPIRING], true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Status from the token alone (no provider call): what the hourly
     * expiry pass and the backfill use. Revocation is only learned from the
     * provider, by the validation pass.
     */
    public static function statusFor(?string $accessToken, ?Carbon $expiresAt, bool $refreshable): string
    {
        if ($accessToken === null || $accessToken === '') {
            return self::NEEDS_REAUTH;
        }

        if ($expiresAt === null) {
            return self::ACTIVE; // e.g. X OAuth 1.0a tokens never expire
        }

        if ($expiresAt->isPast()) {
            return $refreshable ? self::EXPIRED : self::NEEDS_REAUTH;
        }

        return $expiresAt->lte(now()->addDays(self::EXPIRING_WITHIN_DAYS)) ? self::EXPIRING : self::ACTIVE;
    }

    /**
     * Capabilities a set of granted scopes unlocks, given a driver's
     * capability map (capability => any-of scopes).
     *
     * @param  array<string, string[]>  $map
     * @return string[]
     */
    public static function capabilitiesFrom(?array $grantedScopes, array $map): array
    {
        $granted = $grantedScopes ?? [];

        return array_values(array_filter(
            self::CAPABILITIES,
            fn ($capability) => (bool) array_intersect($map[$capability] ?? [], $granted)
        ));
    }
}
