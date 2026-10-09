<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Services\PostServices;
use App\Support\Http\MetaAppSecretProof;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** social:publish-posts, error messages, and the Graph appsecret_proof middleware. */
class PublishPostsCommandTest extends TestCase
{
    use CreatesConnectionTables;

    private array $services = [];

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
            $t->string('group_id')->nullable();
            $t->string('title')->nullable();
            $t->text('content')->nullable();
            $t->boolean('schedule_mode')->default(false);
            $t->timestamp('schedule_at')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->string('post_id')->nullable();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->string('media_url')->nullable();
            $t->string('media_type')->nullable();
            $t->timestamps();
        });

        foreach ([
            PostServices\MetaPostService::class, PostServices\InstagramPostService::class, PostServices\GooglePostService::class,
            PostServices\YoutubePostService::class, PostServices\TiktokPostService::class, PostServices\XPostService::class,
            PostServices\LinkedInPostService::class, PostServices\WhatsAppPostService::class, PostServices\ThreadsPostService::class,
            PostServices\PinterestPostService::class,
        ] as $class) {
            $this->services[$class] = Mockery::mock($class);
            $this->app->instance($class, $this->services[$class]);
        }
    }

    private function makePost(string $platform, string $status, array $extra = []): Post
    {
        return Post::forceCreate(array_merge(['platform' => $platform, 'user_id' => 1, 'content' => 'Hi', 'status' => $status], $extra));
    }

    public function test_only_pending_posts_are_published(): void
    {
        $pending = $this->makePost('facebook', 'pending');
        $this->makePost('facebook', 'failed');
        $this->makePost('facebook', 'draft');
        $this->makePost('facebook', 'completed');

        $this->services[PostServices\MetaPostService::class]->shouldReceive('publishPost')->once()
            ->withArgs(fn ($p) => $p->id === $pending->id)->andReturn(['success' => true]);

        $this->artisan('social:publish-posts')->expectsOutputToContain("Published Post #{$pending->id}")->assertSuccessful();
    }

    public function test_the_saved_reason_is_shown_when_a_service_returns_none(): void
    {
        $post = $this->makePost('facebook', 'pending');
        $this->services[PostServices\MetaPostService::class]->shouldReceive('publishPost')->andReturnUsing(function ($p) {
            $p->update(['status' => 'failed', 'error_message' => 'API calls from the server require an appsecret_proof argument']);

            return ['success' => false];
        });

        $this->artisan('social:publish-posts')->expectsOutputToContain("Post #{$post->id} failed: API calls from the server require an appsecret_proof argument")->assertSuccessful();
    }

    public function test_a_crash_marks_the_post_failed_with_a_short_reason(): void
    {
        $post = $this->makePost('youtube', 'pending');
        $this->services[PostServices\YoutubePostService::class]->shouldReceive('publishPost')
            ->andThrow(new \RuntimeException("YouTube session creation failed: {\n \"error\": {\n  \"code\": 400,\n  \"message\": \"Media type 'image/png' is not supported. \"\n }\n}\n"));

        $this->artisan('social:publish-posts')->assertSuccessful();

        $this->assertSame('failed', $post->fresh()->status);
        $this->assertSame("YouTube session creation failed: Media type 'image/png' is not supported.", $post->fresh()->error_message);
    }

    public function test_error_messages_are_capped(): void
    {
        $post = $this->makePost('x', 'failed', ['error_message' => str_repeat('a', 5000)]);

        $this->assertLessThanOrEqual(1003, mb_strlen($post->fresh()->error_message));
    }

    public function test_graph_calls_get_appsecret_proof(): void
    {
        Settings::set('posts.facebook.client_secret', 'app-secret');
        Http::fake();

        Http::get('https://graph.facebook.com/v25.0/123/feed', ['access_token' => 'TOK']);
        Http::asForm()->post('https://graph.facebook.com/v25.0/123/photos', ['access_token' => 'TOK2']);
        Http::withToken('TOK3')->post('https://graph.facebook.com/v25.0/123/media', ['image_url' => 'x']);
        Http::get('https://graph.facebook.com/v25.0/me', ['access_token' => 'TOK', 'appsecret_proof' => 'mine']);
        Http::get('https://graph.instagram.com/me', ['access_token' => 'TOK']);

        $proofs = [];
        Http::assertSent(function ($r) use (&$proofs) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $proofs[] = $q['appsecret_proof'] ?? null;

            return true;
        });

        $this->assertSame([
            hash_hmac('sha256', 'TOK', 'app-secret'),
            hash_hmac('sha256', 'TOK2', 'app-secret'),
            hash_hmac('sha256', 'TOK3', 'app-secret'),
            'mine',
            null,
        ], $proofs);
    }
}
