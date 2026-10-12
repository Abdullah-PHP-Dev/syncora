<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\PostServices\TiktokPostService;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * TikTok Content Posting, end to end with TikTok faked: connect (redirect
 * -> callback -> account details), publish a video / photos, token
 * refresh, and the app's webhook (signature + post.publish.* events).
 */
class TiktokContentPostingTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'open.tiktokapis.com/v2';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        (require database_path('migrations/2026_09_05_223054_create_webhook_logs_table.php'))->up();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('group_id')->nullable();
            $t->string('post_id')->nullable();
            $t->string('title')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->string('post_url')->nullable();
            $t->string('visibility')->nullable();
            $t->json('platform_metadata')->nullable();
            $t->integer('likes')->default(0);
            $t->integer('comments')->default(0);
            $t->integer('shares')->default(0);
            $t->integer('views')->default(0);
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('file_name')->nullable();
            $t->string('media_type')->nullable();
            $t->text('media_url')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.tiktok.client_id', 'tt-client');
        Settings::set('posts.tiktok.client_secret', 'tt-secret');
        $this->user = User::create(['name' => 'Test User', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        Bus::fake();
    }

    private function account(array $overrides = []): SocialAccount
    {
        return SocialAccount::create($overrides + [
            'user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'OPEN1', 'name' => 'Bakery',
            'username' => 'bakery', 'access_token' => 'tt-access', 'refresh_token' => 'tt-refresh',
            'expires_at' => now()->addDay(), 'is_token_valid' => true, 'has_posting_permission' => true,
        ]);
    }

    private function makePost(SocialAccount $account, array $media, array $overrides = []): Post
    {
        $post = Post::forceCreate($overrides + [
            'platform' => 'tiktok', 'user_id' => $this->user->id, 'social_account_id' => $account->id,
            'title' => 'Test User', 'content' => 'Fresh sourdough every morning. #bakery #bread', 'status' => 'pending',
        ]);
        foreach ($media as [$type, $url]) {
            DB::table('post_media')->insert(['post_id' => $post->id, 'media_type' => $type, 'media_url' => $url]);
        }

        return $post->fresh(['media', 'socialAccount']);
    }

    private function fakePublish(array $creator = ['privacy_level_options' => ['SELF_ONLY', 'PUBLIC_TO_EVERYONE']], array $status = ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7350000000000000001]]): void
    {
        Http::fake([
            self::API . '/post/publish/creator_info/query/' => Http::response(['data' => $creator, 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'v_pub_url~1'], 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/content/init/' => Http::response(['data' => ['publish_id' => 'p_pub_url~1'], 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/status/fetch/' => Http::response(['data' => $status, 'error' => ['code' => 'ok']]),
        ]);
    }

    // ---- connect ---------------------------------------------------------

    public function test_redirect_sends_the_login_kit_scopes_and_pkce(): void
    {
        $url = app(SocialAuthService::class)->redirect('tiktok')->getTargetUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/', $url);
        $this->assertSame('tt-client', $q['client_key']);
        $this->assertSame('user.info.basic,video.upload,video.publish,user.info.profile,user.info.stats,video.list', $q['scope']);
        $this->assertSame(hash('sha256', session('social_tiktok_code_verifier')), $q['code_challenge']);
        $this->assertSame(session('social_oauth_state_tiktok'), $q['state']);
        $this->assertStringEndsWith('/post-accounts/tiktok/callback', $q['redirect_uri']);
    }

    public function test_callback_saves_the_account_details_and_connection(): void
    {
        Http::fake([
            self::API . '/oauth/token/' => Http::response(['open_id' => 'OPEN1', 'access_token' => 'act.1', 'refresh_token' => 'rft.1', 'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'scope' => 'user.info.basic,video.upload,video.publish,user.info.profile,user.info.stats']),
            self::API . '/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'OPEN1', 'display_name' => 'The Bakery', 'username' => 'bakery', 'avatar_url' => 'https://p16.tiktokcdn.com/a.jpg', 'follower_count' => 1200, 'following_count' => 10, 'likes_count' => 5400, 'video_count' => 33, 'bio_description' => 'Bread', 'profile_deep_link' => 'https://vm.tiktok.com/x']]]),
            self::API . '/video/list/*' => Http::response(['data' => ['videos' => [$this->video('7001'), $this->video('7002', ['view_count' => 90])], 'has_more' => true], 'error' => ['code' => 'ok']]),
        ]);
        $service = app(SocialAuthService::class);
        $service->redirect('tiktok');

        $response = $service->callback('tiktok', 'auth-code', session('social_oauth_state_tiktok'));

        $this->assertSame('TikTok account connected. Imported your latest 2 video(s).', session('success'), (string) session('error'));
        $this->assertSame(['7001', '7002'], Post::where('platform', 'tiktok')->orderBy('post_id')->pluck('post_id')->all());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/video/list/?fields=') && $r['max_count'] === 5);
        $account = SocialAccount::where('platform', 'tiktok')->sole();
        $this->assertSame(['OPEN1', 'The Bakery', 'bakery', 1200, 5400, 33, true], [$account->platform_account_id, $account->name, $account->username, (int) $account->followers_count, (int) $account->likes_count, (int) $account->media_count, (bool) $account->has_posting_permission]);
        $this->assertSame(['act.1', 'rft.1'], [$account->access_token, $account->refresh_token]);
        $this->assertSame($account->social_connection_id, SocialConnection::where('platform', 'tiktok')->sole()->id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/oauth/token/') && $r['code_verifier'] !== null && $r['code'] === 'auth-code');
        $this->assertTrue($response->isRedirect());
    }

    public function test_a_tampered_state_is_rejected(): void
    {
        Http::fake();
        $service = app(SocialAuthService::class);
        $service->redirect('tiktok');

        $service->callback('tiktok', 'auth-code', 'forged-state');

        $this->assertStringContainsString('connection failed', session('error'));
        Http::assertNothingSent();
        $this->assertSame(0, SocialAccount::count());
    }

    // ---- create post -----------------------------------------------------

    public function test_a_video_is_published_with_the_caption_and_its_public_link(): void
    {
        $this->fakePublish();
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/uploads/quick/media/v.mp4']]);

        $result = app(TiktokPostService::class)->publishPost($post);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/post/publish/video/init/')
            && $r['post_info']['title'] === 'Fresh sourdough every morning. #bakery #bread' // the caption, not the seller's name
            && $r['post_info']['privacy_level'] === 'SELF_ONLY'
            && $r['source_info'] === ['source' => 'PULL_FROM_URL', 'video_url' => 'https://cdn.socialeaz.com/uploads/quick/media/v.mp4']);
        $post->refresh();
        $this->assertSame(['completed', '7350000000000000001', 'https://www.tiktok.com/@bakery/video/7350000000000000001', 'v_pub_url~1'], [$post->status, $post->post_id, $post->post_url, $post->platform_metadata['publish_id']]);
    }

    public function test_photos_use_a_short_title_and_the_full_caption(): void
    {
        $this->fakePublish();
        $post = $this->makePost($this->account(), [['image', 'https://cdn.socialeaz.com/a.jpg'], ['image', 'https://cdn.socialeaz.com/b.webp']]);

        app(TiktokPostService::class)->publishPost($post);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/post/publish/content/init/')
            && $r['post_info']['title'] === 'Fresh sourdough every morning.'
            && $r['post_info']['description'] === 'Fresh sourdough every morning. #bakery #bread'
            && $r['source_info']['photo_images'] === ['https://cdn.socialeaz.com/a.jpg', 'https://cdn.socialeaz.com/b.webp']
            && $r['media_type'] === 'PHOTO');
        $this->assertSame('https://www.tiktok.com/@bakery/photo/7350000000000000001', $post->fresh()->post_url);
    }

    public function test_png_photos_are_stopped_before_tiktok(): void
    {
        $this->fakePublish();
        $post = $this->makePost($this->account(), [['image', 'https://cdn.socialeaz.com/a.png']]);

        $result = app(TiktokPostService::class)->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('JPG or WebP', $post->fresh()->error_message);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/content/init/'));
    }

    public function test_privacy_follows_the_setting_when_tiktok_allows_it(): void
    {
        Settings::set('posts.tiktok.privacy_level', 'PUBLIC_TO_EVERYONE');
        $this->fakePublish();
        app(TiktokPostService::class)->publishPost($this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']]));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/video/init/') && $r['post_info']['privacy_level'] === 'PUBLIC_TO_EVERYONE');
    }

    public function test_privacy_falls_back_to_self_only_when_tiktok_does_not_allow_it(): void
    {
        Settings::set('posts.tiktok.privacy_level', 'PUBLIC_TO_EVERYONE');
        // An unaudited app/creator only offers SELF_ONLY.
        $this->fakePublish(['privacy_level_options' => ['SELF_ONLY']]);
        app(TiktokPostService::class)->publishPost($this->makePost($this->account(['platform_account_id' => 'OPEN2']), [['video', 'https://cdn.socialeaz.com/v.mp4']]));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/video/init/') && $r['post_info']['privacy_level'] === 'SELF_ONLY');
    }

    public function test_a_still_processing_video_is_handed_to_the_background_check(): void
    {
        $this->fakePublish(status: ['status' => 'PROCESSING_DOWNLOAD']);
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']]);

        app(TiktokPostService::class)->publishPost($post);

        $this->assertSame(['completed', 'v_pub_url~1', null], [$post->fresh()->status, $post->fresh()->post_id, $post->fresh()->post_url]);
        Bus::assertDispatched(\App\Jobs\Posts\ResolveTiktokPublishStatus::class);
    }

    public function test_an_unverified_media_domain_gets_a_clear_message(): void
    {
        Http::fake([
            self::API . '/post/publish/creator_info/query/' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/video/init/' => Http::response(['error' => ['code' => 'url_ownership_unverified', 'message' => 'Please review our URL ownership verification rules.']], 403),
        ]);
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']]);

        app(TiktokPostService::class)->publishPost($post);

        $this->assertSame('failed', $post->fresh()->status);
        $this->assertStringContainsString('URL properties in the TikTok developer portal', $post->fresh()->error_message);
    }

    public function test_an_expired_connection_asks_for_a_reconnect(): void
    {
        Http::fake([self::API . '/oauth/token/' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid or expired.'])]); // HTTP 200
        $account = $this->account(['expires_at' => now()->subHour()]);
        $post = $this->makePost($account, [['video', 'https://cdn.socialeaz.com/v.mp4']]);

        $result = app(TiktokPostService::class)->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $post->fresh()->status);
        $this->assertStringContainsString('reconnect the TikTok account', $post->fresh()->error_message);
        $this->assertFalse((bool) $account->fresh()->is_token_valid);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/post/publish/'));
    }

    public function test_an_expired_token_is_refreshed_before_posting(): void
    {
        Http::fake([
            self::API . '/oauth/token/' => Http::response(['access_token' => 'act.new', 'refresh_token' => 'rft.new', 'expires_in' => 86400]),
            self::API . '/post/publish/creator_info/query/' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'v1'], 'error' => ['code' => 'ok']]),
            self::API . '/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [42]], 'error' => ['code' => 'ok']]),
        ]);
        $account = $this->account(['expires_at' => now()->subHour()]);

        app(TiktokPostService::class)->publishPost($this->makePost($account, [['video', 'https://cdn.socialeaz.com/v.mp4']]));

        $this->assertSame('act.new', $account->fresh()->access_token);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/video/init/') && $r->hasHeader('Authorization', 'Bearer act.new'));
    }

    // ---- webhook ---------------------------------------------------------

    private function deliver(string $event, array $content, ?string $secret = 'tt-secret')
    {
        $body = json_encode(['client_key' => 'tt-client', 'event' => $event, 'create_time' => time(), 'user_openid' => 'OPEN1', 'content' => json_encode($content)]);
        $t = (string) time();
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_TIKTOK_SIGNATURE'] = "t={$t},s=" . hash_hmac('sha256', "{$t}.{$body}", $secret);
        }

        return $this->call('POST', '/api/posts/tiktok', [], [], [], $headers, $body);
    }

    public function test_webhook_rejects_bad_signatures_and_answers_the_reachability_check(): void
    {
        $this->get('/api/posts/tiktok')->assertOk();
        $this->deliver('post.publish.failed', ['publish_id' => 'x'], 'wrong-secret')->assertForbidden();
        $this->deliver('post.publish.failed', ['publish_id' => 'x'], null)->assertForbidden();
        $this->assertSame(2, DB::table('webhook_logs')->where('signature_valid', false)->count());
    }

    public function test_publicly_available_fills_in_the_real_post_and_link(): void
    {
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~9', 'platform_metadata' => ['publish_id' => 'v_pub_url~9']]);

        $this->deliver('post.publish.publicly_available', ['publish_id' => 'v_pub_url~9', 'post_id' => '7350000000000000009', 'publish_type' => 'DIRECT_PUBLISH'])->assertOk();

        $post->refresh();
        $this->assertSame(['7350000000000000009', 'https://www.tiktok.com/@bakery/video/7350000000000000009', null], [$post->post_id, $post->post_url, $post->error_message]);
        $log = DB::table('webhook_logs')->latest('id')->first();
        $this->assertSame(['post.publish.publicly_available', 1, 1], [$log->event_type, (int) $log->signature_valid, (int) $log->processed]);
    }

    public function test_failed_and_no_longer_public_events_update_the_post(): void
    {
        $failed = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~A', 'platform_metadata' => ['publish_id' => 'v_pub_url~A']]);
        // Already resolved to its real id - still found through the saved publish_id.
        $public = $this->makePost($this->account(['platform_account_id' => 'OPEN2']), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => '735', 'platform_metadata' => ['publish_id' => 'v_pub_url~B']]);

        $this->deliver('post.publish.failed', ['publish_id' => 'v_pub_url~A', 'reason' => 'file_format_check_failed', 'publish_type' => 'DIRECT_PUBLISH'])->assertOk();
        $this->deliver('post.publish.no_longer_publicaly_available', ['publish_id' => 'v_pub_url~B', 'post_id' => '735'])->assertOk();

        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertStringContainsString('file_format_check_failed', $failed->fresh()->error_message);
        $this->assertStringContainsString('no longer public', $public->fresh()->error_message);
    }

    public function test_authorization_removed_disables_the_account(): void
    {
        $account = $this->account();

        $this->deliver('authorization.removed', ['reason' => 1])->assertOk();

        $this->assertSame([false, false], [(bool) $account->fresh()->is_token_valid, (bool) $account->fresh()->has_posting_permission]);
    }

    // ---- existing videos and counts ----------------------------------------

    private function video(string $id, array $overrides = []): array
    {
        return $overrides + [
            'id' => $id, 'title' => '', 'video_description' => "Morning bake #{$id} #bread", 'create_time' => 1790000000,
            'cover_image_url' => "https://p16.tiktokcdn.com/cover-{$id}.jpg", 'share_url' => "https://www.tiktok.com/@bakery/video/{$id}",
            'embed_link' => "https://www.tiktok.com/player/v1/{$id}", 'duration' => 15,
            'like_count' => 120, 'comment_count' => 8, 'share_count' => 5, 'view_count' => 3400,
        ];
    }

    public function test_import_creates_posts_with_counts_and_covers(): void
    {
        Http::fake([self::API . '/video/list/*' => Http::response(['data' => ['videos' => [$this->video('7001')]], 'error' => ['code' => 'ok']])]);
        $account = $this->account();

        $this->assertSame(1, app(TiktokPostService::class)->backfillRecentPosts($account, 5));

        $post = Post::where('post_id', '7001')->sole();
        $this->assertSame(
            ['completed', $account->id, 'Morning bake #7001 #bread', 'https://www.tiktok.com/@bakery/video/7001', 120, 8, 5, 3400, 1790000000],
            [$post->status, (int) $post->social_account_id, $post->content, $post->post_url, (int) $post->likes, (int) $post->comments, (int) $post->shares, (int) $post->views, $post->created_at->timestamp]
        );
        $this->assertSame(['image', 'https://p16.tiktokcdn.com/cover-7001.jpg'], [DB::table('post_media')->value('media_type'), DB::table('post_media')->value('media_url')]);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'fields=' . TiktokPostService::VIDEO_FIELDS) && $r->hasHeader('Authorization', 'Bearer tt-access'));
    }

    public function test_import_updates_videos_already_on_file(): void
    {
        $account = $this->account();
        $existing = $this->makePost($account, [], ['post_id' => '7001', 'status' => 'completed']); // published from the app
        Http::fake([self::API . '/video/list/*' => Http::response(['data' => ['videos' => [$this->video('7001', ['like_count' => 999])]], 'error' => ['code' => 'ok']])]);

        app(TiktokPostService::class)->backfillRecentPosts($account);

        $this->assertSame(1, Post::count());
        $this->assertSame([999, 'Fresh sourdough every morning. #bakery #bread'], [(int) $existing->fresh()->likes, $existing->fresh()->content]); // counts updated, text kept
    }

    public function test_a_refused_import_still_connects_the_account(): void
    {
        Http::fake([
            self::API . '/oauth/token/' => Http::response(['open_id' => 'OPEN1', 'access_token' => 'act.1', 'refresh_token' => 'rft.1', 'expires_in' => 86400]),
            self::API . '/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'The Bakery']]]),
            self::API . '/video/list/*' => Http::response(['error' => ['code' => 'scope_not_authorized', 'message' => 'The user did not authorize the scope required for completing this request.']], 401),
        ]);
        $service = app(SocialAuthService::class);
        $service->redirect('tiktok');

        $service->callback('tiktok', 'auth-code', session('social_oauth_state_tiktok'));

        $this->assertSame('TikTok account connected.', session('success'));
        $this->assertSame(1, SocialAccount::count());
        $this->assertSame(0, Post::count());
    }

    public function test_engagement_sync_refreshes_counts_and_the_cover(): void
    {
        $account = $this->account();
        $post = $this->makePost($account, [], ['post_id' => '7001', 'status' => 'completed']);
        Http::fake([self::API . '/video/query/*' => Http::response(['data' => ['videos' => [$this->video('7001', ['like_count' => 150, 'share_count' => 9, 'view_count' => 5000, 'cover_image_url' => 'https://p16.tiktokcdn.com/new.jpg'])]], 'error' => ['code' => 'ok']])]);

        $this->assertTrue(app(TiktokPostService::class)->syncEngagement($post));

        $post->refresh();
        $this->assertSame([150, 8, 9, 5000], [(int) $post->likes, (int) $post->comments, (int) $post->shares, (int) $post->views]);
        $this->assertArrayHasKey('engagement_synced_at', $post->platform_metadata);
        $this->assertSame('https://p16.tiktokcdn.com/new.jpg', DB::table('post_media')->where('post_id', $post->id)->value('media_url'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/video/query/') && $r['filters'] === ['video_ids' => ['7001']]);
    }

    public function test_engagement_sync_skips_posts_still_processing(): void
    {
        Http::fake();
        $post = $this->makePost($this->account(), [], ['post_id' => 'v_pub_url~1', 'status' => 'completed']);

        $this->assertFalse(app(TiktokPostService::class)->syncEngagement($post));
        Http::assertNothingSent();
    }

    public function test_account_stats_are_refreshed(): void
    {
        $account = $this->account();
        Http::fake([self::API . '/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'The Bakery', 'follower_count' => 1500, 'following_count' => 12, 'likes_count' => 7000, 'video_count' => 40]], 'error' => ['code' => 'ok']])]);

        $this->assertTrue(app(TiktokPostService::class)->syncAccountStats($account));

        $account->refresh();
        $this->assertSame([1500, 12, 7000, 40], [(int) $account->followers_count, (int) $account->following_count, (int) $account->likes_count, (int) $account->media_count]);
    }

    public function test_scheduled_sync_covers_tiktok_posts(): void
    {
        $account = $this->account();
        $this->makePost($account, [], ['post_id' => '7001', 'status' => 'completed']);
        Http::fake([
            self::API . '/video/query/*' => Http::response(['data' => ['videos' => [$this->video('7001')]], 'error' => ['code' => 'ok']]),
            self::API . '/user/info/*' => Http::response(['data' => ['user' => ['follower_count' => 1]], 'error' => ['code' => 'ok']]),
        ]);

        $this->artisan('posts:sync-engagement')->expectsOutputToContain('Synced 1 of 1 post(s).')->assertSuccessful();
    }

    // ---- checking a stuck post -------------------------------------------

    public function test_post_status_command_marks_a_private_post_published(): void
    {
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~9', 'platform_metadata' => ['publish_id' => 'v_pub_url~9']]);
        Http::fake([self::API . '/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']])]);

        $this->artisan('tiktok:post-status', ['post' => $post->id])->assertSuccessful();

        $this->assertStringContainsString('private (only you can see it)', $post->fresh()->error_message);
    }

    public function test_post_status_command_shows_tiktoks_fail_reason(): void
    {
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~9', 'platform_metadata' => ['publish_id' => 'v_pub_url~9']]);
        Http::fake([self::API . '/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'video_pull_failed'], 'error' => ['code' => 'ok']])]);

        $this->artisan('tiktok:post-status', ['post' => $post->id])->expectsOutputToContain('video_pull_failed')->assertSuccessful();

        $this->assertSame('failed', $post->fresh()->status);
        $this->assertStringContainsString('video_pull_failed', $post->fresh()->error_message);
    }

    public function test_background_check_gives_the_real_reason_when_it_gives_up(): void
    {
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~9']);
        Http::fake([self::API . '/post/publish/status/fetch/' => Http::response(['error' => ['code' => 'access_token_invalid', 'message' => 'The access token is invalid or not found in the request.']], 401)]);

        (new \App\Jobs\Posts\ResolveTiktokPublishStatus($post->id, 'v_pub_url~9', 20))->handle(app(TiktokPostService::class));

        $this->assertStringContainsString('access token is invalid', $post->fresh()->error_message);
        $this->assertStringContainsString('tiktok:post-status ' . $post->id, $post->fresh()->error_message);
    }

    public function test_background_check_marks_a_failed_publish_failed(): void
    {
        $post = $this->makePost($this->account(), [['video', 'https://cdn.socialeaz.com/v.mp4']], ['status' => 'completed', 'post_id' => 'v_pub_url~9']);
        Http::fake([self::API . '/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'file_format_check_failed'], 'error' => ['code' => 'ok']])]);

        (new \App\Jobs\Posts\ResolveTiktokPublishStatus($post->id, 'v_pub_url~9'))->handle(app(TiktokPostService::class));

        $this->assertSame('failed', $post->fresh()->status);
        $this->assertStringContainsString('file_format_check_failed', $post->fresh()->error_message);
    }
}
