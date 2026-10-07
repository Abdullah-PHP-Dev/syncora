<?php

namespace App\Support\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use Illuminate\Support\Facades\Http;

/**
 * One Instagram account can be connected twice: through its Facebook Page
 * (Meta login - stored under the Instagram professional account ID) and
 * through Instagram Login (stored under an app-scoped ID). They look like
 * two accounts, so a post picked for both publishes twice.
 *
 * Instagram Login's GET /me `user_id` is that same professional account ID
 * (Instagram API with Instagram Login, "User" fields), which identifies the
 * pair. The Page-linked account wins - it carries posting, the inbox and
 * ads - and the Instagram Login copy has posting and messaging turned off
 * (enabled_capabilities, the Hub's per-account switches; the user can turn
 * them back on there). Marked once, so a later choice in the Hub sticks.
 */
class InstagramDuplicates
{
    public const MARK = 'duplicate_of';

    /** @return array<int, array{login: SocialAccount, twin: SocialAccount}> pairs found (and resolved unless dry-run) */
    public static function resolve(int $userId, bool $dryRun = false): array
    {
        $accounts = SocialAccount::where('user_id', $userId)->where('platform', 'instagram')->get();
        $pageLinked = $accounts->reject(fn ($a) => self::isInstagramLogin($a))->keyBy('platform_account_id');

        if ($pageLinked->isEmpty()) {
            return [];
        }

        $pairs = [];

        foreach ($accounts->filter(fn ($a) => self::isInstagramLogin($a)) as $login) {
            $settings = $login->metadata['settings'] ?? [];

            if (isset($settings[self::MARK])) {
                continue;
            }

            $igUserId = $settings['ig_user_id'] ?? self::fetchIgUserId($login);
            $twin = $igUserId ? $pageLinked->get($igUserId) : null;

            if (! $twin) {
                continue;
            }

            $pairs[] = ['login' => $login, 'twin' => $twin];

            if (! $dryRun) {
                $metadata = $login->metadata ?? [];
                $metadata['settings'] = array_merge($settings, ['ig_user_id' => $igUserId, self::MARK => $twin->id]);
                $enabled = $login->enabled_capabilities ?? SocialConnection::CAPABILITIES;

                $login->forceFill([
                    'metadata' => $metadata,
                    'enabled_capabilities' => array_values(array_diff($enabled, ['posting', 'messaging'])),
                ])->saveQuietly();
            }
        }

        return $pairs;
    }

    public static function isInstagramLogin(SocialAccount $account): bool
    {
        return ($account->metadata['settings']['auth_type'] ?? null) === 'instagram_login';
    }

    /** Older Instagram Login rows didn't store user_id - ask Instagram once. */
    private static function fetchIgUserId(SocialAccount $account): ?string
    {
        if (! $account->access_token) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get('https://graph.instagram.com/me', ['fields' => 'user_id', 'access_token' => $account->access_token]);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? ($response->json('user_id') ? (string) $response->json('user_id') : null) : null;
    }
}
