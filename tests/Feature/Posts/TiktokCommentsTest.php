<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\TikTokDriver;
use App\Services\PostServices\TiktokCommentsService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * TikTok comments through the Business Accounts API: connect (account-
 * holder authorization), hourly import of comments + replies, replying,
 * token refresh. TikTok faked.
 */
class TiktokCommentsTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'business-api.tiktok.com/open_api/v1.3';

    private User $user;

    private SocialAccount $posting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('post_id')->nullable();
            $t->string('title')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->json('platform_metadata')->nullable();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });
        Schema::create('post_comments', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->string('comment_id')->nullable();
            $t->unsignedBigInteger('parent_comment_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->string('sender_type')->nullable();
            $t->boolean('is_reply')->default(false);
            $t->string('user_name')->nullable();
            $t->text('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->integer('likes')->default(0);
            $t->string('status')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Settings::set('ads.tiktok.client_id', 'biz-app');
        Settings::set('ads.tiktok.client_secret', 'biz-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->posting = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'OPEN1', 'name' => 'Socialeaz', 'username' => 'socialeaz', 'access_token' => 'login-kit', 'has_posting_permission' => true, 'is_token_valid' => true]);
    }

    private function businessAccount(array $overrides = []): SocialAccount
    {
        return SocialAccount::create($overrides + [
            'user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'biz_BIZ1', 'name' => 'Socialeaz',
            'account_type' => TiktokCommentsService::ACCOUNT_TYPE, 'access_token' => 'biz-token', 'refresh_token' => 'biz-refresh',
            'expires_at' => now()->addDay(), 'is_token_valid' => true, 'has_posting_permission' => false,
            'metadata' => ['business_id' => 'BIZ1', 'posting_account_id' => $this->posting->id],
        ]);
    }

    private function tiktokPost(string $videoId = '7300000000000000001'): Post
    {
        return Post::forceCreate(['platform' => 'tiktok', 'user_id' => $this->user->id, 'social_account_id' => $this->posting->id, 'post_id' => $videoId, 'status' => 'completed', 'content' => 'Our launch']);
    }

    private function comment(string $id, array $overrides = []): array
    {
        return $overrides + [
            'comment_id' => $id, 'video_id' => '7300000000000000001', 'text' => "Comment {$id}", 'likes' => 2, 'replies' => 0,
            'owner' => false, 'status' => 'PUBLIC', 'username' => "user{$id}", 'display_name' => "User {$id}",
            'profile_image' => "https://p16.tiktokcdn.com/{$id}.jpg", 'create_time' => '1790000000',
        ];
    }

    private function ok(array $data): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['code' => 0, 'message' => 'OK', 'data' => $data]);
    }

    // ---- connect ---------------------------------------------------------

    public function test_hub_offers_the_comments_step(): void
    {
        $steps = collect(app(TikTokDriver::class)->steps())->keyBy('key');

        $this->assertTrue($steps[TikTokDriver::COMMENTS]['available']);
        $url = app(TikTokDriver::class)->connect(TikTokDriver::COMMENTS)->getTargetUrl();
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize?', $url); // account-holder flow: no trailing slash, no PKCE
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame(['biz-app', 'video.list,video.insights,biz.ads.recommend,biz.brand.insights,comment.list,user.info.basic,user.info.username,user.info.stats,user.info.profile,user.account.type,user.insights,comment.list.manage,video.publish,video.upload,biz.spark.auth,discovery.search.words,biz.creator.info,biz.creator.insights,tto.campaign.link', 'code', session('tiktok_comments_state')], [$q['client_key'], $q['scope'], $q['response_type'], $q['state']]);
        $this->assertArrayNotHasKey('code_challenge', $q);
        $this->assertSame(url('/messaging/auth/tiktok/callback'), $q['redirect_uri']);
    }

    public function test_callback_connects_and_links_the_posting_account(): void
    {
        Http::fake([
            self::API . '/tt_user/oauth2/token/' => $this->ok(['access_token' => 'act.biz', 'refresh_token' => 'rft.biz', 'open_id' => 'BIZ1', 'expires_in' => 86400, 'refresh_token_expires_in' => 31536000, 'scope' => 'user.info.basic,comment.list,comment.list.manage']),
            self::API . '/business/get/*' => $this->ok(['display_name' => 'Socialeaz', 'username' => 'socialeaz', 'profile_image' => 'https://p16.tiktokcdn.com/me.jpg']),
        ]);
        app(TiktokCommentsService::class)->redirect();

        $result = app(TiktokCommentsService::class)->handleCallback(new Request(['code' => 'auth-code', 'state' => session('tiktok_comments_state')]));

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('TikTok comments connected for @socialeaz.', $result['message']);
        $biz = SocialAccount::where('account_type', TiktokCommentsService::ACCOUNT_TYPE)->sole();
        $this->assertSame(['act.biz', 'rft.biz', 'BIZ1', $this->posting->id, false], [$biz->access_token, $biz->refresh_token, $biz->metadata['business_id'], $biz->metadata['posting_account_id'], (bool) $biz->has_posting_permission]);
        $this->assertSame([TikTokDriver::COMMENTS, $biz->social_connection_id], [SocialConnection::where('step', TikTokDriver::COMMENTS)->sole()->step, SocialConnection::where('step', TikTokDriver::COMMENTS)->sole()->id]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/tt_user/oauth2/token/') && $r['auth_code'] === 'auth-code' && $r['client_id'] === 'biz-app' && $r['grant_type'] === 'authorization_code');
    }

    /** Hub "Connect" for the comments step, as a real request. */
    private function connectOverHttp(): \Illuminate\Testing\TestResponse
    {
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);

        return $this->actingAs($this->user)->get(route('admin.connections.connect', ['tiktok', TikTokDriver::COMMENTS]));
    }

    public function test_portal_callback_url_connects_over_http(): void
    {
        Http::fake([
            self::API . '/tt_user/oauth2/token/' => $this->ok(['access_token' => 'act.biz', 'refresh_token' => 'rft.biz', 'open_id' => 'BIZ1', 'expires_in' => 86400]),
            self::API . '/business/get/*' => $this->ok(['display_name' => 'Socialeaz', 'username' => 'socialeaz']),
        ]);
        $this->connectOverHttp()->assertRedirect();

        $this->get('/messaging/auth/tiktok/callback?code=auth-code&state=' . session('tiktok_comments_state'))
            ->assertRedirect(route('admin.connections.index') . '#tiktok')
            ->assertSessionHas('success', 'TikTok comments connected for @socialeaz.');

        $this->assertSame('BIZ1', SocialAccount::where('account_type', TiktokCommentsService::ACCOUNT_TYPE)->sole()->metadata['business_id']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/tt_user/oauth2/token/') && $r['redirect_uri'] === url('/messaging/auth/tiktok/callback'));
    }

    public function test_token_exchange_ignores_a_sandbox_ads_base_url(): void
    {
        Settings::set('ads.tiktok.base_url', 'https://sandbox-ads.tiktok.com/open_api/v1.3/');
        Http::fake([
            self::API . '/tt_user/oauth2/token/' => $this->ok(['access_token' => 'act.biz', 'open_id' => 'BIZ1', 'expires_in' => 86400]),
            self::API . '/business/get/*' => $this->ok(['username' => 'socialeaz']),
            '*' => Http::response('Not Found', 404),
        ]);
        app(TiktokCommentsService::class)->redirect();

        $result = app(TiktokCommentsService::class)->handleCallback(new Request(['code' => 'auth-code', 'state' => session('tiktok_comments_state')]));

        $this->assertTrue($result['success'], $result['message']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://business-api.tiktok.com/open_api/v1.3/tt_user/oauth2/token/');
    }

    public function test_portal_callback_url_reports_a_declined_consent(): void
    {
        Http::fake();
        $this->connectOverHttp();

        $this->get('/messaging/auth/tiktok/callback?error=access_denied&error_description=User+cancelled&state=' . session('tiktok_comments_state'))
            ->assertRedirect(route('admin.connections.index') . '#tiktok')
            ->assertSessionHas('error', 'TikTok declined: User cancelled');
        Http::assertNothingSent();
    }

    public function test_a_forged_state_is_rejected(): void
    {
        Http::fake();
        app(TiktokCommentsService::class)->redirect();

        $result = app(TiktokCommentsService::class)->handleCallback(new Request(['code' => 'x', 'state' => 'forged']));

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    // ---- import ----------------------------------------------------------

    public function test_comments_and_replies_are_imported(): void
    {
        $this->businessAccount();
        $post = $this->tiktokPost();
        Http::fake([
            self::API . '/business/comment/list/*' => Http::sequence()
                ->push(['code' => 0, 'data' => ['comments' => [$this->comment('C1', ['replies' => 1]), $this->comment('C2', ['status' => 'HIDDEN'])], 'has_more' => true, 'cursor' => 2]])
                ->push(['code' => 0, 'data' => ['comments' => [$this->comment('C3', ['owner' => true, 'display_name' => 'Socialeaz'])], 'has_more' => false, 'cursor' => 3]]),
            self::API . '/business/comment/reply/list/*' => $this->ok(['comments' => [$this->comment('R1', ['parent_comment_id' => 'C1', 'text' => 'Thanks!', 'owner' => true])], 'has_more' => false]),
        ]);

        $result = app(TiktokCommentsService::class)->syncComments($post);

        $this->assertSame(['success' => true, 'synced' => 4], $result);
        $c1 = PostComment::where('comment_id', 'C1')->sole();
        $this->assertSame([$post->id, 'customer', 'User C1', 'https://p16.tiktokcdn.com/C1.jpg', 2, 'approved', 1790000000], [(int) $c1->post_id, $c1->sender_type, $c1->user_name, $c1->user_avatar_url, (int) $c1->likes, $c1->status, $c1->posted_at->timestamp]);
        $reply = PostComment::where('comment_id', 'R1')->sole();
        $this->assertSame([$c1->id, true, 'support'], [(int) $reply->parent_comment_id, (bool) $reply->is_reply, $reply->sender_type]);
        $this->assertSame('hidden', PostComment::where('comment_id', 'C2')->value('status'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/business/comment/list/') && $r->hasHeader('Access-Token', 'biz-token')
            && str_contains($r->url(), 'business_id=BIZ1') && str_contains($r->url(), 'video_id=7300000000000000001') && str_contains($r->url(), 'cursor=2'));
    }

    public function test_resync_updates_instead_of_duplicating(): void
    {
        $this->businessAccount();
        $post = $this->tiktokPost();
        Http::fake([self::API . '/business/comment/list/*' => Http::sequence()
            ->push(['code' => 0, 'data' => ['comments' => [$this->comment('C1')], 'has_more' => false]])
            ->push(['code' => 0, 'data' => ['comments' => [$this->comment('C1', ['likes' => 9, 'text' => 'Edited'])], 'has_more' => false]])]);

        app(TiktokCommentsService::class)->syncComments($post);
        app(TiktokCommentsService::class)->syncComments($post);

        $this->assertSame(1, PostComment::count());
        $this->assertSame([9, 'Edited'], [(int) PostComment::sole()->likes, PostComment::sole()->content]);
    }

    public function test_comments_paused_in_the_hub_are_not_synced(): void
    {
        $this->businessAccount(['enabled_capabilities' => []]);
        Http::fake();

        $this->assertNull(app(TiktokCommentsService::class)->accountFor($this->tiktokPost()));
        Http::assertNothingSent();
    }

    public function test_without_a_comments_connection_nothing_is_called(): void
    {
        Http::fake();

        $result = app(TiktokCommentsService::class)->syncComments($this->tiktokPost());

        $this->assertStringContainsString('Connect "TikTok comments"', $result['message']);
        Http::assertNothingSent();
    }

    public function test_a_missing_permission_explains_what_to_do(): void
    {
        $this->businessAccount();
        Http::fake([self::API . '/business/comment/list/*' => Http::response(['code' => 40001, 'message' => 'No permission to operate the scope comment.list'])]);

        $result = app(TiktokCommentsService::class)->syncComments($this->tiktokPost());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('comment permissions approved', $result['message']);
    }

    public function test_an_expired_token_is_refreshed_first(): void
    {
        $biz = $this->businessAccount(['expires_at' => now()->subHour()]);
        Http::fake([
            self::API . '/tt_user/oauth2/refresh_token/' => $this->ok(['access_token' => 'act.new', 'refresh_token' => 'rft.new', 'expires_in' => 86400]),
            self::API . '/business/comment/list/*' => $this->ok(['comments' => [], 'has_more' => false]),
        ]);

        app(TiktokCommentsService::class)->syncComments($this->tiktokPost());

        $this->assertSame(['act.new', 'rft.new'], [$biz->fresh()->access_token, $biz->fresh()->refresh_token]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/business/comment/list/') && $r->hasHeader('Access-Token', 'act.new'));
    }

    public function test_a_rejected_refresh_asks_for_a_reconnect(): void
    {
        $biz = $this->businessAccount(['expires_at' => now()->subHour()]);
        Http::fake([self::API . '/tt_user/oauth2/refresh_token/' => Http::response(['code' => 40104, 'message' => 'refresh token expired'])]);

        $result = app(TiktokCommentsService::class)->syncComments($this->tiktokPost());

        $this->assertStringContainsString('reconnect it in Connections', $result['message']);
        $this->assertFalse((bool) $biz->fresh()->is_token_valid);
    }

    // ---- reply / comment -------------------------------------------------

    public function test_reply_from_the_preview_is_published_and_saved(): void
    {
        $this->businessAccount();
        $post = $this->tiktokPost();
        $parent = PostComment::create(['platform' => 'tiktok', 'comment_id' => 'C1', 'post_id' => $post->id, 'content' => 'Nice', 'sender_type' => 'customer']);
        Http::fake([self::API . '/business/comment/reply/create/' => $this->ok(['comment_id' => 'R9'])]);

        $data = app(\App\Http\Controllers\Admin\PostController::class)->storeReply(new Request(['content' => 'Thank you!']), $parent)->getData(true);

        $this->assertTrue($data['success']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/business/comment/reply/create/')
            && $r['business_id'] === 'BIZ1' && $r['video_id'] === '7300000000000000001' && $r['comment_id'] === 'C1' && $r['text'] === 'Thank you!');
        $reply = PostComment::where('comment_id', 'R9')->sole();
        $this->assertSame([$parent->id, 'support', 'Socialeaz'], [(int) $reply->parent_comment_id, $reply->sender_type, $reply->user_name]);
    }

    public function test_new_comment_from_the_preview_goes_to_comment_create(): void
    {
        $this->businessAccount();
        $post = $this->tiktokPost();
        Http::fake([self::API . '/business/comment/create/' => $this->ok(['comment_id' => 'N1'])]);

        $data = app(\App\Http\Controllers\Admin\PostController::class)->storeComment(new Request(['content' => 'Pinned info']), $post)->getData(true);

        $this->assertTrue($data['success']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/business/comment/create/') && ! isset($r['comment_id']) && $r['text'] === 'Pinned info');
        $this->assertFalse((bool) PostComment::where('comment_id', 'N1')->sole()->is_reply);
    }

    // ---- hourly command --------------------------------------------------

    public function test_hourly_command_imports_recent_posts_only_once_on_tiktok(): void
    {
        $this->businessAccount();
        $this->tiktokPost('7300000000000000001');
        Post::forceCreate(['platform' => 'tiktok', 'user_id' => $this->user->id, 'social_account_id' => $this->posting->id, 'post_id' => 'v_pub_file~1', 'status' => 'completed']); // still processing
        Http::fake([self::API . '/business/comment/list/*' => $this->ok(['comments' => [$this->comment('C1')], 'has_more' => false])]);

        $this->artisan('tiktok:sync-comments')->expectsOutputToContain('Imported 1 comment(s) from 1 of 1 TikTok post(s).')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, PostComment::count());
    }

    public function test_command_is_scheduled_hourly(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression('/^\s*0\s+\*\s+\*\s+\*\s+\*\s+php artisan tiktok:sync-comments/m', \Illuminate\Support\Facades\Artisan::output());
    }
}
