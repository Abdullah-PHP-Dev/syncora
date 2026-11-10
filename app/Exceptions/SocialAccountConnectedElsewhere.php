<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A connection tried to add a Page / account another workspace or organization
 * already has. Never names that other tenant.
 */
class SocialAccountConnectedElsewhere extends RuntimeException
{
    public function __construct(public readonly string $label)
    {
        parent::__construct(__('connections.duplicate.one', ['name' => $label]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'code' => 'account_connected_elsewhere'], 409);
        }

        // Back to Connections in the same (session) workspace.
        return redirect()->route('admin.connections.index')->with('error', $this->getMessage());
    }
}
