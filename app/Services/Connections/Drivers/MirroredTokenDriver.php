<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;

/**
 * For platforms whose module services refresh the account's tokens
 * themselves - often rotating the refresh token on every use (X, TikTok,
 * Pinterest, Snapchat). Refreshing here as well would race those services
 * and invalidate their tokens, so validation never refreshes: it mirrors
 * the account's latest tokens onto the connection and asks the provider
 * only while the access token is still valid.
 */
abstract class MirroredTokenDriver extends BaseDriver
{
    /**
     * One cheap authenticated call with a still-valid access token.
     *
     * @return array{status: string, error: string}|null null when the token works
     */
    abstract protected function probe(SocialConnection $connection, SocialAccount $asset): ?array;

    public function validate(SocialConnection $connection): SocialConnection
    {
        $asset = $connection->assets()->latest('updated_at')->first();

        if (! $asset || (! $asset->access_token && ! $asset->refresh_token)) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'No token stored - reconnect.');
        }

        $connection->fill([
            'access_token' => $asset->access_token,
            'refresh_token' => $asset->refresh_token,
            'expires_at' => $asset->expires_at,
        ]);

        $stillValid = $asset->access_token && ($asset->expires_at === null || $asset->expires_at->isFuture());

        if ($stillValid && ($problem = $this->probe($connection, $asset))) {
            return $this->mark($connection, $problem['status'], $problem['error']);
        }

        return $this->markHealthy($connection, null);
    }

    /** Shared mapping: 401 = token no longer accepted, anything else unexpected = error. */
    protected function problemFromStatus(int $status, ?string $message): ?array
    {
        if ($status >= 200 && $status < 300) {
            return null;
        }

        return $status === 401
            ? ['status' => SocialConnection::NEEDS_REAUTH, 'error' => $message ?: 'The platform no longer accepts this token.']
            : ['status' => SocialConnection::ERROR, 'error' => $message ?: 'HTTP ' . $status];
    }
}
