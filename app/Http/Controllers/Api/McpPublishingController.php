<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\PackageAccessService;
use App\Support\MediaCompatibility;
use App\Support\PlatformTextLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McpPublishingController extends Controller
{
    private function access(Request $request, PackageAccessService $access, bool $write = false): void
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tokenCan($write ? 'mcp:posts:write' : 'mcp:read'), 403);
        $access->authorize($user, 'publishing', $write);
    }

    private function accountsQuery(int $userId)
    {
        return SocialAccount::where('user_id', $userId)->active()->usableFor('posting')
            ->where(fn ($q) => $q->whereNull('social_connection_id')->orWhereHas('connection', fn ($c) => $c->whereIn('status', SocialConnection::USABLE)));
    }

    private function readiness(SocialAccount $account): ?string
    {
        if (! config('mcp-publishing.platforms.'.$account->platform)) {
            return $account->platform === 'whatsapp'
                ? 'WhatsApp requires a separate template and recipient messaging flow.'
                : 'No post publisher is registered for this platform.';
        }
        if ($account->platform === 'pinterest' && ! data_get($account->metadata, 'settings.board_id')) {
            return 'Configure the default Pinterest board on the website first.';
        }
        if ($account->platform === 'google' && ! data_get($account->metadata, 'parent_account_id')) {
            return 'Reconnect the Google Business Profile location to its parent account.';
        }

        return null;
    }

    public function accounts(Request $request, PackageAccessService $access)
    {
        $this->access($request, $access);
        $query = $request->validate([
            'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:50',
            'platform' => ['sometimes', 'string', 'max:30', 'regex:/^[a-z_]+$/'],
        ]);
        $accounts = $this->accountsQuery($request->user()->id)
            ->when(isset($query['platform']), fn ($q) => $q->where('platform', $query['platform']))
            ->orderBy('id')->paginate($query['per_page'] ?? 20, ['id', 'platform', 'name', 'username', 'platform_account_id', 'metadata'], 'page', $query['page'] ?? 1);
        $data = $accounts->getCollection()->map(function ($account) {
            $rules = config('mcp-publishing.platforms.'.$account->platform);

            return $account->only(['id', 'platform', 'name', 'username', 'platform_account_id']) + [
                'publishing' => [
                    'supported' => $rules !== null, 'ready' => $this->readiness($account) === null,
                    'reason' => $this->readiness($account), 'requirements' => $rules,
                    'text_limits' => $rules ? PlatformTextLimits::forAccount($account, false) : null,
                ],
            ];
        })->values();

        return response()->json(['data' => $data, 'meta' => [
            'current_page' => $accounts->currentPage(), 'last_page' => $accounts->lastPage(),
            'per_page' => $accounts->perPage(), 'total' => $accounts->total(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, PackageAccessService $access)
    {
        $this->access($request, $access, true);
        $input = $request->validate([
            'social_account_ids' => 'required|array|list|min:1|max:20', 'social_account_ids.*' => 'required|integer|min:1|distinct',
            'content' => 'sometimes|nullable|string|max:63206', 'title' => 'sometimes|nullable|string|max:100',
            'media_asset_ids' => 'sometimes|array|list|max:35', 'media_asset_ids.*' => 'required|integer|min:1|distinct',
            'idempotency_key' => 'required|uuid',
        ]);
        $ids = array_map('intval', $input['social_account_ids']);
        sort($ids);
        $payload = ['kind' => 'multi_platform_post', 'social_account_ids' => $ids, 'content' => $input['content'] ?? '',
            'title' => $input['title'] ?? '', 'media_asset_ids' => array_map('intval', $input['media_asset_ids'] ?? [])];
        if (trim($payload['content']) === '' && ! $payload['media_asset_ids']) {
            throw ValidationException::withMessages(['content' => 'Provide post text or gallery media.']);
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $user = $request->user();
        [$posts, $replayed] = DB::transaction(function () use ($user, $input, $payload, $hash, $access) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $key = strtolower($input['idempotency_key']);
            $saved = DB::table('mcp_post_requests')->where(['user_id' => $user->id, 'idempotency_key' => $key])->first();
            if ($saved) {
                abort_unless(hash_equals($saved->payload_hash, $hash), 409, 'This key was used for a different post request.');
                $posts = Post::where('user_id', $user->id)->where('group_id', $key)->orderBy('social_account_id')->get();
                abort_unless($posts->count() === count($payload['social_account_ids']) && $posts->contains('id', $saved->post_id), 409, 'An original post was deleted.');

                return [$posts, true];
            }
            $accounts = $this->accountsQuery($user->id)->whereIn('id', $payload['social_account_ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($accounts->count() === count($payload['social_account_ids']), 404, 'A selected posting account is unavailable.');
            $assets = collect();
            if ($payload['media_asset_ids']) {
                $access->authorize($user, 'media', false);
                $found = MediaAsset::ownedBy($user->id)->whereIn('id', $payload['media_asset_ids'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                abort_unless($found->count() === count($payload['media_asset_ids']), 404, 'A selected gallery asset is unavailable.');
                $assets = collect($payload['media_asset_ids'])->map(fn ($id) => $found[$id]);
            }
            foreach ($accounts as $account) {
                $this->validatePost($account, $assets, $payload);
            }
            $posts = collect();
            foreach ($accounts as $account) {
                $post = Post::create([
                    'user_id' => $user->id, 'social_account_id' => $account->id, 'platform' => $account->platform,
                    'group_id' => $key, 'title' => $payload['title'], 'content' => $payload['content'],
                    'visibility' => 'public', 'status' => 'pending', 'schedule_mode' => 0, 'schedule_at' => null,
                    'expiry_mode' => 0, 'expiry_at' => null,
                ]);
                foreach ($assets as $index => $asset) {
                    PostMedia::create([
                        'post_id' => $post->id, 'media_asset_id' => $asset->id, 'user_id' => $user->id,
                        'platform' => $account->platform, 'social_account_id' => $account->id, 'sort_order' => $index,
                        'media_url' => $asset->url, 'media_type' => $asset->type, 'file_name' => basename($asset->path),
                        'file_size' => $asset->size, 'width' => $asset->width, 'height' => $asset->height,
                        'duration_seconds' => $asset->duration, 'thumbnail_url' => $asset->thumbnail_url, 'alt_text' => $asset->original_name,
                    ]);
                }
                $posts->push($post);
            }
            DB::table('mcp_post_requests')->insert([
                'user_id' => $user->id, 'idempotency_key' => $key, 'payload_hash' => $hash,
                'post_id' => $posts->first()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return [$posts, false];
        });

        return response()->json(['posts' => $posts->map(fn ($post) => $this->summary($post))->values(), 'replayed' => $replayed], $replayed ? 200 : 202)->header('Cache-Control', 'no-store');
    }

    private function validatePost(SocialAccount $account, $assets, array $payload): void
    {
        $fail = function (string $message) use ($account): never {
            throw ValidationException::withMessages(['accounts.'.$account->id => $account->platform.': '.$message]);
        };
        if ($reason = $this->readiness($account)) {
            $fail($reason);
        }
        $rules = config('mcp-publishing.platforms.'.$account->platform);
        if ($rules['media_required'] && $assets->isEmpty()) {
            $fail('This platform requires gallery media.');
        }
        if ($rules['text_required'] && trim($payload['content']) === '') {
            $fail('This platform requires text.');
        }
        if ($rules['title_required'] && trim($payload['title']) === '') {
            $fail('This platform requires a title.');
        }
        $limits = PlatformTextLimits::forAccount($account, false);
        if (isset($limits['title']) && mb_strlen($payload['title']) > $limits['title']) {
            $fail('Title exceeds the platform limit.');
        }
        if ($problems = PlatformTextLimits::problems($limits, $payload['content'], $payload['title'])) {
            $fail(implode(' ', $problems));
        }
        $images = $assets->where('type', 'image')->count();
        $videos = $assets->where('type', 'video')->count();
        if ($images + $videos !== $assets->count() || ($images && $videos)
            || $images > $rules['max_images'] || $videos > $rules['max_videos']) {
            $fail('Unsupported attachment count or mixed image/video selection. Check account requirements.');
        }
        foreach ($assets as $asset) {
            $extensions = $rules[$asset->type.'_extensions'];
            if (! in_array(strtolower($asset->extension), $extensions, true)) {
                $fail('The selected media format is not supported by this publishing adapter.');
            }
            if (isset(MediaCompatibility::PLATFORMS[$account->platform]) && ! in_array($account->platform, $asset->compatible_platforms ?? [], true)) {
                $fail('The selected gallery media is incompatible with this platform.');
            }
        }
    }

    public function show(Request $request, int $post, PackageAccessService $access)
    {
        $this->access($request, $access);

        return response()->json(['post' => $this->summary(Post::where('user_id', $request->user()->id)->findOrFail($post))])->header('Cache-Control', 'no-store');
    }

    private function summary(Post $post): array
    {
        $processing = $post->platform === 'tiktok' && $post->status === 'completed'
            && (! $post->post_url || str_contains((string) $post->post_id, 'pub_url'));
        $published = $post->status === 'completed' && (bool) $post->post_id && ! $processing;

        return $post->only(['id', 'platform', 'social_account_id', 'group_id', 'title', 'content', 'status', 'post_id', 'post_url', 'created_at']) + [
            'published' => $published, 'provider_processing' => $processing,
            'status_message' => $processing ? 'Provider accepted the upload; public delivery is processing or visibility is restricted. Check the platform.' : match ($post->status) {
                'pending', 'scheduled' => 'Queued for background publishing. Not yet published.',
                'publishing' => 'Publishing in progress. Do not submit a replacement.',
                'completed' => $published ? 'Publishing completed.' : 'Provider delivery is not yet confirmed.',
                'failed' => 'Publishing failed. Check the website and platform before retrying.',
                default => 'Check the website for the current post state.',
            },
        ];
    }
}
