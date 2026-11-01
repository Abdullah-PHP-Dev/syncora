<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PostServices;
use App\Support\PostCapabilities;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Mockery;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Edit and delete of posts per platform capability, bulk delete, filters, time zones and the publisher's claim. */
class PostManagementTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Schema::create('posts', function ($t) {
            $t->id();
            foreach (['platform', 'post_id', 'group_id', 'title', 'post_url', 'status', 'visibility'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['user_id', 'social_account_id', 'post_category_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->text('content')->nullable();
            $t->text('error_message')->nullable();
            $t->json('platform_metadata')->nullable();
            $t->boolean('schedule_mode')->default(false);
            $t->timestamp('schedule_at')->nullable();
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->unsignedBigInteger('media_asset_id')->nullable();
            $t->string('media_url')->nullable();
            $t->string('media_type')->nullable();
            $t->timestamps();
        });
        Schema::create('media_assets', function ($t) {
            $t->id();
            $t->string('url')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        Settings::set('posts.linkedin.base_url', 'https://api.linkedin.com/rest/');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function account(string $platform, array $attributes = [], ?User $owner = null): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id, 'platform' => $platform, 'platform_account_id' => $platform . '-1', 'name' => ucfirst($platform) . ' Page',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_posting_permission' => true,
        ], $attributes));
    }

    private function makePost(SocialAccount $account, array $attributes = []): Post
    {
        return Post::forceCreate(array_merge([
            'platform' => $account->platform, 'user_id' => $account->user_id, 'social_account_id' => $account->id,
            'title' => 'Launch', 'content' => 'Our new collection is live', 'status' => 'completed', 'post_id' => '111_222',
        ], $attributes));
    }

    public function test_capabilities_follow_each_platforms_api_and_the_post_state(): void
    {
        $caps = fn (string $platform, array $attributes = [], array $account = []) => PostCapabilities::for($this->makePost($this->account($platform, $account + ['platform_account_id' => uniqid()]), $attributes));

        $this->assertSame([true, ['content'], true], array_values(array_intersect_key($caps('facebook'), array_flip(['can_edit', 'edit_fields', 'can_delete']))));
        $this->assertSame(['title', 'content'], $caps('youtube')['edit_fields']);
        $this->assertSame(['content'], $caps('linkedin')['edit_fields']);
        $this->assertSame(['content'], $caps('google')['edit_fields']);
        foreach (['x', 'pinterest'] as $platform) {
            $this->assertFalse($caps($platform)['can_edit'], $platform);
            $this->assertTrue($caps($platform)['can_delete'], $platform);
        }
        $this->assertSame([false, false, 'platform_read_only'], [$caps('tiktok')['can_edit'], $caps('tiktok')['can_delete'], $caps('tiktok')['reason']]);
        // Instagram deletes only through Facebook Login, and Instagram and Threads only with the delete permission granted.
        $this->assertSame([false, 'missing_permission'], [$caps('instagram')['can_delete'], $caps('instagram')['reason']]);
        $granted = fn (string $scope) => ['social_connection_id' => \App\Models\SocialConnection::forceCreate(['user_id' => $this->user->id, 'platform' => 'meta', 'provider_account_id' => uniqid(), 'step' => 'posting', 'granted_scopes' => [$scope], 'status' => 'active'])->id];
        $this->assertTrue($caps('instagram', [], $granted('instagram_manage_contents'))['can_delete']);
        $this->assertTrue($caps('threads', [], $granted('threads_delete'))['can_delete']);
        $this->assertFalse($caps('threads')['can_delete']);
        $this->assertSame([false, 'instagram_login'], [$caps('instagram', [], ['metadata' => ['settings' => ['auth_type' => 'instagram_login']]])['can_delete'], $caps('instagram', [], ['metadata' => ['settings' => ['auth_type' => 'instagram_login']]])['reason']]);
        // Not on the platform yet: managed here, for any platform.
        $scheduled = $caps('tiktok', ['status' => 'pending', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->addDay()]);
        $this->assertSame(['scheduled', true, ['title', 'content', 'schedule_at'], false, true, false], [$scheduled['state'], $scheduled['can_edit'], $scheduled['edit_fields'], $scheduled['edit_remote'], $scheduled['can_delete'], $scheduled['delete_remote']]);
        $this->assertSame([false, true], [$caps('x', ['status' => 'failed', 'post_id' => null])['can_edit'], $caps('x', ['status' => 'failed', 'post_id' => null])['can_delete']]);
        // Being sent right now: hands off.
        $this->assertSame([false, false, 'in_progress'], [$caps('facebook', ['status' => 'publishing'])['can_edit'], $caps('facebook', ['status' => 'publishing'])['can_delete'], $caps('facebook', ['status' => 'publishing'])['reason']]);
    }

    public function test_another_sellers_post_cannot_be_deleted_or_edited(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $theirs = $this->makePost($this->account('facebook', [], $other));
        Http::fake();

        $this->deleteJson(route('admin.posts.destroy', $theirs->id))->assertNotFound();
        $this->patchJson(route('admin.posts.update', $theirs->id), ['content' => 'hacked'])->assertNotFound();
        $this->postJson(route('admin.posts.bulk-destroy'), ['ids' => [$theirs->id]])->assertStatus(422);

        $this->assertNotNull($theirs->fresh());
        Http::assertNothingSent();
    }

    public function test_a_published_facebook_post_is_deleted_on_facebook_then_here(): void
    {
        $post = $this->makePost($this->account('facebook'));
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertOk()->assertJson(['success' => true, 'remote' => true]);

        $this->assertNull($post->fresh());
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'graph.facebook.com/v25.0/111_222'));
    }

    public function test_a_platform_refusal_keeps_the_post_live_and_explains_why(): void
    {
        $post = $this->makePost($this->account('facebook'));
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#200) Permissions error']], 400)]);

        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertStatus(422)->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'The platform didn’t delete the post: (#200) Permissions error']);

        $this->assertSame('completed', $post->fresh()->status);
    }

    public function test_a_post_already_removed_on_the_platform_is_cleaned_up_here(): void
    {
        $post = $this->makePost($this->account('x'));
        Http::fake(['api.x.com/*' => Http::response(['title' => 'Not Found'], 404)]);
        Settings::set('posts.x.base_url', 'https://api.x.com/2/');

        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertOk();
        $this->assertNull($post->fresh());
    }

    public function test_tiktok_posts_cannot_be_deleted_and_nothing_is_sent(): void
    {
        $post = $this->makePost($this->account('tiktok'));
        Http::fake();

        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertStatus(422);

        $this->assertNotNull($post->fresh());
        Http::assertNothingSent();
    }

    public function test_a_scheduled_post_is_edited_and_deleted_locally_with_the_sellers_time_zone(): void
    {
        $post = $this->makePost($this->account('tiktok'), ['status' => 'pending', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->addDay()]);
        Http::fake();
        $local = now('Asia/Riyadh')->addDays(2)->setTime(9, 0);

        $data = $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'New text', 'title' => 'New title', 'schedule_at' => $local->format('Y-m-d H:i:s'), 'timezone' => 'Asia/Riyadh'])
            ->assertOk()->json();

        $post->refresh();
        $this->assertSame(['New text', 'New title'], [$post->content, $post->title]);
        $this->assertSame($local->copy()->setTimezone('UTC')->toDateTimeString(), $post->schedule_at->toDateTimeString()); // 09:00 Riyadh = 06:00 UTC
        $this->assertSame('scheduled', $data['post']['state']);

        $this->patchJson(route('admin.posts.update', $post->id), ['schedule_at' => now()->addMinute()->toDateTimeString()])->assertStatus(422);
        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertOk()->assertJson(['remote' => false]);
        $this->assertNull($post->fresh());
        Http::assertNothingSent();
    }

    public function test_a_published_facebook_post_text_is_edited_on_facebook(): void
    {
        $post = $this->makePost($this->account('facebook'));
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

        $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'Updated caption', 'title' => 'ignored'])->assertOk();

        $this->assertSame(['Updated caption', 'Launch'], [$post->fresh()->content, $post->fresh()->title]); // title isn't a Facebook field
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), '/111_222') && $r['message'] === 'Updated caption');
    }

    public function test_youtube_edits_keep_the_videos_tags_and_category(): void
    {
        $post = $this->makePost($this->account('youtube'), ['post_id' => 'vid123']);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos?part=snippet&id=vid123' => Http::response(['items' => [['snippet' => ['title' => 'Old', 'description' => 'Old desc', 'tags' => ['shoes'], 'categoryId' => '22', 'channelTitle' => 'x']]]]),
            'www.googleapis.com/youtube/v3/videos?part=snippet' => Http::response(['id' => 'vid123']),
        ]);

        $this->patchJson(route('admin.posts.update', $post->id), ['title' => 'New title', 'content' => 'New description'])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT'
            && $r['snippet'] === ['title' => 'New title', 'description' => 'New description', 'tags' => ['shoes'], 'categoryId' => '22']);
    }

    public function test_linkedin_edits_and_deletes_the_post_itself(): void
    {
        $post = $this->makePost($this->account('linkedin'), ['post_id' => 'urn:li:share:123']);
        Http::fake(['api.linkedin.com/*' => Http::response(null, 204)]);

        $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'Fresh text'])->assertOk();
        $this->deleteJson(route('admin.posts.destroy', $post->id))->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->hasHeader('X-RestLi-Method', 'PARTIAL_UPDATE')
            && $r['patch'] === ['$set' => ['commentary' => 'Fresh text']] && str_ends_with($r->url(), 'posts/' . urlencode('urn:li:share:123')));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'posts/' . urlencode('urn:li:share:123')));
    }

    public function test_a_failed_edit_never_marks_a_live_post_failed(): void
    {
        $post = $this->makePost($this->account('facebook', ['expires_at' => now()->subDay()]));
        // The token refresh fails and the edit is refused.
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Session has expired']], 400)]);

        $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'x'])->assertStatus(422);

        $this->assertSame(['completed', null, 'Our new collection is live'], [$post->fresh()->status, $post->fresh()->error_message, $post->fresh()->content]);
    }

    public function test_bulk_delete_reports_each_post(): void
    {
        $fb = $this->makePost($this->account('facebook'));
        $tiktok = $this->makePost($this->account('tiktok'));
        $draft = $this->makePost($this->account('x'), ['status' => 'draft', 'post_id' => null]);
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

        $data = $this->postJson(route('admin.posts.bulk-destroy'), ['ids' => [$fb->id, $tiktok->id, $draft->id]])->assertOk()->json();

        $this->assertSame([2, 1], [$data['deleted'], $data['failed']]);
        $this->assertNull($fb->fresh());
        $this->assertNull($draft->fresh());
        $this->assertNotNull($tiktok->fresh());
    }

    public function test_quick_post_schedule_times_are_read_in_the_sellers_time_zone(): void
    {
        $value = \App\Http\Controllers\Admin\PostController::toAppTime('2026-12-01 09:00:00', 'Asia/Riyadh');
        $this->assertSame('2026-12-01 06:00:00', $value->toDateTimeString());
        $this->assertSame('2026-12-01 09:00:00', \App\Http\Controllers\Admin\PostController::toAppTime('2026-12-01 09:00:00', 'Not/AZone')->toDateTimeString());
    }

    public function test_the_publisher_claims_posts_so_they_are_never_sent_twice(): void
    {
        $meta = Mockery::mock(PostServices\MetaPostService::class);
        $this->app->instance(PostServices\MetaPostService::class, $meta);
        foreach ([PostServices\InstagramPostService::class, PostServices\GooglePostService::class, PostServices\YoutubePostService::class, PostServices\TiktokPostService::class,
            PostServices\XPostService::class, PostServices\LinkedInPostService::class, PostServices\WhatsAppPostService::class, PostServices\ThreadsPostService::class, PostServices\PinterestPostService::class] as $class) {
            $this->app->instance($class, Mockery::mock($class));
        }
        $account = $this->account('facebook');
        $due = $this->makePost($account, ['status' => 'pending', 'post_id' => null]);
        $future = $this->makePost($account, ['status' => 'pending', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->addHour()]);
        $claimed = $this->makePost($account, ['status' => 'publishing', 'post_id' => null]);
        $stuck = $this->makePost($account, ['status' => 'publishing', 'post_id' => null]);
        Post::whereKey($stuck->id)->update(['updated_at' => now()->subHour()]);

        // While the service sends it, the post is claimed - a second run would skip it.
        $meta->shouldReceive('publishPost')->once()->withArgs(fn ($p) => $p->id === $due->id)->andReturnUsing(function ($p) {
            \PHPUnit\Framework\Assert::assertSame('publishing', $p->fresh()->status);

            return ['success' => true];
        });

        $this->artisan('social:publish-posts')->assertSuccessful();

        $this->assertSame('completed', $due->fresh()->status); // settled although the service recorded nothing
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('publishing', $claimed->fresh()->status);
        $this->assertSame('failed', $stuck->fresh()->status);
        $this->assertStringContainsString('interrupted', $stuck->fresh()->error_message);
    }

    public function test_only_failures_that_never_reached_the_platform_are_retried(): void
    {
        $meta = Mockery::mock(PostServices\MetaPostService::class);
        $this->app->instance(PostServices\MetaPostService::class, $meta);
        $account = $this->account('facebook');
        $unreachable = $this->makePost($account, ['status' => 'pending', 'post_id' => null]);
        $timedOut = $this->makePost($account, ['status' => 'pending', 'post_id' => null]);
        $meta->shouldReceive('publishPost')->andReturnUsing(fn ($p) => throw new \Illuminate\Http\Client\ConnectionException($p->id === $unreachable->id
            ? 'cURL error 7: Failed to connect to graph.facebook.com port 443'
            : 'cURL error 28: Operation timed out after 300000 milliseconds'));

        $this->artisan('social:publish-posts')->assertSuccessful();

        $this->assertSame(['pending', 1], [$unreachable->fresh()->status, $unreachable->fresh()->platform_metadata['publish_attempts']]);
        // A timeout after sending may have posted it - never re-sent.
        $this->assertSame('failed', $timedOut->fresh()->status);
    }

    public function test_the_listing_filters_by_what_it_shows_and_sends_each_platforms_actions(): void
    {
        $fb = $this->account('facebook');
        $published = $this->makePost($fb, ['title' => 'Live one']);
        $scheduled = $this->makePost($fb, ['title' => 'Later one', 'status' => 'pending', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->addDay()]);
        $this->makePost($fb, ['title' => 'Broken one', 'status' => 'failed', 'post_id' => null]);

        $titles = fn (string $status) => array_column($this->getJson(route('admin.posts.data', ['status' => $status]))->assertOk()->json('data'), 'title');
        $this->assertSame(['Live one'], $titles('published'));
        $this->assertSame(['Later one'], $titles('scheduled'));
        $this->assertSame(['Broken one'], $titles('failed'));

        $entry = collect($this->getJson(route('admin.posts.data'))->json('data'))->firstWhere('id', $published->id)['platforms'][0];
        $this->assertSame(['published', '111_222', true, true], [$entry['state'], $entry['platform_post_id'], $entry['capabilities']['can_edit'], $entry['capabilities']['can_delete']]);
        $later = collect($this->getJson(route('admin.posts.data'))->json('data'))->firstWhere('id', $scheduled->id)['platforms'][0];
        $this->assertSame(['scheduled', ['title', 'content', 'schedule_at']], [$later['state'], $later['capabilities']['edit_fields']]);
    }
}
