<?php

namespace App\View\Components;

use App\Services\Connections\ConnectionService;
use App\Support\Connections\HubLink;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Inline "Reconnect" / "Upgrade access" prompts on module pages
 * (docs/connection-hub-design.md §3, §10) - never a separate connect flow,
 * always a link into the Connection Hub's consent for that capability.
 *
 *   <x-connection-alerts capability="ads" />                 every Hub platform offering ads
 *   <x-connection-alerts capability="ads" platform="tiktok" show-not-connected />
 *
 * Platforms the user never connected stay silent unless show-not-connected
 * is set, so nobody is nagged about platforms they don't use.
 */
class ConnectionAlerts extends Component
{
    /** @var array<int, array{platform: string, label: string, reason: string, url: string}> */
    public array $alerts = [];

    public function __construct(
        public string $capability,
        ?string $platform = null,
        bool $showNotConnected = false,
    ) {
        if (! Auth::check()) {
            return;
        }

        $connections = app(ConnectionService::class);
        $platforms = $platform !== null
            ? array_filter([HubLink::card($platform) ?? (array_key_exists($platform, $connections->platforms()) ? $platform : null)])
            : array_keys($connections->platforms());

        foreach ($platforms as $card) {
            $driver = $connections->driver($card);

            if (! in_array($capability, $driver->presentation()['benefits'] ?? [], true)) {
                continue;
            }

            $result = $connections->ensure((int) Auth::id(), $card, $capability);

            if ($result['ok'] || ($result['reason'] === 'not_connected' && ! $showNotConnected)) {
                continue;
            }

            $this->alerts[] = ['platform' => $card, 'label' => $driver->label(), 'reason' => $result['reason'], 'url' => $result['url']];
        }
    }

    public function shouldRender(): bool
    {
        return $this->alerts !== [];
    }

    public function render(): View
    {
        return view('components.connection-alerts');
    }
}
