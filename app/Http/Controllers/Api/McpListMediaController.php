<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;

class McpListMediaController extends Controller
{
    public function __invoke(Request $request, PackageAccessService $access)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan('mcp:read'), 403);
        $access->authorize($user, 'media', false);
        $query = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'type' => 'sometimes|in:image,video',
            'q' => 'sometimes|string|max:100',
            'sort' => 'sometimes|in:newest,oldest,largest,name',
        ]);
        $sort = [
            'newest' => ['created_at', 'desc'], 'oldest' => ['created_at', 'asc'],
            'largest' => ['size', 'desc'], 'name' => ['original_name', 'asc'],
        ][$query['sort'] ?? 'newest'];
        $fields = ['id', 'type', 'url', 'thumbnail_url', 'original_name', 'mime_type', 'size', 'width', 'height', 'duration', 'video_codec', 'compatible_platforms', 'created_at'];
        $assets = MediaAsset::ownedBy($user->id)->select($fields)
            ->when(isset($query['type']), fn ($q) => $q->where('type', $query['type']))
            // A bound parameter prevents SQL injection; escape LIKE wildcards
            // using an explicit character so behavior is portable across DBs.
            ->when(isset($query['q']), function ($builder) use ($query) {
                $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query['q']);
                $builder->whereRaw("original_name LIKE ? ESCAPE '!'", ['%'.$term.'%']);
            })
            ->orderBy(...$sort)->orderByDesc('id')
            ->paginate($query['per_page'] ?? 20, ['*'], 'page', $query['page'] ?? 1);

        return response()->json([
            'data' => $assets->getCollection()->map(fn ($asset) => $asset->only($fields))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(), 'total' => $assets->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
