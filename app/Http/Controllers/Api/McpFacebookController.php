<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McpFacebookController extends Controller
{
    private function authorize(Request $request, PackageAccessService $access, bool $write = false): void
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan($write ? 'mcp:posts:write' : 'mcp:read'), 403);
        $access->authorize($user, 'publishing', $write);
    }

    private function pagesQuery(int $userId)
    {
        return SocialAccount::where('user_id', $userId)->where('platform', 'facebook')->active()->usableFor('posting')
            ->where(fn ($q) => $q->whereNull('social_connection_id')->orWhereHas('connection', fn ($c) => $c->whereIn('status', SocialConnection::USABLE)));
    }

    public function pages(Request $request, PackageAccessService $access)
    {
        $this->authorize($request, $access);
        $query = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:50']);
        $pages = $this->pagesQuery($request->user()->id)->orderBy('id')->paginate($query['per_page'] ?? 20, ['id', 'platform', 'name', 'username', 'platform_account_id'], 'page', $query['page'] ?? 1);
        return response()->json(['data' => $pages->items(), 'meta' => [
            'current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(), 'per_page' => $pages->perPage(), 'total' => $pages->total(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, PackageAccessService $access)
    {
        $this->authorize($request, $access, true);
        $input = $request->validate([
            'social_account_id' => 'required|integer|min:1', 'content' => 'sometimes|nullable|string|max:63206',
            'media_asset_id' => 'sometimes|nullable|integer|min:1', 'idempotency_key' => 'required|uuid',
        ]);
        $payload = ['social_account_id' => (int) $input['social_account_id'], 'content' => $input['content'] ?? '', 'media_asset_id' => isset($input['media_asset_id']) ? (int) $input['media_asset_id'] : null];
        if (trim($payload['content']) === '' && $payload['media_asset_id'] === null) {
            throw ValidationException::withMessages(['content' => 'Provide post text or a gallery media asset.']);
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $user = $request->user();
        [$post, $replayed] = DB::transaction(function () use ($user, $input, $payload, $hash, $access) {
            // Serialize retries on the same owner before taking any package locks.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $key = strtolower($input['idempotency_key']);
            $saved = DB::table('mcp_post_requests')->where(['user_id' => $user->id, 'idempotency_key' => $key])->first();
            if ($saved) {
                abort_unless(hash_equals($saved->payload_hash, $hash), 409, 'Idempotency key already used for different content.');
                $post = Post::where('user_id', $user->id)->where('platform', 'facebook')->find($saved->post_id);
                abort_unless($post, 409, 'The original post no longer exists.');
                return [$post, true];
            }
            $account = $this->pagesQuery($user->id)->whereKey($payload['social_account_id'])->firstOrFail();
            $asset = null;
            if ($payload['media_asset_id']) {
                $access->authorize($user, 'media', false);
                $asset = MediaAsset::ownedBy($user->id)->whereKey($payload['media_asset_id'])->lockForUpdate()->firstOrFail();
                if (! in_array($asset->type, ['image', 'video'], true) || ! in_array('facebook', $asset->compatible_platforms ?? [], true)
                    || ($asset->type === 'image' && $asset->extension === 'gif')) {
                    throw ValidationException::withMessages(['media_asset_id' => 'Choose a Facebook-compatible static image or video from your gallery.']);
                }
            }
            // The existing Post model enforces and reserves subscription usage.
            // The scheduler only sees this pending post after the transaction commits.
            $post = Post::create([
                'user_id' => $user->id, 'social_account_id' => $account->id, 'platform' => 'facebook',
                'title' => 'Facebook post', 'content' => $payload['content'], 'visibility' => 'public',
                'status' => 'pending', 'schedule_mode' => 0, 'schedule_at' => null,
                'expiry_mode' => 0, 'expiry_at' => null,
            ]);
            if ($asset) {
                PostMedia::create([
                    'post_id' => $post->id, 'media_asset_id' => $asset->id, 'user_id' => $user->id,
                    'platform' => 'facebook', 'social_account_id' => $account->id,
                    'media_url' => $asset->url, 'media_type' => $asset->type,
                    'file_name' => basename($asset->path), 'file_size' => $asset->size,
                    'width' => $asset->width, 'height' => $asset->height, 'duration_seconds' => $asset->duration,
                    'thumbnail_url' => $asset->thumbnail_url, 'alt_text' => $asset->original_name,
                ]);
            }
            DB::table('mcp_post_requests')->insert([
                'user_id' => $user->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'post_id' => $post->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return [$post, false];
        });
        return response()->json(['post' => $this->summary($post), 'replayed' => $replayed], $replayed ? 200 : 202)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, int $post, PackageAccessService $access)
    {
        $this->authorize($request, $access);
        $record = Post::where('user_id', $request->user()->id)->where('platform', 'facebook')->findOrFail($post);
        return response()->json(['post' => $this->summary($record)])->header('Cache-Control', 'no-store');
    }

    private function summary(Post $post): array
    {
        return $post->only(['id', 'platform', 'social_account_id', 'content', 'status', 'post_id', 'post_url', 'created_at']) + [
            'published' => $post->status === 'completed' && (bool) $post->post_id,
            'status_message' => match ($post->status) {
                'pending', 'scheduled' => 'Queued for background publishing. Not yet published.',
                'publishing' => 'Publishing is in progress. Do not submit another post.',
                'completed' => 'Publishing completed.',
                'failed' => 'Publishing failed. Check the website for details and Facebook before retrying.',
                default => 'Check the website for the current post state.',
            },
        ];
    }
}
