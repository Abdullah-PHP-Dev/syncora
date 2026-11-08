<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use Illuminate\Http\Request;

class McpConnectionSummaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan('mcp:read'), 403);
        // Match the Connection Hub's ownership scope. Aggregate in SQL so no
        // provider tokens or account metadata are loaded or serialized.
        $connections = SocialConnection::where('user_id', $user->id)
            ->select('platform', 'status')->selectRaw('COUNT(*) as total')->groupBy('platform', 'status')->get();
        $accounts = SocialAccount::where('user_id', $user->id)
            ->select('platform')->selectRaw('COUNT(*) as total')->groupBy('platform')->get();
        $platforms = [];
        $empty = ['total_connections' => 0, 'usable_connections' => 0, 'total_accounts' => 0];
        foreach ($connections as $row) {
            $platforms[$row->platform] ??= ['platform' => $row->platform] + $empty;
            $platforms[$row->platform]['total_connections'] += (int) $row->total;
            if (in_array($row->status, SocialConnection::USABLE, true)) {
                $platforms[$row->platform]['usable_connections'] += (int) $row->total;
            }
        }
        foreach ($accounts as $row) {
            $platforms[$row->platform] ??= ['platform' => $row->platform] + $empty;
            $platforms[$row->platform]['total_accounts'] = (int) $row->total;
        }
        ksort($platforms);
        $totals = $empty;
        foreach (array_keys($totals) as $field) {
            $totals[$field] = array_sum(array_column($platforms, $field));
        }

        return response()->json($totals + ['platforms' => array_values($platforms)])->header('Cache-Control', 'no-store');
    }
}
