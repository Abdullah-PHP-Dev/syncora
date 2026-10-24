<?php

namespace App\Services\PostServices;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Services\Connections\ConnectionRecorder;
use App\Services\Connections\Drivers\TikTokDriver;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comments on TikTok videos - TikTok API for Business, Organic "Accounts
 * API" (business-api.tiktok.com/portal/docs/get-comments-on-an-owned-video/v1.3).
 *
 * The Login Kit / Content Posting app that publishes posts has no comment
 * API and no comment webhook, so comments need a second authorization:
 * the "TikTok account holder authorization" of the TikTok for Business
 * app (ads.tiktok.client_id/secret), exchanged at tt_user/oauth2/token/.
 * Its token and open_id (the "business_id" every call takes) are kept on
 * a separate SocialAccount row (account_type tiktok_business), linked to
 * the posting account through metadata.posting_account_id.
 *
 * Comments are pulled (tiktok:sync-comments, hourly): comment/list +
 * comment/reply/list, upserted by comment_id. Replies and new comments
 * go out through comment/reply/create and comment/create.
 */
class TiktokCommentsService
{
    public const ACCOUNT_TYPE = 'tiktok_business';

    /** Up to 30 per page (TikTok's max); pages are capped per run. */
    private const PAGE_SIZE = 30;

    private const MAX_PAGES = 10;

    /**
     * Fixed, not ads.tiktok.base_url: that setting serves the Marketing API
     * and may point at sandbox-ads.tiktok.com, which has no tt_user/ or
     * business/ endpoints (plain 404 - seen live on the token exchange).
     */
    private const BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    private function base(): string
    {
        return self::BASE;
    }

    /** https://socialeaz.com/messaging/auth/tiktok/callback - see scopes(). */
    public function callbackUrl(): string
    {
        return oauthCallbackUrl('admin.post-accounts.tiktok.comments.portal-callback');
    }

    /**
     * Callback and scopes are copied from the "TikTok account holder
     * authorization URL" the developer portal generates for the app (My
     * Apps > Basic Information); posts.tiktok.comments_scopes overrides the
     * default when the app was granted a different set.
     */
    public function scopes(): string
    {
        return (string) (adminSetting('posts.tiktok.comments_scopes') ?: 'video.list,video.insights,biz.ads.recommend,biz.brand.insights,comment.list,user.info.basic,user.info.username,user.info.stats,user.info.profile,user.account.type,user.insights,comment.list.manage,video.publish,video.upload,biz.spark.auth,discovery.search.words,biz.creator.info,biz.creator.insights,tto.campaign.link');
    }

    // ------------------------------------------------------------------
    // Connect
    // ------------------------------------------------------------------

