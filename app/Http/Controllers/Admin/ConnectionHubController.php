<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\HubPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connection Hub (docs/connection-hub-design.md §10): one place to connect
 * each platform once, for Ads, Publishing and Inbox alike.
 */
class ConnectionHubController extends Controller
{
    public function __construct(private ConnectionService $connections, private HubPresenter $presenter)
    {
    }

    public function index(): View
    {
        return view('admin.connections.index', [
            'hub' => $this->presenter->forUser((int) Auth::id()),
            'urls' => [
                'asset' => route('admin.connections.assets.update', ['socialAccount' => '__ID__']),
                'check' => route('admin.connections.check', ['connection' => '__ID__']),
                'disconnect' => route('admin.connections.disconnect', ['connection' => '__ID__']),
            ],
        ]);
    }

    /** Start a card step's consent (also the "Upgrade access" target). */
    public function connect(string $platform, string $step): Response
    {
        return $this->connections->connect($platform, $step);
    }

    /** Asset picker: which capabilities SocialEaz may use on this asset. */
    public function updateAsset(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        abort_unless($socialAccount->user_id === Auth::id() && $socialAccount->social_connection_id, 404);

        $validated = $request->validate([
            'enabled_capabilities' => ['present', 'array'],
            'enabled_capabilities.*' => ['string', Rule::in(SocialConnection::CAPABILITIES)],
        ]);

        $socialAccount->forceFill(['enabled_capabilities' => array_values(array_unique($validated['enabled_capabilities']))])->saveQuietly();

        return response()->json(['connection' => $this->presenter->connection($socialAccount->connection()->with('assets')->first())]);
    }

    /** "Check now": ask the provider for the connection's real state. */
    public function check(SocialConnection $connection): JsonResponse
    {
        abort_unless($connection->user_id === Auth::id(), 404);

        $this->connections->validate($connection);

        return response()->json(['connection' => $this->presenter->connection($connection->fresh('assets'))]);
    }

    public function disconnect(SocialConnection $connection): JsonResponse
    {
        abort_unless($connection->user_id === Auth::id(), 404);

        $this->connections->disconnect($connection);

        return response()->json(['card' => $this->presenter->card((int) Auth::id(), $connection->platform)]);
    }
}
