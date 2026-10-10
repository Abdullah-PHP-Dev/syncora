<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\ConnectionRecorder;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\OpenAiDriver;
use App\Services\Connections\HubPresenter;
use App\Support\Connections\ConnectionWizard;
use App\Support\Connections\OpenAiAds;
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
                'whatsapp_manual' => route('admin.post-accounts.whatsapp.store'),
                'openai_key' => route('admin.connections.openai.key'),
                'composer' => route('admin.posts.composer'),
                'ads' => route('admin.ads.dashboard'),
                'help' => route('admin.help-center.index'),
                'inbox' => route('admin.chats.dashboard'),
                'ads_create' => route('admin.ads.campaigns.create', ['platform' => '__PLATFORM__']),
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

    /**
     * ChatGPT Ads: an Ads API key from ads.openai.com > Settings. Checked
     * with GET /ad_account before anything is saved; one key = one ad account.
     */
    public function connectOpenAi(Request $request): RedirectResponse
    {
        $validated = $request->validate(['api_key' => ['required', 'string', 'min:20', 'max:500']]);
        $key = trim($validated['api_key']);
        $hub = route('admin.connections.index') . '#openai';

        try {
            $response = OpenAiAds::adAccount($key);
        } catch (\Throwable $e) {
            return redirect()->to($hub)->with('error', 'Could not reach OpenAI Ads. Please try again.');
        }

        if (! $response->successful() || ! $response->json('id')) {
            return redirect()->to($hub)->with('error', in_array($response->status(), [401, 403], true)
                ? 'OpenAI did not accept this API key. Copy it again from ads.openai.com > Settings.'
                : OpenAiAds::errorMessage($response, 'Could not read the ChatGPT Ads account for this key.'));
        }

        $account = $response->json();
        $userId = (int) Auth::id();

        $asset = SocialAccount::updateOrCreate(
            ['platform' => 'openai', 'platform_account_id' => (string) $account['id'], 'user_id' => $userId],
            [
                'name' => $account['name'] ?? 'ChatGPT Ads account',
                'account_type' => 'ad_account',
                'access_token' => $key,
                'is_token_valid' => true,
                'has_ads_permission' => true,
                'metadata' => array_filter(['currency' => $account['currency'] ?? null, 'timezone' => $account['timezone'] ?? null]),
            ]
        );
        $asset->syncAdDetails(array_filter(['currency' => $account['currency'] ?? null, 'account_status' => $account['status'] ?? null]));

        ConnectionRecorder::record($userId, 'openai', OpenAiDriver::API_KEY, (string) $account['id'], [
            'provider_app' => 'ads.openai',
            'access_token' => $key,
        ], [$asset->id]);

        return redirect()->to($hub)->with('success', 'Connected ChatGPT Ads account "' . $asset->name . '".');
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
