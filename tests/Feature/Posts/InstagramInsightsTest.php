<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PostServices\InstagramPostService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * Instagram post insights on posts/{id}/preview/instagram:
 * GET /{media}?fields=like_count,comments_count and GET /{media}/insights
 * (views, reach, saved, shares) - shapes as in Meta's docs.
 */
class InstagramInsightsTest extends TestCase
{
    use CreatesConnectionTables;

    private const FB = 'https://graph.facebook.com/v25.0/';
    private const MEDIA = '18502583398100703';

    private SocialAccount $account;
    private Post $post;

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
            $t->string('status')->nullable();
            foreach (['likes', 'comments', 'shares', 'saves', 'views', 'impressions', 'reach', 'clicks'] as $column) {
                $t->integer($column)->default(0);
            }
            $t->json('platform_metadata')->nullable();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.facebook.client_secret', 'meta-secret');
        $user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->account = SocialAccount::create(['user_id' => $user->id, 'platform' => 'instagram', 'platform_account_id' => '17841440159861121', 'username' => 'social.eaz', 'access_token' => 'page-token', 'expires_at' => now()->addDays(30), 'is_token_valid' => true]);
        $this->post = Post::forceCreate(['platform' => 'instagram', 'user_id' => $user->id, 'social_account_id' => $this->account->id, 'post_id' => self::MEDIA, 'status' => 'completed', 'likes' => 1]);
    }

    private function insight(string $name, int $value): array
    {
        return ['name' => $name, 'period' => 'lifetime', 'values' => [['value' => $value]], 'title' => ucfirst($name), 'id' => self::MEDIA . "/insights/{$name}/lifetime"];
    }

    public function test_counts_and_insights_are_saved(): void
    {
        Http::fake([
            self::FB . self::MEDIA . '/insights*' => Http::response(['data' => [$this->insight('views', 1250), $this->insight('reach', 830), $this->insight('saved', 14), $this->insight('shares', 6)]]),
            self::FB . self::MEDIA . '*' => Http::response(['like_count' => 42, 'comments_count' => 5, 'media_product_type' => 'FEED', 'id' => self::MEDIA]),
        ]);

        $this->assertTrue(app(InstagramPostService::class)->syncEngagement($this->post));

        $p = $this->post->fresh();
        $this->assertSame([42, 5, 1250, 1250, 830, 14, 6], [$p->likes, $p->comments, $p->views, $p->impressions, $p->reach, $p->saves, $p->shares]);
        $this->assertNotNull($p->platform_metadata['engagement_synced_at']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/insights') && $r['metric'] === 'views,reach,saved,shares' && $r['access_token'] === 'page-token' && $r['appsecret_proof'] === hash_hmac('sha256', 'page-token', 'meta-secret'));
    }

    public function test_a_refused_metric_does_not_lose_the_others(): void
    {
        Http::fake(function (HttpRequest $r) {
            if (! str_contains($r->url(), '/insights')) {
                return Http::response(['like_count' => 3, 'comments_count' => 0, 'media_product_type' => 'REELS']);
            }

            return str_contains($r['metric'], 'saved') && $r['metric'] !== 'saved'
                ? Http::response(['error' => ['message' => '(#100) metric[0] must be one of the following values: ...', 'code' => 100]], 400)
                : Http::response(['data' => [$this->insight($r['metric'] === 'saved' ? 'saved' : $r['metric'], 9)]]);
        });

        app(InstagramPostService::class)->syncEngagement($this->post);

        $p = $this->post->fresh();
        $this->assertSame([3, 9, 9, 9, 9], [$p->likes, $p->views, $p->reach, $p->saves, $p->shares]);
    }

    public function test_stories_skip_saves_and_instagram_login_uses_its_own_host(): void
    {
        $this->account->update(['metadata' => ['settings' => ['auth_type' => 'instagram_login']]]);
        Http::fake([
            'graph.instagram.com/v20.0/' . self::MEDIA . '/insights*' => Http::response(['data' => [$this->insight('views', 70), $this->insight('reach', 50), $this->insight('shares', 1)]]),
            'graph.instagram.com/v20.0/' . self::MEDIA . '*' => Http::response(['like_count' => 0, 'comments_count' => 0, 'media_product_type' => 'STORY']),
        ]);

        app(InstagramPostService::class)->syncEngagement($this->post->fresh());

        $this->assertSame([70, 50], [$this->post->fresh()->views, $this->post->fresh()->reach]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/insights') && $r['metric'] === 'views,reach,shares' && ! isset($r['appsecret_proof']));
    }

    public function test_an_api_error_leaves_the_post_untouched(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Unsupported get request.', 'code' => 100]], 400)]);

        $this->assertFalse(app(InstagramPostService::class)->syncEngagement($this->post));
        $this->assertSame([1, 'completed'], [$this->post->fresh()->likes, $this->post->fresh()->status]);
    }
}
