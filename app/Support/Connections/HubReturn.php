<?php

namespace App\Support\Connections;

use Illuminate\Support\Facades\Route;

/**
 * A driver's connect() marks the session (social_oauth_return_to = hub)
 * so the module callback that finishes the consent sends the user back to
 * the Connection Hub instead of that module's own page.
 */
class HubReturn
{
    public const SESSION_KEY = 'social_oauth_return_to';

    public static function mark(): void
    {
        session([self::SESSION_KEY => 'hub']);
    }

    /** The Hub when it started this flow (consumes the mark), else $default. */
    public static function route(string $default): string
    {
        if (session(self::SESSION_KEY) === 'hub' && Route::has('admin.connections.index')) {
            session()->forget(self::SESSION_KEY);

            return 'admin.connections.index';
        }

        return $default;
    }
}
