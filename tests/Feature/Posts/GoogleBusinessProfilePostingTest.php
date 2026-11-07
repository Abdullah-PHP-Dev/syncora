<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\GoogleSuite\BusinessProfileService;
use App\Services\PostServices\GooglePostService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Google Business Profile local posts: what the composer stores, what Google receives, and how results are recorded. */
class GoogleBusinessProfilePostingTest extends TestCase
{
    use CreatesConnectionTables;

    private const BASE = 'https://mybusiness.googleapis.com/v4/';

    private User $user;

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
            $t->unsignedBigInteger('post_category_id')->nullable();
            $t->string('group_id')->nullable();
            $t->string('page_id')->nullable();
            $t->string('post_id')->nullable();
            $t->string('title')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->string('post_url')->nullable();
            $t->string('visibility')->nullable();
            $t->boolean('schedule_mode')->default(false);
            $t->timestamp('schedule_at')->nullable();
            $t->boolean('expiry_mode')->default(false);
            $t->timestamp('expiry_at')->nullable();
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->unsignedBigInteger('post_category_id')->nullable();
            $t->string('visibility')->nullable();
            $t->string('file_name')->nullable();
            $t->string('media_type')->nullable();
            $t->text('media_url')->nullable();
            $t->integer('file_size')->nullable();
            $t->integer('width')->nullable();
            $t->integer('height')->nullable();
            $t->decimal('duration_seconds')->nullable();
            $t->text('thumbnail_url')->nullable();
            $t->string('alt_text')->nullable();
            $t->timestamps();
        });
        Schema::create('post_comments', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->string('comment_id')->nullable();
            $t->unsignedBigInteger('parent_comment_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('content')->nullable();
            $t->string('sender_type')->nullable();
            $t->string('sender_name')->nullable();
            $t->string('user_name')->nullable();
            $t->boolean('is_read')->default(false);
            $t->boolean('is_reply')->default(false);
            $t->string('status')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.google.base_url', self::BASE);
        Settings::set('posts.google.client_id', 'client-1');
        Settings::set('posts.google.client_secret', 'secret-1');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function location(): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'google', 'account_type' => 'location', 'name' => 'Riyadh Store',
            'platform_account_id' => '555', 'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour(),
            'is_token_valid' => true, 'has_posting_permission' => true, 'metadata' => ['parent_account_id' => '111'],
        ]);
    }

    private function makePost(SocialAccount $location, array $media = [], array $overrides = []): Post
    {
        $post = Post::forceCreate($overrides + [
            'platform' => 'google', 'user_id' => $this->user->id, 'social_account_id' => $location->id,
            'title' => 'Seller', 'content' => 'Weekend offer: 20% off all coffee.', 'status' => 'pending',
        ]);
        foreach ($media as [$type, $url]) {
            DB::table('post_media')->insert(['post_id' => $post->id, 'media_type' => $type, 'media_url' => $url]);
        }

        return $post->fresh(['media', 'socialAccount']);
    }

    public function test_a_post_is_published_as_a_standard_update_with_its_photo_and_link(): void
    {
        Http::fake([self::BASE . 'accounts/111/locations/555/localPosts' => Http::response([
            'name' => 'accounts/111/locations/555/localPosts/987', 'state' => 'LIVE', 'searchUrl' => 'https://local.google.com/place?id=1&use=posts&lpsid=987',
        ])]);
        $post = $this->makePost($this->location(), [['image', 'https://cdn.socialeaz.com/a.jpg'], ['image', 'https://cdn.socialeaz.com/b.png']]);

        $this->assertTrue(app(GooglePostService::class)->publishPost($post)['success']);

        Http::assertSent(fn (HttpRequest $r) => $r['topicType'] === 'STANDARD'
            && $r['languageCode'] === 'en'
            && $r['summary'] === 'Weekend offer: 20% off all coffee.'
            // A list of media items, one photo.
            && $r['media'] === [['mediaFormat' => 'PHOTO', 'sourceUrl' => 'https://cdn.socialeaz.com/a.jpg']]
            && ! isset($r['topic_type']));
        $post->refresh();
        $this->assertSame(['completed', '987', 'https://local.google.com/place?id=1&use=posts&lpsid=987'], [$post->status, $post->post_id, $post->getRawOriginal('post_url')]);
    }

    public function test_arabic_posts_are_sent_as_arabic(): void
    {
        Http::fake(['*' => Http::response(['name' => 'accounts/111/locations/555/localPosts/1'])]);

        app(GooglePostService::class)->publishPost($this->makePost($this->location(), [], ['content' => 'عرض نهاية الأسبوع']));

        Http::assertSent(fn (HttpRequest $r) => $r['languageCode'] === 'ar');
    }

    public function test_a_refused_post_records_googles_reason(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Request contains an invalid argument.',
            'details' => [['errorDetails' => [['message' => 'Phone numbers are not allowed in the summary.']]]]]], 400)]);
        $post = $this->makePost($this->location());

        $this->assertFalse(app(GooglePostService::class)->publishPost($post)['success']);
        $this->assertSame(['failed', 'Phone numbers are not allowed in the summary.'], [$post->fresh()->status, $post->fresh()->error_message]);
    }

    public function test_an_expired_token_that_cannot_refresh_stops_publishing(): void
    {
        $location = $this->location();
        $location->update(['expires_at' => now()->subMinute()]);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
        $post = $this->makePost($location);

        app(GooglePostService::class)->publishPost($post);

        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'localPosts'));
        $this->assertSame('failed', $post->fresh()->status);
    }

    public function test_videos_and_unsupported_images_are_refused_before_anything_is_stored(): void
    {
        Storage::fake('r2');
        $location = $this->location();

        foreach (['clip.mp4', 'photo.webp'] as $name) {
            $result = app(GooglePostService::class)->store(['content' => 'Hi', 'category_id' => 1, 'media' => [UploadedFile::fake()->create($name, 10)]], collect([$location]));
            $this->assertFalse($result['success']);
            $this->assertStringContainsString($name, $result['message']);
        }
        $this->assertSame(0, Post::count());

        $result = app(GooglePostService::class)->store(['content' => 'Hi', 'category_id' => 1, 'media' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]], collect([$location]));
        $this->assertTrue($result['success']);
        $this->assertSame(1, DB::table('post_media')->count());
    }

    public function test_posts_cannot_be_commented_on_but_review_replies_are_recorded(): void
    {
        $location = $this->location();
        $post = $this->makePost($location);
        Http::fake([self::BASE . '*' => Http::response(['comment' => 'Thank you!', 'updateTime' => '2026-11-07T10:00:00Z'])]);

        $result = app(GooglePostService::class)->publishComment(['body' => 'hi'], $post, 'comment');
        $this->assertFalse($result['success']);
        Http::assertNothingSent();

        $review = PostComment::forceCreate(['platform' => 'google', 'comment_id' => 'r1', 'social_account_id' => $location->id, 'post_id' => $post->id, 'content' => 'Great!', 'user_id' => $this->user->id]);
        $result = app(GooglePostService::class)->publishComment(['body' => 'Thank you!'], $review);

        $this->assertTrue($result['success']);
        $this->assertSame('r1:reply', $result['data']->comment_id);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_ends_with($r->url(), 'accounts/111/locations/555/reviews/r1/reply'));
    }

    public function test_the_business_profile_page_lists_recent_posts_with_their_status(): void
    {
        $location = $this->location();
        $this->makePost($location, [['image', 'https://cdn.socialeaz.com/a.jpg']], ['status' => 'completed', 'post_url' => 'https://local.google.com/p/1']);
        $this->makePost($location, [], ['status' => 'failed', 'error_message' => 'Phone numbers are not allowed.']);

        $posts = BusinessProfileService::recentPosts($location);

        $this->assertSame(['failed', 'live'], array_column($posts, 'status'));
        $this->assertSame('Phone numbers are not allowed.', $posts[0]['error']);
        $this->assertSame('https://local.google.com/p/1', $posts[1]['url']);
        $this->assertSame('https://cdn.socialeaz.com/a.jpg', $posts[1]['thumbnail']);
    }
}
