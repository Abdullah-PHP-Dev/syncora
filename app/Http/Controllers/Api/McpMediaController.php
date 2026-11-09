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
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4', 'max:51200']]);
        if ($request->file('file')->getMimeType() !== 'video/mp4') {
            $request->validate(['file' => ['image', 'max:10240']]);
        }
        $asset = $media->store($request->file('file'), $user->id);

        return response()->json(['media' => $asset->only([
            'id', 'url', 'original_name', 'mime_type', 'size', 'width', 'height', 'type', 'duration', 'video_codec',
        ])], 201)->header('Cache-Control', 'no-store');
    }
}
