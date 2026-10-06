<?php

namespace App\Support;

use App\Models\SocialAccount;
use Illuminate\Support\Collection;

/**
 * Which of a seller's ad accounts Ads Manager works with, per platform.
 *
 * Every *AdService used to take ->first() SocialAccount for its platform,
 * so with two ad accounts (or a Facebook Page connected for posting next to
 * the ad account) the wrong one could be used silently, and there was no
 * way to choose. The choice is remembered in the session per platform and
 * used by both the campaign pages and the services, so what the seller
 * picks on screen is what campaigns are created in.
 */
class AdAccountSelection
{
    private const KEY = 'ad_account_selection';

    /** YouTube campaigns run on the Google Ads account. */
    public static function accountPlatform(string $platform): string
    {
        return $platform === 'youtube' ? 'google' : $platform;
    }

    /** The seller's ad-capable accounts for $platform, oldest first. */
    public static function options(int $userId, string $platform): Collection
    {
        return SocialAccount::where('user_id', $userId)
            ->where('platform', self::accountPlatform($platform))
            ->usableFor('ads')
            ->orderBy('id')
            ->get();
    }

    /** Remember $accountId for $platform if it's one of the seller's ad accounts. */
    public static function select(int $userId, string $platform, int $accountId): bool
    {
        $valid = self::options($userId, $platform)->contains('id', $accountId);

        if ($valid) {
            session()->put(self::KEY . '.' . self::accountPlatform($platform), $accountId);
        }

        return $valid;
    }

    /**
     * The selected account: the remembered choice if still valid, else the
     * first ad-capable account, else (previous behaviour) the first account
     * on that platform at all.
     */
    public static function resolve(int $userId, string $platform): ?SocialAccount
    {
        $options = self::options($userId, $platform);
        $chosen = session(self::KEY . '.' . self::accountPlatform($platform));

        return $options->firstWhere('id', $chosen)
            ?? $options->first()
            ?? SocialAccount::where('user_id', $userId)->where('platform', self::accountPlatform($platform))->first();
    }
}
