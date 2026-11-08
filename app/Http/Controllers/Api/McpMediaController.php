<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaAssetService;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;

class McpMediaController extends Controller
{
    public function __invoke(Request $request, MediaAssetService $media, PackageAccessService $access)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan('mcp:media:write'), 403);
        $access->authorize($user, 'media', true);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240']]);
        $asset = $media->store($request->file('file'), $user->id);

        return response()->json(['media' => $asset->only([
            'id', 'url', 'original_name', 'mime_type', 'size', 'width', 'height', 'type',
        ])], 201)->header('Cache-Control', 'no-store');
    }
}
