<?php

namespace App\Support\Connections;

/**
 * Feature flags for the Connection Hub (docs/connection-hub-design.md §8).
 * Defaults live in config('connections.flags'); the admin setting
 * `connections.flags.<flag>` overrides them per environment.
 */
class ConnectionFlags
{
    public static function get(string $flag): mixed
    {
        $defaults = config('connections.flags', []);

        if (! array_key_exists($flag, $defaults)) {
            throw new \InvalidArgumentException("Unknown connection flag [{$flag}].");
        }

        $override = adminSetting("connections.flags.{$flag}");

        if ($override === null || $override === '') {
            return $defaults[$flag];
        }

        // Settings are stored as strings; boolean flags accept 1/0/true/false/on/off.
        return is_bool($defaults[$flag])
            ? filter_var($override, FILTER_VALIDATE_BOOLEAN)
            : $override;
    }

    public static function on(string $flag): bool
    {
        return (bool) self::get($flag);
    }
}
