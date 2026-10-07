<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\HubPresenter;
use App\Support\Connections\ConnectionWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    public function __construct(private ConnectionService $connections, private HubPresenter $presenter, private ConnectionWizard $wizard)
    {
    }

    public function index(): View
    {
        return view('admin.connections.index', [
            'hub' => $this->presenter->forUser((int) Auth::id()),
            'wizard' => $this->wizardPayload(),
            'urls' => [
                'wizard_start' => route('admin.connections.wizard.start'),
                'wizard_skip' => route('admin.connections.wizard.skip'),
                'wizard_finish' => route('admin.connections.wizard.finish'),
                'asset' => route('admin.connections.assets.update', ['socialAccount' => '__ID__']),
                'check' => route('admin.connections.check', ['connection' => '__ID__']),
                'disconnect' => route('admin.connections.disconnect', ['connection' => '__ID__']),
            ],
        ]);
    }

    /** "Connect all recommended": straight into the first missing consent. */
    public function startWizard(): RedirectResponse|Response
    {
        $first = $this->wizard->start((int) Auth::id());

        return $first
            ? $this->connections->connect($first['platform'], $first['step'])
            : redirect()->route('admin.connections.index')->with('success', __('admin.connections.wizard.nothing_needed'));
    }

    public function skipWizard(): RedirectResponse
    {
        $this->wizard->skip((int) Auth::id());

        return redirect()->route('admin.connections.index');
    }

    public function finishWizard(): RedirectResponse
    {
        $this->wizard->finish();

        return redirect()->route('admin.connections.index');
    }

    /** What the Hub banner needs, with the next step's connect link. */
    private function wizardPayload(): array
    {
        $state = $this->wizard->state((int) Auth::id());

        if ($state && $state['next']) {
            $state['next']['url'] = route('admin.connections.connect', ['platform' => $state['next']['platform'], 'step' => $state['next']['step']]);
        }

        return [
            'state' => $state,
            'available' => $this->wizard->pending((int) Auth::id()) !== [],
        ];
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
