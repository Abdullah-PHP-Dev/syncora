<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PostServices\XPostService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * X answers an over-long post with a bare 403 "You are not permitted to
 * perform this action." - XPostService checks the length against the
 * account's limit (280 without X Premium) before posting.
 */
class XPostLengthTest extends TestCase
{
    use CreatesConnectionTables;

    private SocialAccount $account;

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
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });
        Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->string('media_type')->nullable();
            $t->text('media_url')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.x.base_url', 'https://api.x.com/2/');
        $user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->account = SocialAccount::create(['user_id' => $user->id, 'platform' => 'x', 'platform_account_id' => 'X1', 'name' => 'Socialeaz', 'username' => 'Socialeaz', 'access_token' => 'x-token', 'refresh_token' => 'x-refresh', 'expires_at' => now()->addHour(), 'is_token_valid' => true]);
    }

    private function makePost(string $content): Post
    {
        return Post::forceCreate(['platform' => 'x', 'user_id' => $this->account->user_id, 'social_account_id' => $this->account->id, 'content' => $content, 'status' => 'pending'])->fresh(['media', 'socialAccount']);
    }

    public function test_a_long_post_without_premium_fails_with_a_clear_reason(): void
    {
        Http::fake(['api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'X1', 'subscription_type' => 'None']])]);
        $post = $this->makePost(str_repeat('a', 998));

        $result = app(XPostService::class)->publishPost($post);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $post->fresh()->status);
        $this->assertStringContainsString('998 characters - X allows 280', $post->fresh()->error_message);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/2/tweets'));
        $this->assertSame('None', $this->account->fresh()->metadata['x_subscription_type']);
    }

    public function test_premium_accounts_can_post_long_text(): void
    {
        Http::fake([
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'X1', 'subscription_type' => 'Premium']]),
            'api.x.com/2/tweets' => Http::response(['data' => ['id' => '1888']], 201),
        ]);
        $post = $this->makePost(str_repeat('a', 998));

        $result = app(XPostService::class)->publishPost($post);

        $this->assertTrue($result['success']);
        $this->assertSame(['completed', '1888'], [$post->fresh()->status, $post->fresh()->post_id]);
    }

    public function test_the_subscription_is_remembered_for_a_day(): void
    {
        Http::fake([
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'X1', 'subscription_type' => 'None']]),
            'api.x.com/2/tweets' => Http::response(['data' => ['id' => '1']], 201),
        ]);

        app(XPostService::class)->publishPost($this->makePost('Short post'));
        app(XPostService::class)->publishPost($this->makePost('Another short post'));

        Http::assertSentCount(3); // one lookup + two posts
    }

    public function test_when_the_lookup_fails_x_decides_and_its_403_is_explained(): void
    {
        Http::fake([
            'api.x.com/2/users/me*' => Http::response(['title' => 'Forbidden'], 403),
            'api.x.com/2/tweets' => Http::response(['title' => 'Forbidden', 'detail' => 'You are not permitted to perform this action.', 'status' => 403], 403),
        ]);
        $post = $this->makePost(str_repeat('a', 500));

        app(XPostService::class)->publishPost($post);

        $this->assertStringContainsString('500 characters - accounts without X Premium can post up to 280', $post->fresh()->error_message);
        $this->assertStringContainsString('You are not permitted to perform this action.', $post->fresh()->error_message);
    }

    public function test_length_is_counted_like_x(): void
    {
        $this->assertSame(5, XPostService::weightedLength('Hello'));
        $this->assertSame(5, XPostService::weightedLength('مرحبا')); // Arabic counts 1 each
        $this->assertSame(4, XPostService::weightedLength('日本')); // CJK counts 2 each
        $this->assertSame(2, XPostService::weightedLength('👋'));
        $this->assertSame(23 + 1 + 5, XPostService::weightedLength('https://example.com/a/very/long/path?with=query Hello')); // links count 23
    }
}
