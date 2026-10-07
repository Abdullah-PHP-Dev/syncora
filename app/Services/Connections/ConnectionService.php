<?php

namespace App\Services\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one entry point modules use for connections
 * (docs/connection-hub-design.md §3).
 */
class ConnectionService
{
    /** @return array<string, class-string<ProviderDriver>> */
    public function platforms(): array
    {
        return config('connections.drivers', []);
    }

    public function driver(string $platform): ProviderDriver
    {
        $class = $this->platforms()[$platform] ?? null;

        abort_unless($class, 404, "No connection driver for [{$platform}].");

        return app($class);
    }

    public function connect(string $platform, string $step): Response
    {
        $driver = $this->driver($platform);
        $known = collect($driver->steps())->firstWhere('key', $step);

        abort_unless($known && $known['available'], 404);

        return $driver->connect($step);
    }

    /**
     * Token a module should use to act on an asset, or null when it may not:
     *  - the asset's Page / Instagram token (asset_token) when it has one,
     *  - else the asset's own access_token - every connect and refresh path
     *    keeps it current, and some platforms (X) rotate tokens on the asset
     *    itself, which leaves the connection's copy behind,
     *  - else the connection's token.
     * Null when the capability is switched off for this asset in the Hub or
     * the connection isn't usable.
     */
    public function tokenFor(SocialAccount $asset, string $capability): ?string
    {
        if ($asset->enabled_capabilities !== null && ! in_array($capability, $asset->enabled_capabilities, true)) {
            return null;
        }

        $connection = $asset->connection;

        if ($connection === null) {
            return $asset->access_token; // not migrated to a connection yet (dual-read, design §9)
        }

        if (! $connection->isUsable()) {
            return null;
        }

        return $asset->asset_token ?: ($asset->access_token ?: $connection->access_token);
    }

    /**
     * Can this user use a capability on a platform right now? When not, the
     * result says why and where to send them - an inline "Connect",
     * "Reconnect" or "Upgrade access" prompt, never a separate flow.
     *
     * Looks at every connection the user has on the platform (an older
     * consent, e.g. google.ads_legacy, still counts); the link always points
     * at the step whose consent grants the capability.
     *
     * @return array{ok: bool, reason: ?string, url: ?string, connection: ?SocialConnection}
     */
    public function ensure(int $userId, string $platform, string $capability): array
    {
        $driver = $this->driver($platform);
        $url = route('admin.connections.connect', ['platform' => $platform, 'step' => $driver->stepFor($capability)]);

        $connections = SocialConnection::where(['user_id' => $userId, 'platform' => $platform])
            ->latest('updated_at')
            ->get()
            ->reject(fn ($c) => $c->status === SocialConnection::REVOKED && $c->last_error === SocialConnection::DISCONNECTED_BY_USER);

        if ($ok = $connections->first(fn ($c) => $c->isUsable() && $c->hasCapability($capability))) {
            return ['ok' => true, 'reason' => null, 'url' => null, 'connection' => $ok];
        }

        if ($connections->isEmpty()) {
            return ['ok' => false, 'reason' => 'not_connected', 'url' => $url, 'connection' => null];
        }

        // Had the capability but the consent stopped working -> reconnect;
        // connected without it -> request it (Meta re-runs the config,
        // Google adds include_granted_scopes).
        $broken = $connections->first(fn ($c) => $c->hasCapability($capability));

        return $broken
            ? ['ok' => false, 'reason' => 'reconnect', 'url' => $url, 'connection' => $broken]
            : ['ok' => false, 'reason' => 'upgrade', 'url' => $url, 'connection' => $connections->first()];
    }

    public function validate(SocialConnection $connection): SocialConnection
    {
        return $this->driver($connection->platform)->validate($connection);
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->driver($connection->platform)->disconnect($connection);
    }
}