    public function redirect()
    {
        $state = Str::random(40);
        session(['tiktok_comments_state' => $state]);

        // No trailing slash and no PKCE: this is the account-holder flow,
        // not Login Kit (see TiktokMessagingService's docblock).
        return redirect()->away('https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
            'client_key' => adminSetting('ads.tiktok.client_id'),
            'scope' => $this->scopes(),
            'response_type' => 'code',
            'redirect_uri' => $this->callbackUrl(),
            'state' => $state,
        ]));
    }

    /** @return array{success: bool, message: string, account?: SocialAccount} */
    public function handleCallback(Request $request): array
    {
        $expected = session()->pull('tiktok_comments_state');

        if ($request->query('error')) {
            return ['success' => false, 'message' => 'TikTok declined: ' . ($request->query('error_description') ?: $request->query('error'))];
        }
        if (! $request->query('code') || ! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return ['success' => false, 'message' => 'TikTok comments connection failed or was cancelled - please try again.'];
        }

        $response = Http::acceptJson()->post($this->base() . 'tt_user/oauth2/token/', [
            'client_id' => (string) adminSetting('ads.tiktok.client_id'),
            'client_secret' => (string) adminSetting('ads.tiktok.client_secret'),
            'grant_type' => 'authorization_code',
            'auth_code' => (string) $request->query('code'),
            'redirect_uri' => $this->callbackUrl(),
        ]);
        $token = $response->json('data') ?? [];

        if (! $this->ok($response) || empty($token['access_token']) || empty($token['open_id'])) {
            Log::warning('TikTok comments token exchange failed.', ['url' => $this->base() . 'tt_user/oauth2/token/', 'status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            return ['success' => false, 'message' => 'TikTok did not issue a token: ' . ($response->json('message') ?: 'HTTP ' . $response->status())];
        }

        $profile = $this->profile($token['access_token'], $token['open_id']);
        $posting = $this->matchPostingAccount((int) Auth::id(), $profile['username'] ?? null);

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'tiktok', 'platform_account_id' => 'biz_' . $token['open_id'], 'user_id' => Auth::id()],
            [
                'name' => $profile['display_name'] ?? $profile['username'] ?? 'TikTok account',
                'username' => $profile['username'] ?? null,
                'avatar_url' => $profile['profile_image'] ?? null,
                'account_type' => self::ACCOUNT_TYPE,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
                'is_token_valid' => true,
                'has_posting_permission' => false,
                'metadata' => [
                    'business_id' => $token['open_id'],
                    'posting_account_id' => $posting?->id,
                    'scope' => $token['scope'] ?? null,
                    'refresh_expires_at' => isset($token['refresh_token_expires_in']) ? now()->addSeconds((int) $token['refresh_token_expires_in'])->toIso8601String() : null,
                ],
            ]
        );

        ConnectionRecorder::record((int) Auth::id(), 'tiktok', TikTokDriver::COMMENTS, $token['open_id'], [
            'provider_app' => 'ads.tiktok',
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'refresh_expires_at' => isset($token['refresh_token_expires_in']) ? now()->addSeconds((int) $token['refresh_token_expires_in']) : null,
            'granted_scopes' => preg_split('/[\s,]+/', (string) ($token['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
        ], [$account->id]);

        $message = 'TikTok comments connected' . ($posting ? " for @{$posting->username}." : '.');
        if (! $posting) {
            $message .= ' Connect the TikTok account for posting too, so comments can be matched to your posts.';
        }

        return ['success' => true, 'message' => $message, 'account' => $account];
    }

    /** The posting (Login Kit) account the comments belong to: same @username, else the user's only one. */
    private function matchPostingAccount(int $userId, ?string $username): ?SocialAccount
    {
        $accounts = SocialAccount::where('user_id', $userId)->where('platform', 'tiktok')->where('has_posting_permission', true)->get();

        return ($username ? $accounts->first(fn ($a) => strcasecmp((string) $a->username, $username) === 0) : null)
            ?? ($accounts->count() === 1 ? $accounts->first() : null);
    }

    /** business/get - display name, @username and avatar. */
    public function profile(string $accessToken, string $businessId): array
    {
        $response = Http::withHeaders(['Access-Token' => $accessToken])->acceptJson()
            ->get($this->base() . 'business/get/', ['business_id' => $businessId, 'fields' => json_encode(['display_name', 'username', 'profile_image'])]);

        return $this->ok($response) ? array_filter((array) $response->json('data')) : [];
    }

    // ------------------------------------------------------------------
    // Comments
    // ------------------------------------------------------------------

    /** The comments connection for a post's TikTok account, if there is one (and not paused in the Hub). */
    public function accountFor(Post $post): ?SocialAccount
    {
        $candidates = SocialAccount::where('user_id', $post->user_id)->where('platform', 'tiktok')
            ->where('account_type', self::ACCOUNT_TYPE)->where('is_token_valid', true)->enabledFor('engagement')->get();

        return $candidates->first(fn ($a) => (int) ($a->metadata['posting_account_id'] ?? 0) === (int) $post->social_account_id)
            ?? ($candidates->count() === 1 && empty($candidates->first()->metadata['posting_account_id']) ? $candidates->first() : null);
    }

    /**
     * Pull every comment and reply on the post's video into post_comments.
     *
     * @return array{success: bool, synced?: int, message?: string}
     */
    public function syncComments(Post $post): array
    {
        if (! ctype_digit((string) $post->post_id)) {
            return ['success' => false, 'message' => 'Not on TikTok yet (no TikTok post id).'];
        }
        $account = $this->accountFor($post);
        if (! $account) {
            return ['success' => false, 'message' => 'Connect "TikTok comments" in Connections to read comments.'];
        }
        if (! $this->refreshToken($account)) {
            return ['success' => false, 'message' => 'TikTok comments connection expired - reconnect it in Connections.'];
        }

        $synced = 0;
        $cursor = 0;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->get($account, 'business/comment/list/', [
                'video_id' => $post->post_id, 'status' => 'ALL', 'sort_field' => 'create_time', 'sort_order' => 'desc',
                'cursor' => $cursor, 'max_count' => self::PAGE_SIZE,
            ]);
            if (! $this->ok($response)) {
                return ['success' => false, 'message' => $this->error($response)];
            }

            foreach ($response->json('data.comments') ?? [] as $item) {
                $comment = $this->save($post, $item);
                $synced++;

                if ((int) ($item['replies'] ?? 0) > 0 && empty($item['parent_comment_id'])) {
                    $synced += $this->syncReplies($account, $post, $comment);
                }
            }

            if (! $response->json('data.has_more')) {
                break;
            }
            $cursor = (int) $response->json('data.cursor');
        }

        $post->forceFill(['platform_metadata' => array_merge($post->platform_metadata ?? [], ['comments_synced_at' => now()->toIso8601String()])])->save();

        return ['success' => true, 'synced' => $synced];
    }

    private function syncReplies(SocialAccount $account, Post $post, PostComment $parent): int
    {
        $count = 0;
        $cursor = 0;
        for ($page = 0; $page < 3; $page++) {
            $response = $this->get($account, 'business/comment/reply/list/', [
                'video_id' => $post->post_id, 'comment_id' => $parent->comment_id, 'status' => 'ALL',
                'sort_field' => 'create_time', 'sort_order' => 'asc', 'cursor' => $cursor, 'max_count' => self::PAGE_SIZE,
            ]);
            if (! $this->ok($response)) {
                break;
            }
            foreach ($response->json('data.comments') ?? [] as $item) {
                $this->save($post, $item + ['parent_comment_id' => $parent->comment_id]);
                $count++;
            }
            if (! $response->json('data.has_more')) {
                break;
            }
            $cursor = (int) $response->json('data.cursor');
        }

        return $count;
    }

    /** Upsert one TikTok comment/reply object. */
    private function save(Post $post, array $item): PostComment
    {
        $parent = ! empty($item['parent_comment_id'])
            ? PostComment::where('platform', 'tiktok')->where('comment_id', (string) $item['parent_comment_id'])->first()
            : null;

        return PostComment::updateOrCreate(
            ['platform' => 'tiktok', 'comment_id' => (string) $item['comment_id']],
            array_filter([
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'social_account_id' => $post->social_account_id,
                'parent_comment_id' => $parent?->id,
                'is_reply' => (bool) $parent,
                'content' => (string) ($item['text'] ?? ''),
                'user_name' => ($item['display_name'] ?? null) ?: ($item['username'] ?? 'TikTok user'),
                'user_avatar_url' => $item['profile_image'] ?? null,
                'likes' => (int) ($item['likes'] ?? 0),
                // owner = written by the account that posted the video.
                'sender_type' => ! empty($item['owner']) ? 'support' : 'customer',
                'status' => ($item['status'] ?? 'PUBLIC') === 'HIDDEN' ? 'hidden' : 'approved',
                'posted_at' => isset($item['create_time']) ? Carbon::createFromTimestamp((int) $item['create_time']) : null,
            ], fn ($v) => $v !== null)
        );
    }

    /**
     * Reply to a comment ($target = PostComment) or comment on the video
     * ($target = Post) as the account, and save it.
     *
     * @return array{success: bool, message: string, data?: PostComment}
     */
    public function publish(string $text, Post|PostComment $target): array
    {
        $post = $target instanceof Post ? $target : $target->post;
        $account = $post ? $this->accountFor($post) : null;

        if (! $post || ! ctype_digit((string) $post->post_id)) {
            return ['success' => false, 'message' => "This post isn't on TikTok yet."];
        }
        if (! $account) {
            return ['success' => false, 'message' => 'Connect "TikTok comments" in Connections to comment on TikTok.'];
        }
        if (! $this->refreshToken($account)) {
            return ['success' => false, 'message' => 'TikTok comments connection expired - reconnect it in Connections.'];
        }

        $isReply = $target instanceof PostComment;
        $response = Http::withHeaders(['Access-Token' => $account->access_token])->acceptJson()
            ->post($this->base() . ($isReply ? 'business/comment/reply/create/' : 'business/comment/create/'), array_filter([
                'business_id' => $account->metadata['business_id'] ?? null,
                'video_id' => (string) $post->post_id,
                'comment_id' => $isReply ? (string) $target->comment_id : null,
                'text' => $text,
            ]));

        if (! $this->ok($response)) {
            return ['success' => false, 'message' => $this->error($response)];
        }

        $comment = $this->save($post, [
            'comment_id' => (string) ($response->json('data.comment_id') ?? 'tt_' . Str::random(12)),
            'parent_comment_id' => $isReply ? $target->comment_id : null,
            'text' => $text,
            'owner' => true,
            'display_name' => $post->socialAccount?->name ?? $account->name,
            'profile_image' => $post->socialAccount?->avatar_url ?? $account->avatar_url,
            'create_time' => time(),
        ]);

        return ['success' => true, 'message' => $isReply ? 'Reply published.' : 'Comment published.', 'data' => $comment];
    }

    // ------------------------------------------------------------------
    // Token + HTTP
    // ------------------------------------------------------------------

    /** tt_user/oauth2/refresh_token/ when the token is about to expire. False = reconnect needed. */
    public function refreshToken(SocialAccount $account): bool
    {
        if ($account->access_token && $account->expires_at && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))) {
            return true;
        }

        $response = Http::acceptJson()->post($this->base() . 'tt_user/oauth2/refresh_token/', [
            'client_id' => (string) adminSetting('ads.tiktok.client_id'),
            'client_secret' => (string) adminSetting('ads.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $account->refresh_token,
        ]);
        $token = $response->json('data') ?? [];

        if (! $this->ok($response) || empty($token['access_token'])) {
            $account->update(['is_token_valid' => false]);
            Log::warning('TikTok comments token refresh failed.', ['account_id' => $account->id, 'body' => $response->json()]);

            return false;
        }

        $account->update([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'is_token_valid' => true,
        ]);

        return true;
    }

    private function get(SocialAccount $account, string $path, array $params): Response
    {
        return Http::withHeaders(['Access-Token' => $account->access_token])->acceptJson()
            ->get($this->base() . $path, ['business_id' => $account->metadata['business_id'] ?? null] + $params);
    }

    /** Business API answers HTTP 200 with code != 0 on errors. */
    private function ok(Response $response): bool
    {
        return $response->successful() && (int) $response->json('code', -1) === 0;
    }

    private function error(Response $response): string
    {
        $message = $response->json('message') ?: 'HTTP ' . $response->status();

        return str_contains(strtolower($message), 'scope') || str_contains(strtolower($message), 'permission')
            ? "TikTok refused ({$message}) - the TikTok for Business app needs the comment permissions approved, then reconnect \"TikTok comments\"."
            : "TikTok: {$message}";
    }
}
