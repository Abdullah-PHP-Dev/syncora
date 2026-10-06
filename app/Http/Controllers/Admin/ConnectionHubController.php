<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Connections\ConnectionService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connection Hub (docs/connection-hub-design.md §10): one place to connect
 * each platform once, for Ads, Publishing and Inbox alike.
 */
class ConnectionHubController extends Controller
{
    public function __construct(private ConnectionService $connections)
    {
    }

    /** Start a card step's consent (also the "Upgrade access" target). */
    public function connect(string $platform, string $step): Response
    {
        return $this->connections->connect($platform, $step);
    }
}
