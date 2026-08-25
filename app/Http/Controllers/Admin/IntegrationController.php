<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\UserIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntegrationController extends Controller
{
    /**
     * Grid of every active integration, each annotated with the current
     * user's connection state (if any) so the view can render the
     * Connected/Not Connected badge and pre-fill the Setup form on edit.
     */
    public function index()
    {
        $userId = Auth::id();

        $integrations = Integration::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function (Integration $integration) use ($userId) {
                $userIntegration = UserIntegration::where('user_id', $userId)
                    ->where('integration_id', $integration->id)
                    ->first();

                $integration->setAttribute('user_integration', $userIntegration);

                return $integration;
            })
            ->groupBy('category');

        return view('admin.integrations.index', compact('integrations'));
    }

    /**
     * Save (or update) the current user's credentials for one integration.
     * Every service here is a pasted ID/token - see
     * Integration::getCredentialFieldsAttribute() - so this is one generic
     * handler rather than a method per service.
     */
    public function store(Request $request, Integration $integration)
    {
        $existing = UserIntegration::where('user_id', Auth::id())
            ->where('integration_id', $integration->id)
            ->first();

        // First connect requires every field; editing an already-connected
        // service allows blanks - the Setup tab tells the user a blank
        // field keeps its current value rather than erasing it.
        $rules = [];
        foreach ($integration->credential_fields as $field) {
            $rules["credentials.{$field['key']}"] = [$existing ? 'nullable' : 'required', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);

        $credentials = $existing->credentials ?? [];
        foreach (($validated['credentials'] ?? []) as $key => $value) {
            if ($value !== null && $value !== '') {
                $credentials[$key] = $value;
            }
        }

        $userIntegration = UserIntegration::updateOrCreate(
            ['user_id' => Auth::id(), 'integration_id' => $integration->id],
            [
                'credentials'     => $credentials,
                'is_enabled'      => true,
                'last_synced_at'  => now(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$integration->name} connected successfully.",
                'connected_at' => $userIntegration->last_synced_at->toDateTimeString(),
            ]);
        }

        return back()->with('success', "{$integration->name} connected successfully.");
    }

    /**
     * Disconnect a service - removes the stored credentials entirely rather
     * than just toggling is_enabled off, matching "Disconnect" as a
     * destructive action in the UI (reconnecting means re-entering the
     * credential, same as connecting fresh).
     */
    public function destroy(UserIntegration $userIntegration, Request $request)
    {
        if ($userIntegration->user_id !== Auth::id()) {
            abort(403);
        }

        $name = $userIntegration->integration->name;
        $userIntegration->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "{$name} disconnected."]);
        }

        return back()->with('success', "{$name} disconnected.");
    }
}
