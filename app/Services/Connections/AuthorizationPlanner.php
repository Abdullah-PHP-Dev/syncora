<?php

namespace App\Services\Connections;

/**
 * Turns the capabilities a customer picked into the provider consents to run
 * (docs/connection-capabilities.md §3). Capabilities granted by the same
 * consent share one sign-in; ones granted by another consent get their own
 * step; ones already working, awaiting provider approval or unsupported are
 * reported instead of being sent to the provider.
 *
 * The plan works at consent granularity: each driver step keeps the scope
 * set its provider verified (Google's consent screen, Meta's App Review /
 * Login configuration), so nothing outside that set is ever requested.
 */
class AuthorizationPlanner
{
    public function __construct(private ConnectionService $connections, private CapabilityCatalog $catalog)
    {
    }

    /**
     * @param  string[]  $capabilities  validated against the catalog by the caller
     * @return array{platform: string, steps: array<int, array{key: string, label: string, description: string, capabilities: string[], reconnect: bool}>,
     *               connected: string[], blocked: array<int, array{key: string, status: string}>}
     */
    public function plan(int $userId, string $platform, array $capabilities): array
    {
        $rows = collect($this->catalog->forUser($userId, $platform))
            ->whereIn('key', array_values(array_unique($capabilities)))
            ->keyBy('key');

        $selectable = $rows->where('selectable', true);
        $byStep = $selectable->groupBy('step');

        // Driver order, so the main consent always comes first.
        $steps = collect($this->connections->driver($platform)->steps())
            ->filter(fn ($step) => $byStep->has($step['key']))
            ->map(fn ($step) => [
                'key' => $step['key'],
                'label' => $step['label'],
                'description' => $step['description'],
                'capabilities' => $byStep[$step['key']]->pluck('key')->values()->all(),
                // Running a consent again adds what's missing; the provider
                // keeps earlier grants (Meta, Google include_granted_scopes).
                'reconnect' => $byStep[$step['key']]->contains(fn ($row) => $row['status'] !== CapabilityCatalog::AVAILABLE),
            ])
            ->values()
            ->all();

        return [
            'platform' => $platform,
            'steps' => $steps,
            'connected' => $rows->where('status', CapabilityCatalog::CONNECTED)->keys()->values()->all(),
            'blocked' => $rows->where('selectable', false)
                ->reject(fn ($row) => $row['status'] === CapabilityCatalog::CONNECTED)
                ->map(fn ($row) => ['key' => $row['key'], 'status' => $row['status']])
                ->values()
                ->all(),
        ];
    }
}
