<?php

namespace App\Support\Connections;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

/**
 * One Page / account, one tenant. A connected account (a social_accounts row
 * that still holds credentials) belongs to exactly one workspace account
 * (user_id); every other workspace or organization is refused, matched on the
 * platform's own ID, never on names. Disconnected rows (tokens cleared by
 * BaseDriver::disconnectLocally) keep their history but no longer hold the account.
 */
final class AccountOwnership
{
    public const SESSION_KEY = 'connections.skipped';

    /** Same platform + platform ID, still connected, under a different workspace or organization. */
    public static function heldElsewhere(string $platform, string|int|null $externalId, ?int $userId): bool
    {
        if ($externalId === null || $externalId === '' || ! $userId) {
            return false;
        }

        return SocialAccount::query()
            ->where('platform', $platform)
            ->whereIn('platform_account_id', self::variants($platform, (string) $externalId))
            ->where('user_id', '!=', $userId)
            ->where(fn ($q) => $q->whereNotNull('access_token')->orWhereNotNull('asset_token'))
            ->exists();
    }

    /**
     * For import loops that add several accounts at once: true to go ahead,
     * false to skip this one (remembered for the Connections page message).
     */
    public static function allows(string $platform, string|int|null $externalId, ?int $userId, ?string $label = null): bool
    {
        if (! self::heldElsewhere($platform, $externalId, $userId)) {
            return true;
        }
        self::rememberSkipped($label ?: (string) $externalId);
        Log::info('Account not connected: it is connected to another workspace or organization.', ['platform' => $platform, 'user_id' => $userId]);

        return false;
    }

    public static function rememberSkipped(string $label): void
    {
        // Shown on the next Connections page load; background syncs just leave the account out.
        if (! app()->bound('session')) {
            return;
        }
        session()->put(self::SESSION_KEY, array_values(array_unique([...(array) session(self::SESSION_KEY, []), $label])));
    }

    /** @return string[] names of accounts left out since the last Connections page load */
    public static function pullSkipped(): array
    {
        return app()->bound('session') ? (array) session()->pull(self::SESSION_KEY, []) : [];
    }

    /** Meta ad accounts are stored both as "123" and "act_123". */
    public static function variants(string $platform, string $externalId): array
    {
        if ($platform === 'facebook' && preg_match('/^(act_)?(\d+)$/', $externalId, $m)) {
            return [$m[2], 'act_'.$m[2]];
        }

        return [$externalId];
    }

    /** Lock key per platform account, shared by both ID spellings. */
    public static function lockKey(string $platform, string $externalId): string
    {
        return 'social-account-owner:'.$platform.':'.self::variants($platform, $externalId)[0];
    }
}
