<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PostServices\LinkedInPostService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * LinkedIn Page post engagement: polling (socialActions + organic share
 * statistics), the Organization Social Action Notifications webhook
 * (challenge, signature, payload) and the eventSubscriptions subscribe.
 * Payload shapes follow learn.microsoft.com; all LinkedIn calls are faked.
 */
class LinkedInEngagementTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'https://api.linkedin.com/rest/';
    private const SECRET = 'li-client-secret';
    private const URN = 'urn:li:share:7400000000000000001';

    private User $user;
    private SocialAccount $account;
    private Post $post;

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
            foreach (['likes', 'comments', 'shares', 'views', 'clicks', 'reach', 'impressions', 'saves'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->text('content')->nullable();
            $t->text('error_message')->nullable();
            $t->json('platform_metadata')->nullable();
            $t->boolean('schedule_mode')->default(false);
            $t->timestamp('schedule_at')->nullable();
            $t->timestamps();
        });
        Schema::create('post_comments', function ($t) {
            $t->id();
            foreach (['platform', 'comment_id', 'sender_type', 'user_name', 'status'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['parent_comment_id', 'user_id', 'social_account_id', 'post_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->boolean('is_reply')->default(false);
            $t->text('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->integer('likes')->default(0);
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->string('media_url')->nullable();
            $t->string('media_type')->nullable();
            $t->string('thumbnail_url')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.linkedin.base_url', self::API);
        Settings::set('posts.linkedin.client_secret', self::SECRET);
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => '99887766', 'name' => 'Socialeaz',
            'access_token' => 'li-token', 'refresh_token' => 'li-refresh', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_posting_permission' => true]);
        $this->post = Post::forceCreate(['platform' => 'linkedin', 'user_id' => $this->user->id, 'social_account_id' => $this->account->id,
            'post_id' => self::URN, 'status' => 'completed', 'content' => 'Launch day']);
    }

    private function fakeLinkedIn(array $overrides = []): void
    {
        Http::fake($overrides + [
            self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments*' => Http::response(['elements' => [
                ['id' => '7400000000000000555', 'actor' => 'urn:li:person:abc', 'message' => ['text' => 'Congrats!'], 'created' => ['time' => 1764200000000], 'likesSummary' => ['totalLikes' => 2]],
            ]]),
            self::API . 'socialActions/*' => Http::response(['likesSummary' => ['totalLikes' => 12], 'commentsSummary' => ['totalFirstLevelComments' => 3, 'aggregatedTotalComments' => 4]]),
            self::API . 'organizationalEntityShareStatistics*' => Http::response(['elements' => [[
                'share' => self::URN, 'organizationalEntity' => 'urn:li:organization:99887766',
                'totalShareStatistics' => ['impressionCount' => 1500, 'uniqueImpressionsCount' => 900, 'clickCount' => 41, 'shareCount' => 5, 'likeCount' => 12, 'commentCount' => 4, 'engagement' => 0.04],
            ]]]),
        ]);
    }

    private function signed(array $payload): array
    {
        $body = json_encode($payload);

        return [$body, hash_hmac('sha256', 'hmacsha256=' . $body, self::SECRET)];
    }

    private function sendWebhook(array $payload, ?string $signature = null)
    {
        [$body, $valid] = $this->signed($payload);

        return $this->call('POST', route('comments.webhook.linkedin.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_LI_SIGNATURE' => $signature ?? $valid,
        ], $body);
    }

    private function notification(array $attributes = []): array
    {
        return ['type' => 'ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS', 'notifications' => [array_merge([
            'notificationId' => 4406004, 'action' => 'COMMENT', 'organizationalEntity' => 'urn:li:organization:99887766',
            'sourcePost' => self::URN, 'generatedActivity' => 'urn:li:comment:(urn:li:activity:7400000000000000009,7400000000000000555)',
            'lastModifiedAt' => 1764200000000, 'subscriber' => 'urn:li:person:admin1',
        ], $attributes)]];
    }

    public function test_sync_reads_likes_comments_impressions_clicks_and_reposts(): void
    {
        $this->fakeLinkedIn();

        $this->assertTrue(app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount')));

        $post = $this->post->fresh();
        $this->assertSame([12, 4, 1500, 900, 41, 5], [$post->likes, $post->comments, $post->impressions, $post->reach, $post->clicks, $post->shares]);
        $this->assertNull($post->platform_metadata['engagement_errors']);
        $this->assertSame(['Congrats!'], PostComment::where('post_id', $post->id)->pluck('content')->all());
        // Restli 2.0: List() literal, URNs encoded; versioned headers.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'organizationalEntityShareStatistics?q=organizationalEntity&organizationalEntity=urn%3Ali%3Aorganization%3A99887766&shares=List(urn%3Ali%3Ashare%3A7400000000000000001)')
            && $r->hasHeader('X-Restli-Protocol-Version', '2.0.0') && $r->hasHeader('Linkedin-Version'));
    }

    public function test_comments_are_imported_without_the_undocumented_sort_parameter(): void
    {
        $this->fakeLinkedIn([self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments*' => Http::response(['elements' => [
            ['id' => '1001', 'actor' => 'urn:li:person:abc', 'message' => ['text' => 'Older comment'], 'created' => ['time' => 1764100000000]],
            ['id' => '1002', 'actor' => 'urn:li:organization:99887766', 'message' => ['text' => 'Thanks from the Page'], 'created' => ['time' => 1764300000000]],
            ['id' => '1003', 'actor' => 'urn:li:person:xyz', 'actor~' => ['localizedFirstName' => 'Sara', 'localizedLastName' => 'Ali'], 'message' => ['text' => 'Love it'], 'created' => ['time' => 1764200000000]],
            ['id' => '1004', 'actor' => 'urn:li:person:abc', 'parentComment' => 'urn:li:comment:(urn:li:activity:1,1001)', 'message' => ['text' => 'nested reply'], 'created' => ['time' => 1764400000000]],
        ]])]);

        app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount'));

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/comments?start=0&count=50') && ! str_contains($r->url(), 'sortOrder'));
        $comments = PostComment::where('post_id', $this->post->id)->orderByDesc('posted_at')->get();
        $this->assertSame(
            [['Thanks from the Page', 'Socialeaz', 'support'], ['Love it', 'Sara Ali', 'customer'], ['Older comment', 'LinkedIn member', 'customer']],
            $comments->map(fn ($c) => [$c->content, $c->user_name, $c->sender_type])->all()
        );
        $this->assertNull($this->post->fresh()->platform_metadata['engagement_errors']);
    }

    public function test_if_linkedin_refuses_paging_parameters_the_first_page_is_read_without_them(): void
    {
        $url = self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments';
        $this->fakeLinkedIn([$url . '*' => fn (HttpRequest $r) => str_contains($r->url(), 'count=')
            ? Http::response(['status' => 400, 'message' => 'Query parameter count is not allowed'], 400)
            : Http::response(['elements' => [['id' => '2001', 'actor' => 'urn:li:person:abc', 'message' => ['text' => 'Hello'], 'created' => ['time' => 1764100000000]]]])]);

        app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount'));

        $this->assertSame(['Hello'], PostComment::where('post_id', $this->post->id)->pluck('content')->all());
    }

    public function test_a_refused_comments_read_is_explained_not_silent(): void
    {
        $this->fakeLinkedIn([self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments*' => Http::response(['status' => 403, 'message' => 'Not enough permissions to access: GET /socialActions/{id}/comments'], 403)]);

        app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount'));

        $post = $this->post->fresh();
        $this->assertSame(4, $post->comments); // the count still comes through
        $this->assertStringContainsString('Not enough permissions', $post->platform_metadata['engagement_errors']['comments']);
    }

    public function test_a_reply_sent_from_socialeaz_is_not_imported_twice(): void
    {
        PostComment::forceCreate(['post_id' => $this->post->id, 'platform' => 'linkedin', 'comment_id' => 'urn:li:comment:(urn:li:activity:7400000000000000009,3001)',
            'content' => 'Thanks!', 'sender_type' => 'support', 'user_name' => 'Zaheer', 'is_reply' => false]);
        $this->fakeLinkedIn([self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments*' => Http::response(['elements' => [
            ['id' => '3001', 'commentUrn' => 'urn:li:comment:(urn:li:activity:7400000000000000009,3001)', 'actor' => 'urn:li:organization:99887766', 'message' => ['text' => 'Thanks!'], 'created' => ['time' => 1764100000000], 'likesSummary' => ['totalLikes' => 3]],
        ]])]);

        app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount'));

        $comments = PostComment::where('post_id', $this->post->id)->get();
        $this->assertCount(1, $comments);
        $this->assertSame([3, 'support'], [$comments[0]->likes, $comments[0]->sender_type]);
    }

    public function test_the_preview_shows_comments_imported_while_opening_it(): void
    {
        $this->actingAs($this->user);
        $this->withoutMiddleware([\Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class, \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class, \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class]);
        $this->withoutVite();
        $this->fakeLinkedIn();

        $response = $this->get(route('admin.posts.preview', [$this->post->id, 'linkedin']));

        $response->assertOk()->assertViewHas('post', fn ($post) => collect($post['engagement']['comments'])->pluck('content')->all() === ['Congrats!']);
    }

    public function test_ugc_posts_use_the_ugcposts_parameter(): void
    {
        $this->post->forceFill(['post_id' => 'urn:li:ugcPost:7400000000000000002'])->save();
        $this->fakeLinkedIn();

        app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount'));

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '&ugcPosts=List(urn%3Ali%3AugcPost%3A7400000000000000002)'));
    }

    public function test_without_the_statistics_permission_likes_and_comments_still_sync_and_the_reason_is_kept(): void
    {
        $this->fakeLinkedIn([self::API . 'organizationalEntityShareStatistics*' => Http::response(['status' => 403, 'message' => 'Not enough permissions to access: GET /organizationalEntityShareStatistics'], 403)]);

        $this->assertTrue(app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount')));

        $post = $this->post->fresh();
        $this->assertSame([12, 4, 0], [$post->likes, $post->comments, $post->impressions]);
        $this->assertStringContainsString('rw_organization_admin', $post->platform_metadata['engagement_errors']['statistics']);
        $this->assertSame('completed', $post->status); // a failed read never fails a live post
    }

    public function test_an_expired_token_that_cant_refresh_never_marks_the_post_failed(): void
    {
        $this->account->update(['expires_at' => now()->subDay()]);
        Http::fake(['www.linkedin.com/oauth/v2/accessToken' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->assertFalse(app(LinkedInPostService::class)->syncEngagement($this->post->fresh('socialAccount')));

        $this->assertSame(['completed', null], [$this->post->fresh()->status, $this->post->fresh()->error_message]);
        $this->assertArrayHasKey('token', $this->post->fresh()->platform_metadata['engagement_errors']);
    }

    public function test_the_scheduled_sync_covers_linkedin_posts(): void
    {
        $this->fakeLinkedIn();

        $this->artisan('posts:sync-engagement', ['--post' => $this->post->id])->assertSuccessful();

        $this->assertSame(1500, $this->post->fresh()->impressions);
    }

    public function test_publishing_stores_the_link_to_the_post(): void
    {
        $this->assertSame('https://www.linkedin.com/feed/update/' . self::URN, LinkedInPostService::permalink(self::URN));
    }

    // ---------------------------------------------------------------- webhook

    public function test_the_webhook_answers_linkedins_challenge(): void
    {
        $this->getJson(route('comments.webhook.linkedin.verify', ['challengeCode' => '890e4665-4dfe-4ab1-b689-ed553bceeed0']))
            ->assertOk()->assertExactJson([
                'challengeCode' => '890e4665-4dfe-4ab1-b689-ed553bceeed0',
                'challengeResponse' => hash_hmac('sha256', '890e4665-4dfe-4ab1-b689-ed553bceeed0', self::SECRET),
            ]);
        $this->get(route('comments.webhook.linkedin.verify'))->assertStatus(400);
    }

    public function test_unsigned_or_forged_notifications_are_rejected(): void
    {
        Http::fake();

        $this->sendWebhook($this->notification(), 'deadbeef')->assertForbidden();
        $this->call('POST', route('comments.webhook.linkedin.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->notification()))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_comment_notification_refreshes_the_posts_counts_and_comments(): void
    {
        $this->fakeLinkedIn();

        $this->sendWebhook($this->notification())->assertOk()->assertSee('EVENT_RECEIVED');

        $post = $this->post->fresh();
        $this->assertSame([12, 4, 1500], [$post->likes, $post->comments, $post->impressions]);
        $this->assertSame(1, PostComment::where('post_id', $post->id)->count());
    }

    public function test_redelivered_notifications_are_processed_once(): void
    {
        // The sync runs after the response; count what each delivery queues.
        \Illuminate\Support\Facades\Bus::fake();

        $this->sendWebhook($this->notification(['action' => 'LIKE']))->assertOk();
        $this->sendWebhook($this->notification(['action' => 'LIKE']))->assertOk(); // LinkedIn redelivers
        $this->sendWebhook($this->notification(['action' => 'LIKE', 'lastModifiedAt' => 1764200009999]))->assertOk(); // a newer event

        \Illuminate\Support\Facades\Bus::assertDispatchedAfterResponseTimes(\Illuminate\Queue\CallQueuedClosure::class, 2);
    }

    public function test_a_deleted_comment_is_removed(): void
    {
        PostComment::forceCreate(['post_id' => $this->post->id, 'platform' => 'linkedin', 'comment_id' => '7400000000000000555', 'content' => 'Congrats!']);
        $this->fakeLinkedIn([self::API . 'socialActions/' . rawurlencode(self::URN) . '/comments*' => Http::response(['elements' => []])]);

        $this->sendWebhook($this->notification(['action' => 'COMMENT_DELETE', 'notificationId' => 4406005]))->assertOk();

        $this->assertSame(0, PostComment::where('post_id', $this->post->id)->count());
    }

    public function test_an_activity_urn_refreshes_the_pages_recent_posts(): void
    {
        $this->fakeLinkedIn();

        $this->sendWebhook($this->notification(['sourcePost' => 'urn:li:activity:7400000000000000009', 'notificationId' => 4406006]))->assertOk();

        $this->assertSame(1500, $this->post->fresh()->impressions);
    }

    public function test_notifications_for_another_page_touch_nothing(): void
    {
        Http::fake();

        $this->sendWebhook($this->notification(['organizationalEntity' => 'urn:li:organization:1', 'notificationId' => 4406007]))->assertOk();

        Http::assertNothingSent();
    }

    // ----------------------------------------------------------- subscription

    public function test_the_page_is_subscribed_to_social_action_notifications(): void
    {
        Settings::set('posts.linkedin.client_id', '226711');
        Http::fake([
            self::API . 'organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:99887766', 'roleAssignee' => 'urn:li:person:abc123', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED']]]),
            self::API . 'eventSubscriptions/*' => Http::response(null, 201),
        ]);

        app(LinkedInPostService::class)->subscribeToWebhooks($this->account);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), 'eventSubscriptions/(developerApplication:urn%3Ali%3AdeveloperApplication%3A226711,user:urn%3Ali%3Aperson%3Aabc123,entity:urn%3Ali%3Aorganization%3A99887766,eventType:ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS)')
            && $r['webhook'] === route('comments.webhook.linkedin.receive'));
        $this->assertTrue($this->account->fresh()->metadata['settings']['webhook_subscriptions']);
    }

    public function test_without_an_app_id_or_permission_the_reason_is_recorded(): void
    {
        Http::preventStrayRequests();
        app(LinkedInPostService::class)->subscribeToWebhooks($this->account);
        $this->assertSame([false, 'not_configured'], [$this->account->fresh()->metadata['settings']['webhook_subscriptions'], $this->account->fresh()->metadata['settings']['webhook_status']['reason']]);

        Settings::set('posts.linkedin.client_id', '226711');
        Http::fake([
            self::API . 'organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:99887766', 'roleAssignee' => 'urn:li:person:abc123']]]),
            self::API . 'eventSubscriptions/*' => Http::response(['status' => 403, 'message' => 'Not enough permissions'], 403),
        ]);
        app(LinkedInPostService::class)->subscribeToWebhooks($this->account->fresh());
        $this->assertSame('missing_permission', $this->account->fresh()->metadata['settings']['webhook_status']['reason']);
    }
}
