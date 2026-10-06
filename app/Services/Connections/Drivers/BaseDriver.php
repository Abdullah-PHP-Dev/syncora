<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialConnection;
use App\Services\Connections\ProviderDriver;
use Illuminate\Support\Facades\DB;

/**
 * What every driver shares: recording a validation outcome and the local
 * half of a disconnect. Platform-specific provider calls stay in the drivers.
 */
abstract class BaseDriver implements ProviderDriver
{
    /** Record a validation outcome that isn't "fine". */
    protected function mark(SocialConnection $connection, string $status, string $error, bool $revoked = false): SocialConnection
    {
        $connection->fill([
            'status' => $status,
            'last_error' => $error,
            'last_checked_at' => now(),
            'revoked_at' => $revoked ? now() : $connection->revoked_at,
        ])->save();

        return $connection;
    }

    /** Record a successful validation with what the provider reported. */
    protected function markHealthy(SocialConnection $connection, ?array $grantedScopes): SocialConnection
    {
        $scopes = $grantedScopes ?? $connection->granted_scopes;
        $map = config("connections.capabilities.{$this->platform()}");

        $connection->fill([
            'granted_scopes' => $scopes,
            'capabilities' => $scopes && $map ? SocialConnection::capabilitiesFrom($scopes, $map) : $connection->capabilities,
            'last_error' => null,
            'last_checked_at' => now(),
        ]);
        $connection->status = $connection->timeStatus();
        $connection->save();

        return $connection;
    }

    /**
     * Local half of a disconnect: tokens cleared, status revoked. Assets
     * stay (campaign/post history points at them) but can no longer act;
     * reconnecting re-links and re-validates them.
     */
    protected function disconnectLocally(SocialConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            $connection->fill([
                'access_token' => null,
                'refresh_token' => null,
                'token_secret' => null,
                'status' => SocialConnection::REVOKED,
                'revoked_at' => now(),
                'last_error' => SocialConnection::DISCONNECTED_BY_USER,
            ])->save();

            DB::table('social_accounts')->where('social_connection_id', $connection->id)->update([
                'is_token_valid' => false,
                'access_token' => null,
                'refresh_token' => null,
                'asset_token' => null,
                'user_token' => null,
            ]);
        });
    }
}
