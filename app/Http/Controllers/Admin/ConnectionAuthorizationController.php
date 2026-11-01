<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Connections\AuthorizationPlanner;
use App\Services\Connections\CapabilityCatalog;
use App\Support\Connections\AuthorizationFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Choose what to connect" on a Hub panel (docs/connection-capabilities.md):
 * preview the plan, then walk the customer through its provider consents.
 * Capabilities and platform are re-validated against the catalog here;
 * nothing the browser sends decides which scopes are requested.
 */
class ConnectionAuthorizationController extends Controller
{
    public function __construct(private CapabilityCatalog $catalog, private AuthorizationPlanner $planner, private AuthorizationFlow $flow)
    {
    }

    /** JSON preview for the panel: which sign-ins the selection needs. */
    public function plan(Request $request, string $platform): JsonResponse
    {
        return response()->json($this->planner->plan((int) Auth::id(), $platform, $this->capabilities($request, $platform)));
    }

    public function start(Request $request, string $platform): RedirectResponse|Response
    {
        $plan = $this->flow->start((int) Auth::id(), $platform, $this->capabilities($request, $platform));

        // Nothing to sign in to: the Hub shows the summary straight away.
        return $plan['steps'] ? $this->flow->continue((int) Auth::id()) : $this->hub();
    }

    public function continueFlow(): RedirectResponse|Response
    {
        return $this->flow->continue((int) Auth::id()) ?? $this->hub();
    }

    public function skip(): RedirectResponse
    {
        $this->flow->skip((int) Auth::id());

        return $this->hub();
    }

    public function cancel(): RedirectResponse
    {
        $this->flow->finish();

        return $this->hub();
    }

    /** @return string[] */
    private function capabilities(Request $request, string $platform): array
    {
        abort_unless($this->catalog->supports($platform), 404);

        return $request->validate([
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string', Rule::in(array_keys($this->catalog->entries($platform)))],
        ])['capabilities'];
    }

    private function hub(): RedirectResponse
    {
        return redirect()->route('admin.connections.index');
    }
}
