<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class McpSocialAccountsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan('mcp:read'), 403);
        $query = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'platform' => ['sometimes', 'string', 'max:30', 'regex:/^[a-z_]+$/'],
        ]);
        $fields = ['id', 'platform', 'name', 'username', 'is_token_valid', 'has_posting_permission'];
        $accounts = SocialAccount::where('user_id', $user->id)->select($fields)
            ->when(isset($query['platform']), fn ($q) => $q->where('platform', $query['platform']))
            ->orderBy('id')->paginate($query['per_page'] ?? 20, ['*'], 'page', $query['page'] ?? 1);

        return response()->json([
            'data' => $accounts->getCollection()->map(fn ($account) => $account->only($fields))->values(),
            'meta' => [
                'current_page' => $accounts->currentPage(), 'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(), 'total' => $accounts->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
