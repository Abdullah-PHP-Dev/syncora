<?php

namespace App\Services\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a consent as a SocialConnection and links the assets it produced
 * (docs/connection-hub-design.md §2-3). Called from the existing connect
 * callbacks, so the old per-module buttons and the Hub write identical data.
 *
 * Kept dependency-free on purpose: SocialAuthService and the controllers
 * call it, and drivers call those - injecting ConnectionService there would
 * be circular.
 */
class ConnectionRecorder
{
    /**
     * @param  array{access_token?: ?string, refresh_token?: ?string, token_secret?: ?string,
     *               expires_at?: mixed, granted_scopes?: ?array, provider_app?: ?string}  $attributes
     * @param  iterable<int>  $assetIds  social_accounts ids produced by this consent
     */
    public static function record(
        int $userId,
        string $platform,
        string $step,
        ?string $providerAccountId,
        array $attributes,
        iterable $assetIds = [],
    ): SocialConnection {
        $assetIds = collect($assetIds)->filter()->unique()->values();

        return DB::transaction(function () use ($userId, $platform, $step, $providerAccountId, $attributes, $assetIds) {
            $connection = self::find($userId, $platform, $step, $providerAccountId);

            $scopes = array_key_exists('granted_scopes', $attributes) && $attributes['granted_scopes'] !== null
                ? $attributes['granted_scopes']
                : $connection->granted_scopes; // a provider that reports no scopes never wipes known ones

            $expiresAt = isset($attributes['expires_at']) ? Carbon::parse($attributes['expires_at']) : null;

            $connection->fill(array_merge($attributes, [
                'provider_account_id' => $providerAccountId ?? $connection->provider_account_id,
                'expires_at' => $expiresAt,
                'granted_scopes' => $scopes,
                'capabilities' => self::capabilities($platform, $scopes, $assetIds),
                'status' => SocialConnection::statusFor(
                    $attributes['access_token'] ?? null,
                    $expiresAt,
                    ! empty($attributes['refresh_token']),
                    isset($attributes['refresh_expires_at']) ? Carbon::parse($attributes['refresh_expires_at']) : null,
                ),
                'last_error' => null,
                'revoked_at' => null,
                'last_checked_at' => now(),
            ]))->save();

            if ($assetIds->isNotEmpty()) {
                // Query builder: SocialAccount's model hooks write stat
                // tables, which linking must not touch.
                DB::table('social_accounts')->whereIn('id', $assetIds)->update(['social_connection_id' => $connection->id]);
            }

            return $connection;
        });
    }

    /**
     * Same consent = same row. A connection the backfill created without
     * knowing the provider account id is adopted by the first real connect;
     * a connect that couldn't learn the id updates the latest one.
     */
    private static function find(int $userId, string $platform, string $step, ?string $providerAccountId): SocialConnection
    {
        $base = SocialConnection::where(['user_id' => $userId, 'platform' => $platform, 'step' => $step]);

        if ($providerAccountId !== null) {
            $exact = (clone $base)->where('provider_account_id', $providerAccountId)->first();
            if ($exact) {
                return $exact;
            }

            $unidentified = (clone $base)->whereNull('provider_account_id')->first();
            if ($unidentified) {
                return $unidentified;
            }
        } elseif ($existing = (clone $base)->latest('updated_at')->first()) {
            // Provider account unknown (e.g. the /me lookup failed): the
            // user's latest connection for this step, never a duplicate.
            return $existing;
        }

        return new SocialConnection(['user_id' => $userId, 'platform' => $platform, 'step' => $step]);
    }

    /** From granted scopes when known, else the linked assets' legacy flags. */
    private static function capabilities(string $platform, ?array $scopes, $assetIds): array
    {
        $map = config("connections.capabilities.{$platform}");

        if ($scopes && $map) {
            return SocialConnection::capabilitiesFrom($scopes, $map);
        }

        $assets = SocialAccount::whereIn('id', $assetIds)->get(['has_posting_permission', 'has_messaging_permission', 'has_ads_permission']);

        return array_values(array_filter([
            $assets->contains('has_posting_permission', true) ? 'posting' : null,
            $assets->contains('has_messaging_permission', true) ? 'messaging' : null,
            $assets->contains('has_ads_permission', true) ? 'ads' : null,
        ]));
    }
}
