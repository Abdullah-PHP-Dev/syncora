<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Notifications\Emitters\PublishingNotifications;
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

/**
 * SocialEaz-side lifecycle (drafts, cancel schedule, retry), X post editing,
 * per-destination outcomes and ownership. All platform calls are faked.
 */
class PostLifecycleTest extends TestCase
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
            $t->boolean('expiry_mode')->default(false);
            $t->timestamp('expiry_at')->nullable();
            $t->timestamp('published_at')->nullable();
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
        Settings::set('posts.x.base_url', 'https://api.x.com/2/');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function account(string $platform, array $attributes = [], ?User $owner = null): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id, 'platform' => $platform, 'platform_account_id' => $platform . '-' . uniqid(), 'name' => ucfirst($platform) . ' Page',
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

    private function state(Post $post, array $body)
    {
        return $this->postJson(route('admin.posts.state', $post->id), $body);
    }

    /** Every publisher service mocked; returns the ones the test drives. */
    private function mockServices(): array
    {
        $mocks = [];
        foreach ([PostServices\MetaPostService::class, PostServices\InstagramPostService::class, PostServices\GooglePostService::class, PostServices\YoutubePostService::class,
            PostServices\TiktokPostService::class, PostServices\XPostService::class, PostServices\LinkedInPostService::class, PostServices\WhatsAppPostService::class,
            PostServices\ThreadsPostService::class, PostServices\PinterestPostService::class] as $class) {
            $this->app->instance($class, $mocks[$class] = Mockery::mock($class));
        }

        return $mocks;
    }

    // ---------------------------------------------------------------- drafts

    public function test_the_composer_saves_a_real_draft_that_is_never_published(): void
    {
        Schema::create('post_categories', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name')->nullable();
            $t->timestamps();
        });
        $category = \Illuminate\Support\Facades\DB::table('post_categories')->insertGetId(['user_id' => $this->user->id, 'name' => 'General']);
        $facebook = $this->account('facebook');
        $x = $this->account('x');

        $this->post(route('admin.posts.store'), [
            'content' => 'Draft caption', 'category_id' => $category, 'platforms' => ['facebook', 'x'],
            'facebook' => ['pages' => [$facebook->id]], 'x' => ['pages' => [$x->id]],
            'schedule_mode' => 0, 'save_as_draft' => 1,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('success', true);

        $posts = Post::orderBy('id')->get();
        $this->assertCount(2, $posts);
        $this->assertSame(['draft', 'draft'], $posts->pluck('status')->all());
        $this->assertSame(1, $posts->pluck('group_id')->unique()->count());

        $mocks = $this->mockServices();
        $mocks[PostServices\MetaPostService::class]->shouldNotReceive('publishPost');
        $mocks[PostServices\XPostService::class]->shouldNotReceive('publishPost');
        $this->artisan('social:publish-posts')->assertSuccessful();
        $this->assertSame(['draft', 'draft'], Post::orderBy('id')->pluck('status')->all());
    }

    public function test_a_draft_is_published_now_or_scheduled(): void
    {
        $account = $this->account('facebook');
        $now = $this->makePost($account, ['status' => 'draft', 'post_id' => null]);
        $later = $this->makePost($account, ['status' => 'draft', 'post_id' => null]);
        $this->assertTrue(PostCapabilities::for($now)['can_publish']);

        $this->state($now, ['action' => 'publish'])->assertOk()->assertJsonPath('post.state', 'pending');
        $this->state($later, ['action' => 'publish', 'schedule_at' => now()->addDay()->format('Y-m-d H:i:s')])->assertOk()->assertJsonPath('post.state', 'scheduled');
        $this->state($later->fresh(), ['action' => 'publish'])->assertStatus(422); // no longer a draft

        $this->assertSame([0, 'pending'], [(int) $now->fresh()->schedule_mode, $now->fresh()->status]);
        $this->assertTrue($later->fresh()->schedule_at->isFuture());
    }

    public function test_a_draft_cant_be_scheduled_less_than_ten_minutes_ahead(): void
    {
        $post = $this->makePost($this->account('facebook'), ['status' => 'draft', 'post_id' => null]);

        $this->state($post, ['action' => 'publish', 'schedule_at' => now()->addMinutes(2)->format('Y-m-d H:i:s')])->assertStatus(422);
        $this->assertSame('draft', $post->fresh()->status);
    }

    // ------------------------------------------------------- cancel schedule

    public function test_cancelling_a_schedule_keeps_the_post_as_a_draft_the_publisher_skips(): void
    {
        $post = $this->makePost($this->account('facebook'), ['status' => 'pending', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->addHour()]);
        $this->assertTrue(PostCapabilities::for($post)['can_cancel']);

        $this->state($post, ['action' => 'cancel'])->assertOk()->assertJsonPath('post.state', 'draft');

        Post::whereKey($post->id)->update(['schedule_at' => now()->subMinute()]); // its time comes
        $this->mockServices()[PostServices\MetaPostService::class]->shouldNotReceive('publishPost');
        $this->artisan('social:publish-posts')->assertSuccessful();
        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_a_post_the_publisher_already_claimed_cant_be_cancelled(): void
    {
        $post = $this->makePost($this->account('facebook'), ['status' => 'publishing', 'post_id' => null, 'schedule_mode' => 1, 'schedule_at' => now()->subMinute()]);

        $this->state($post, ['action' => 'cancel'])->assertStatus(422);
        $this->assertSame('publishing', $post->fresh()->status);
    }

    // ------------------------------------------------------------------ retry

    public function test_a_failed_post_the_platform_never_received_is_queued_again_once(): void
    {
        $post = $this->makePost($this->account('facebook'), ['status' => 'failed', 'post_id' => null, 'error_message' => 'Rate limited', 'platform_metadata' => ['publish_attempts' => 3]]);

        $this->state($post, ['action' => 'retry'])->assertOk();
        $this->state($post, ['action' => 'retry'])->assertStatus(422); // a second click can't queue it twice

        $fresh = $post->fresh();
        $this->assertSame(['pending', null], [$fresh->status, $fresh->error_message]);
        $this->assertArrayNotHasKey('publish_attempts', $fresh->platform_metadata);
        $this->assertSame(1, $fresh->platform_metadata['manual_retries']);

        // The publisher sends it on its next run (package checks happen there).
        $this->mockServices()[PostServices\MetaPostService::class]->shouldReceive('publishPost')->once()->andReturn(['success' => true]);
        $this->artisan('social:publish-posts')->assertSuccessful();
        $this->assertSame('completed', $post->fresh()->status);
    }

    public function test_posts_that_may_already_be_live_are_not_resent_blindly(): void
    {
        $account = $this->account('facebook');
        $reached = $this->makePost($account, ['status' => 'failed', 'post_id' => '999']);
        $broadcast = $this->makePost($this->account('whatsapp'), ['status' => 'failed', 'post_id' => null]);
        $interrupted = $this->makePost($account, ['status' => 'failed', 'post_id' => null, 'error_message' => PublishingNotifications::INTERRUPTED . '. Check the platform before posting it again.']);

        $this->assertSame([false, 'retry_reached_platform'], [PostCapabilities::for($reached)['can_retry'], PostCapabilities::for($reached)['reason']]);
        $this->assertSame([false, 'retry_whatsapp'], [PostCapabilities::for($broadcast)['can_retry'], PostCapabilities::for($broadcast)['reason']]);
        $this->state($reached, ['action' => 'retry'])->assertStatus(422);
        $this->state($broadcast, ['action' => 'retry'])->assertStatus(422);

        // Interrupted: only after the seller confirms.
        $this->state($interrupted, ['action' => 'retry'])->assertStatus(422)->assertJsonPath('confirm', true);
        $this->assertSame('failed', $interrupted->fresh()->status);
        $this->state($interrupted, ['action' => 'retry', 'confirmed' => true])->assertOk();
        $this->assertSame('pending', $interrupted->fresh()->status);
    }

    // ------------------------------------------------- per-destination status

    public function test_each_destination_of_one_post_keeps_its_own_outcome(): void
    {
        $group = (string) \Illuminate\Support\Str::uuid();
        $facebook = $this->makePost($this->account('facebook'), ['status' => 'pending', 'post_id' => null, 'group_id' => $group]);
        $x = $this->makePost($this->account('x'), ['status' => 'pending', 'post_id' => null, 'group_id' => $group]);
        $mocks = $this->mockServices();
        $mocks[PostServices\MetaPostService::class]->shouldReceive('publishPost')->once()->andReturn(['success' => true]);
        $mocks[PostServices\XPostService::class]->shouldReceive('publishPost')->once()->andReturn(['success' => false, 'message' => 'Duplicate content']);

        $this->artisan('social:publish-posts')->assertSuccessful();

        $this->assertSame('completed', $facebook->fresh()->status);
        $this->assertNotNull($facebook->fresh()->published_at); // recorded when it went live
        $this->assertSame(['failed', 'Duplicate content'], [$x->fresh()->status, $x->fresh()->error_message]);
        $this->assertNull($x->fresh()->published_at);
        $this->assertTrue(PostCapabilities::for($x->fresh())['can_retry']);
    }

    // ------------------------------------------------------------------ X edit

    private function xPost(array $attributes = [], string $subscription = 'Premium'): Post
    {
        $account = $this->account('x', ['metadata' => ['x_subscription_type' => $subscription, 'x_subscription_checked_at' => now()->toIso8601String()]]);

        return $this->makePost($account, array_merge(['post_id' => '1800000000000000001', 'published_at' => now()->subMinutes(5)], $attributes));
    }

    public function test_x_posts_are_edited_on_x_and_follow_the_new_post_id(): void
    {
        $post = $this->xPost();
        $this->assertSame([true, ['content']], [PostCapabilities::for($post)['can_edit'], PostCapabilities::for($post)['edit_fields']]);
        Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1800000000000000002', 'text' => 'Fixed typo', 'edit_history_post_ids' => ['1800000000000000001', '1800000000000000002']]], 201)]);

        $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'Fixed typo'])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->url() === 'https://api.x.com/2/tweets'
            && $r['text'] === 'Fixed typo' && $r['edit_options'] === ['previous_post_id' => '1800000000000000001'] && ! isset($r['media']));
        $fresh = $post->fresh();
        $this->assertSame(['Fixed typo', '1800000000000000002', 'https://x.com/i/web/status/1800000000000000002', 1],
            [$fresh->content, $fresh->post_id, $fresh->post_url, $fresh->platform_metadata['x_edits']]);
    }

    public function test_x_edit_is_only_offered_within_xs_rules(): void
    {
        $reason = fn (Post $p) => [PostCapabilities::for($p)['can_edit'], PostCapabilities::for($p)['reason']];

        $this->assertSame([false, 'x_edit_premium'], $reason($this->xPost([], 'None')));
        $this->assertSame([false, 'x_edit_window'], $reason($this->xPost(['published_at' => now()->subMinutes(31)])));
        // No recorded publish time (older posts): never guessed.
        $this->assertSame([false, 'x_edit_window'], $reason($this->xPost(['published_at' => null])));
        $this->assertSame([false, 'x_edit_limit'], $reason($this->xPost(['platform_metadata' => ['x_edits' => 5]])));
        $withMedia = $this->xPost();
        \App\Models\PostMedia::forceCreate(['post_id' => $withMedia->id, 'media_url' => 'https://cdn.example/a.jpg', 'media_type' => 'image']);
        $this->assertSame([false, 'x_edit_media'], $reason($withMedia));
        // Delete stays available either way.
        $this->assertTrue(PostCapabilities::for($withMedia)['can_delete']);

        Http::fake();
        $this->patchJson(route('admin.posts.update', $withMedia->id), ['content' => 'x'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_an_x_refusal_keeps_the_post_and_explains_the_rules(): void
    {
        $post = $this->xPost();
        Http::fake(['api.x.com/2/tweets' => Http::response(['title' => 'Forbidden', 'detail' => 'You are not permitted to perform this action.', 'status' => 403], 403)]);

        $this->patchJson(route('admin.posts.update', $post->id), ['content' => 'New'])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'within 30 minutes'));

        $this->assertSame(['1800000000000000001', 'Our new collection is live', 'completed'], [$post->fresh()->post_id, $post->fresh()->content, $post->fresh()->status]);
    }

    // -------------------------------------------------------------- ownership

    public function test_another_sellers_post_cant_be_viewed_or_changed(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $post = $this->makePost($this->account('facebook', [], $other), ['status' => 'failed', 'post_id' => null]);

        $this->state($post, ['action' => 'retry'])->assertNotFound();
        $this->get(route('admin.posts.show', $post->id))->assertNotFound();
        $this->assertSame('failed', $post->fresh()->status);
    }

    public function test_unknown_actions_are_rejected(): void
    {
        $post = $this->makePost($this->account('facebook'), ['status' => 'failed', 'post_id' => null]);

        $this->state($post, ['action' => 'publish_everywhere'])->assertStatus(422);
        $this->assertSame('failed', $post->fresh()->status);
    }
}
