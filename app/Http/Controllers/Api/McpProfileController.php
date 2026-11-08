<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;

class McpProfileController extends Controller
{
    public function __invoke(Request $request, PackageAccessService $access)
    {
        $user = $request->user();
        abort_unless($user->tokenCan('mcp:read'), 403);
        $subscription = $access->subscription($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subscription' => $subscription ? [
                'bundle_name' => $subscription->bundle_name,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'is_active' => $access->active($subscription),
            ] : null,
        ])->header('Cache-Control', 'no-store');
    }
}
