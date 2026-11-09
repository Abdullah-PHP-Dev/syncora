<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class McpDynamicPublishingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->boolean('is_active'); });
        DB::table('users')->insert(['id' => 123, 'name' => 'Seller', 'email' => 'seller@example.com', 'is_active' => true]);
        Schema::create('social_connections', function (Blueprint $t) { $t->id(); $t->string('status'); });
        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('platform'); $t->string('platform_account_id')->default('page1');
            $t->json('metadata')->nullable(); $t->string('name')->default('My Page'); $t->string('username')->nullable();
            $t->boolean('is_token_valid')->default(true); $t->boolean('has_posting_permission')->default(true);
            $t->json('enabled_capabilities')->nullable(); $t->integer('social_connection_id')->nullable();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('social_account_id'); $t->string('platform');
            foreach (['group_id', 'title', 'content', 'visibility', 'status', 'post_id', 'post_url'] as $f) { $t->text($f)->nullable(); }
            $t->boolean('schedule_mode'); $t->dateTime('schedule_at')->nullable(); $t->boolean('expiry_mode'); $t->dateTime('expiry_at')->nullable(); $t->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $t) {
            $t->id(); $t->integer('user_id');
            foreach (['type', 'url', 'path', 'original_name', 'extension', 'thumbnail_url'] as $f) { $t->string($f)->nullable(); }
            foreach (['size', 'width', 'height', 'duration'] as $f) { $t->integer($f)->nullable(); }
            $t->json('compatible_platforms')->nullable(); $t->softDeletes();
        });
        Schema::create('post_media', function (Blueprint $t) {
            $t->id();
            foreach (['post_id', 'media_asset_id', 'user_id', 'social_account_id', 'file_size', 'width', 'height', 'duration_seconds', 'sort_order'] as $f) { $t->integer($f)->nullable(); }
            foreach (['platform', 'media_url', 'media_type', 'file_name', 'thumbnail_url', 'alt_text'] as $f) { $t->string($f)->nullable(); }
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_08_180000_create_mcp_post_requests_table.php'))->up();
        $access = Mockery::mock(PackageAccessService::class);
        $access->shouldReceive('authorize')->andReturnNull();
        $this->app->instance(PackageAccessService::class, $access);
        $usage = Mockery::mock(PackageUsageService::class);
        $usage->shouldReceive('persist')->andReturnUsing(fn ($model, $save) => $save());
        $this->app->instance(PackageUsageService::class, $usage);
    }

    private function signIn(array $abilities = ['mcp:read', 'mcp:posts:write'], bool $active = true): void
    {
        $user = User::findOrFail(123); $user->is_active = $active;
        Sanctum::actingAs($user, $abilities);
    }

    private function page(array $changes = []): int
    {
        return DB::table('social_accounts')->insertGetId($changes + ['user_id' => 123, 'platform' => 'facebook']);
    }

    private function input(array $ids): array
    {
        return ['social_account_ids' => $ids, 'content' => 'Hello social media', 'idempotency_key' => 'b1111111-1111-4111-8111-111111111111'];
    }

    private function asset(string $type = 'image', array $changes = []): int
    {
        return DB::table('media_assets')->insertGetId($changes + ['user_id' => 123, 'type' => $type,
            'extension' => $type === 'image' ? 'jpg' : 'mp4', 'url' => 'https://cdn.example/media.'.($type === 'image' ? 'jpg' : 'mp4'),
            'path' => 'gallery/media', 'size' => 100, 'compatible_platforms' => json_encode(array_keys(config('mcp-publishing.platforms')))]);
    }

    public function test_every_registered_platform_creates_a_post_through_the_same_endpoint(): void
    {
        $this->signIn();
        foreach (array_keys(config('mcp-publishing.platforms')) as $platform) {
            $metadata = ['settings' => ['board_id' => 'board1'], 'parent_account_id' => 'google1'];
            $id = $this->page(['platform' => $platform, 'metadata' => json_encode($metadata)]);
            $media = $this->asset($platform === 'youtube' ? 'video' : 'image');
            $input = array_replace($this->input([$id]), ['title' => 'Sample', 'media_asset_ids' => [$media], 'idempotency_key' => (string) \Illuminate\Support\Str::uuid()]);
            $this->postJson('/api/mcp/v1/posts', $input)->assertStatus(202)->assertJsonPath('posts.0.platform', $platform)->assertJsonPath('posts.0.published', false);
        }
        $this->assertDatabaseCount('posts', count(config('mcp-publishing.platforms')));
        Http::assertNothingSent();
    }

    public function test_batch_is_atomic_and_identical_retry_preserves_each_post_id(): void
    {
        $this->signIn();
        $ids = [$this->page(), $this->page(['platform' => 'instagram']), $this->page(['platform' => 'x'])];
        $input = $this->input($ids) + ['media_asset_ids' => [$this->asset()]];
        $first = $this->postJson('/api/mcp/v1/posts', $input)->assertStatus(202)->assertJsonCount(3, 'posts')->json('posts');
        $again = $this->postJson('/api/mcp/v1/posts', array_replace($input, ['social_account_ids' => array_reverse($ids)]))->assertOk()->assertJsonPath('replayed', true)->json('posts');
        $this->assertSame(array_column($first, 'id'), array_column($again, 'id'));
        $this->assertDatabaseCount('posts', 3); $this->assertDatabaseCount('post_media', 3); $this->assertDatabaseCount('mcp_post_requests', 1);
        $this->postJson('/api/mcp/v1/posts', array_replace($input, ['content' => 'Different']))->assertConflict();
        DB::table('posts')->where('id', $first[1]['id'])->delete();
        $this->postJson('/api/mcp/v1/posts', $input)->assertConflict();
        $this->assertDatabaseCount('posts', 2);
    }

    public function test_a_bad_target_or_media_rejects_the_entire_batch(): void
    {
        $this->signIn();
        $facebook = $this->page(); $instagram = $this->page(['platform' => 'instagram']);
        $this->postJson('/api/mcp/v1/posts', $this->input([$facebook, $instagram]))->assertUnprocessable();
        $foreign = $this->page(['user_id' => 999]);
        $this->postJson('/api/mcp/v1/posts', $this->input([$facebook, $foreign]))->assertNotFound();
        $foreignAsset = $this->asset('image', ['user_id' => 999]);
        $this->postJson('/api/mcp/v1/posts', $this->input([$facebook]) + ['media_asset_ids' => [$foreignAsset]])->assertNotFound();
        $this->assertDatabaseCount('posts', 0); $this->assertDatabaseCount('mcp_post_requests', 0);
    }

    public function test_account_discovery_exposes_dynamic_rules_and_no_metadata_secrets(): void
    {
        $this->signIn();
        $this->page(['platform' => 'x', 'metadata' => '{"x_subscription_type":"Premium","access_token":"secret"}']);
        $this->page(['platform' => 'youtube']); $this->page(['platform' => 'whatsapp']); $this->page(['user_id' => 999]);
        $response = $this->getJson('/api/mcp/v1/publishing-accounts')->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.publishing.text_limits.content', 25000)
            ->assertJsonPath('data.1.publishing.requirements.title_required', true)
            ->assertJsonPath('data.2.publishing.supported', false);
        $response->assertDontSee('secret');
        $this->getJson('/api/mcp/v1/publishing-accounts?platform=youtube')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_text_title_attachment_limits_and_platform_setup_are_checked(): void
    {
        $this->signIn();
        $x = $this->page(['platform' => 'x']);
        $this->postJson('/api/mcp/v1/posts', array_replace($this->input([$x]), ['content' => str_repeat('a', 281)]))->assertUnprocessable();
        $youtube = $this->page(['platform' => 'youtube']);
        $this->postJson('/api/mcp/v1/posts', $this->input([$youtube]) + ['media_asset_ids' => [$this->asset('video')]])->assertUnprocessable();
        $pinterest = $this->page(['platform' => 'pinterest']);
        $this->postJson('/api/mcp/v1/posts', $this->input([$pinterest]) + ['media_asset_ids' => [$this->asset()]])->assertUnprocessable();
        $unsupported = $this->page(['platform' => 'whatsapp']);
        $this->postJson('/api/mcp/v1/posts', $this->input([$unsupported]))->assertUnprocessable();
        $this->postJson('/api/mcp/v1/posts', $this->input([$x]) + ['media_asset_ids' => [$this->asset(), $this->asset('video')]])->assertUnprocessable();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_quota_failure_for_second_account_rolls_back_first_account_and_receipt(): void
    {
        $this->signIn(); $calls = 0;
        $usage = Mockery::mock(PackageUsageService::class);
        $usage->shouldReceive('persist')->twice()->andReturnUsing(function ($model, $save) use (&$calls) {
            if (++$calls === 2) { throw ValidationException::withMessages(['package' => 'Quota exceeded']); }
            return $save();
        });
        $this->app->instance(PackageUsageService::class, $usage);
        $this->postJson('/api/mcp/v1/posts', $this->input([$this->page(), $this->page(['platform' => 'x'])]))->assertUnprocessable();
        $this->assertDatabaseCount('posts', 0); $this->assertDatabaseCount('mcp_post_requests', 0);
    }

    public function test_status_distinguishes_tiktok_processing_and_protects_ownership(): void
    {
        $this->signIn(); $id = $this->page(['platform' => 'tiktok']);
        $post = $this->postJson('/api/mcp/v1/posts', $this->input([$id]) + ['media_asset_ids' => [$this->asset('video')]])->assertStatus(202)->json('posts.0.id');
        DB::table('posts')->where('id', $post)->update(['status' => 'completed', 'post_id' => 'v_pub_url~tracking']);
        $this->getJson('/api/mcp/v1/posts/'.$post)->assertOk()->assertJsonPath('post.published', false)->assertJsonPath('post.provider_processing', true);
        DB::table('posts')->where('id', $post)->update(['post_id' => '123456', 'post_url' => 'https://www.tiktok.com/@seller/video/123456']);
        $this->getJson('/api/mcp/v1/posts/'.$post)->assertOk()->assertJsonPath('post.published', true);
        DB::table('posts')->where('id', $post)->update(['user_id' => 999]);
        $this->getJson('/api/mcp/v1/posts/'.$post)->assertNotFound();
    }

    public function test_authentication_permissions_and_duplicate_targets_are_enforced(): void
    {
        $input = $this->input([$this->page()]);
        $this->postJson('/api/mcp/v1/posts', $input)->assertUnauthorized();
        $this->signIn(['mcp:read']);
        $this->postJson('/api/mcp/v1/posts', $input)->assertForbidden();
        $this->signIn(['mcp:posts:write'], false);
        $this->postJson('/api/mcp/v1/posts', $input)->assertForbidden();
        $this->signIn();
        $this->postJson('/api/mcp/v1/posts', array_replace($input, ['social_account_ids' => [$input['social_account_ids'][0], $input['social_account_ids'][0]]]))->assertUnprocessable();
    }
}
