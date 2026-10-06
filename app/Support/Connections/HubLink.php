<?php

namespace App\Support\Connections;

/**
 * Where a module page sends someone who wants to connect a platform
 * (docs/connection-hub-design.md §10). Platforms whose card lives in the
 * Connection Hub link there; everything else keeps its own connect flow
 * until its driver moves in.
 */
class HubLink
{
    /** Module platform key => Hub card anchor. */
    private const MANAGED = [
        'facebook' => 'meta',
        'instagram' => 'meta',
        'whatsapp' => 'meta',
    ];

    public static function managed(string $platform): bool
    {
        return isset(self::MANAGED[$platform]);
    }

    /** The Hub card URL for a platform, or null when it isn't managed there yet. */
    public static function for(string $platform): ?string
    {
        $card = self::MANAGED[$platform] ?? null;

        return $card ? route('admin.connections.index') . '#' . $card : null;
    }

    /** Short "managed in Connections" note for connect tiles. */
    public static function note(): string
    {
        return __('admin.connections.managed_in_hub');
    }
}
