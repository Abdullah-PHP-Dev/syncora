<?php

namespace App\Services\Connections;

use App\Models\SocialConnection;
use App\Support\Connections\ConnectionFlags;
use Illuminate\Support\Collection;

/**
 * What a customer can pick on a platform's panel and where each choice
 * stands for them (docs/connection-capabilities.md). Statuses come from the
 * granted scopes on their connections - never from an OAuth round trip.
 */
class CapabilityCatalog
{
    /** Nobody has asked for it yet; the consent can grant it. */
    public const AVAILABLE = 'available';
    /** A working consent grants it. */
    public const CONNECTED = 'connected';
    /** The consent ran but this permission wasn't granted (unticked, or not approved for the app). */
    public const NOT_GRANTED = 'not_granted';
    /** Was granted, but the consent stopped working (expired / revoked). */
    public const RECONNECT = 'reconnect';
    /** The provider must approve the app before anyone can grant it. */
    public const APPROVAL = 'approval_required';
    /** No consent grants it: unsupported, or the app isn't configured here. */
    public const UNAVAILABLE = 'unavailable';

    /** Statuses a customer can still authorize from the panel. */
    public const SELECTABLE = [self::AVAILABLE, self::NOT_GRANTED, self::RECONNECT];

    public function __construct(private ConnectionService $connections)
    {
    }

    public function supports(string $platform): bool
    {
        return (bool) $this->entries($platform);
    }

    /** @return array<string, array{step: ?string, approval?: string, partial?: bool}> */
    public function entries(string $platform): array
    {
        return config("connections.catalog.{$platform}", []);
    }

    /**
     * One row per catalog capability, in catalog order.
     *
     * @param  Collection<int, SocialConnection>|null  $connections  the user's connections on the platform
     * @return array<int, array{key: string, step: ?string, status: string, selectable: bool, partial: bool, account: ?string, note: ?string}>
     */
    public function forUser(int $userId, string $platform, ?Collection $connections = null): array
    {
        if (! $this->supports($platform)) {
            return [];
        }

        $connections = ($connections ?? SocialConnection::where(['user_id' => $userId, 'platform' => $platform])->get())
            ->reject(fn ($c) => $c->status === SocialConnection::REVOKED && $c->last_error === SocialConnection::DISCONNECTED_BY_USER);
        $steps = collect($this->connections->driver($platform)->steps())->keyBy('key');

        $rows = [];

        foreach ($this->entries($platform) as $capability => $entry) {
            $status = $this->status($capability, $entry, $steps, $connections);

            $rows[] = [
                'key' => $capability,
                'step' => $entry['step'] ?? null,
                'status' => $status,
                'selectable' => in_array($status, self::SELECTABLE, true),
                'partial' => (bool) ($entry['partial'] ?? false),
                'account' => $this->text("connections.catalog.{$platform}.{$capability}.account"),
                'note' => $this->text("connections.catalog.{$platform}.{$capability}.note"),
            ];
        }

        return $rows;
    }

    private function status(string $capability, array $entry, Collection $steps, Collection $connections): string
    {
        // Approval first: an unapproved product often has no step at all.
        if (isset($entry['approval']) && ! ConnectionFlags::on($entry['approval'])) {
            return self::APPROVAL;
        }

        $step = $entry['step'] ?? null;

        if (! $step || ! ($steps[$step]['available'] ?? false)) {
            return self::UNAVAILABLE;
        }

        if ($connections->contains(fn ($c) => $c->isUsable() && $c->hasCapability($capability))) {
            return self::CONNECTED;
        }

        if ($connections->contains(fn ($c) => $c->hasCapability($capability))) {
            return self::RECONNECT;
        }

        if ($connections->contains(fn ($c) => $c->step === $step && $c->isUsable())) {
            return self::NOT_GRANTED;
        }

        return self::AVAILABLE;
    }

    private function text(string $key): ?string
    {
        return trans()->has($key) ? __($key) : null;
    }
}
