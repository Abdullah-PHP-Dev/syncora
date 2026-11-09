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

class McpFacebookTest extends TestCase
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
            $t->string('name')->default('My Page'); $t->string('username')->nullable();
            $t->boolean('is_token_valid')->default(true); $t->boolean('has_posting_permission')->default(true);
            $t->json('enabled_capabilities')->nullable(); $t->integer('social_connection_id')->nullable();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('social_account_id'); $t->string('platform');
            foreach (['title', 'content', 'visibility', 'status', 'post_id', 'post_url'] as $f) { $t->text($f)->nullable(); }
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
            foreach (['post_id', 'media_asset_id', 'user_id', 'social_account_id', 'file_size', 'width', 'height', 'duration_seconds'] as $f) { $t->integer($f)->nullable(); }
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

    private function input(int $page): array
    {
        return ['social_account_id' => $page, 'content' => 'Hello Facebook', 'idempotency_key' => 'a1111111-1111-4111-8111-111111111111'];
    }

    public function test_creates_one_pending_post_and_replays_identical_requests(): void
    {
        $this->signIn(); $input = $this->input($this->page());
        $id = $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertStatus(202)->assertJsonPath('post.published', false)->assertJsonPath('replayed', false)->json('post.id');
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('post.id', $id);
        $this->assertDatabaseCount('posts', 1); $this->assertDatabaseCount('mcp_post_requests', 1);
        $this->assertDatabaseHas('posts', ['id' => $id, 'status' => 'pending', 'user_id' => 123, 'schedule_mode' => 0]);
        Http::assertNothingSent();
    }

    public function test_conflicting_and_deleted_receipts_never_create_another_post(): void
    {
        $this->signIn(); $input = $this->input($this->page());
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertStatus(202);
        $this->postJson('/api/mcp/v1/facebook/posts', array_replace($input, ['content' => 'changed']))->assertConflict();
        $this->assertDatabaseCount('posts', 1);
        DB::table('posts')->delete();
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertConflict();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_other_users_pages_invalid_tokens_and_disabled_capabilities_are_rejected(): void
    {
        $this->signIn();
        foreach ([['user_id' => 999], ['platform' => 'instagram'], ['is_token_valid' => false], ['has_posting_permission' => false], ['enabled_capabilities' => '[]']] as $change) {
            $this->postJson('/api/mcp/v1/facebook/posts', $this->input($this->page($change)))->assertNotFound();
        }
        $this->assertDatabaseCount('posts', 0); $this->assertDatabaseCount('mcp_post_requests', 0);
    }

    public function test_page_discovery_filters_unusable_connections(): void
    {
        $this->signIn(); $id = $this->page();
        DB::table('social_connections')->insert(['id' => 1, 'status' => 'revoked']);
        $this->page(['social_connection_id' => 1]); $this->page(['user_id' => 999]); $this->page(['has_posting_permission' => false]);
        $this->getJson('/api/mcp/v1/facebook/pages')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    }

    public function test_media_is_owned_compatible_and_reused_without_uploading(): void
    {
        $this->signIn(); $input = $this->input($this->page());
        $media = DB::table('media_assets')->insertGetId(['user_id' => 123, 'type' => 'video', 'extension' => 'mp4', 'url' => 'https://cdn.example/clip.mp4', 'path' => 'gallery/clip.mp4', 'compatible_platforms' => '["facebook"]']);
        $this->postJson('/api/mcp/v1/facebook/posts', $input + ['media_asset_id' => $media])->assertStatus(202);
        $this->assertDatabaseHas('post_media', ['media_asset_id' => $media, 'media_type' => 'video', 'media_url' => 'https://cdn.example/clip.mp4']);
        Http::assertNothingSent();
    }

    public function test_foreign_deleted_and_incompatible_media_are_rejected(): void
    {
        $this->signIn(); $input = $this->input($this->page());
        foreach ([['user_id' => 999], ['deleted_at' => now()], ['compatible_platforms' => '[]']] as $changes) {
            $media = DB::table('media_assets')->insertGetId($changes + ['user_id' => 123, 'type' => 'image', 'extension' => 'png', 'compatible_platforms' => '["facebook"]']);
            $this->postJson('/api/mcp/v1/facebook/posts', $input + ['media_asset_id' => $media])->assertStatus(isset($changes['compatible_platforms']) ? 422 : 404);
        }
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_status_is_owned_and_only_confirms_completed_provider_posts(): void
    {
        $this->signIn(); $input = $this->input($this->page());
        $id = $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertStatus(202)->json('post.id');
        $this->getJson('/api/mcp/v1/facebook/posts/'.$id)->assertOk()->assertJsonPath('post.published', false);
        DB::table('posts')->where('id', $id)->update(['status' => 'completed', 'post_id' => 'page_post']);
        $this->getJson('/api/mcp/v1/facebook/posts/'.$id)->assertOk()->assertJsonPath('post.published', true);
        DB::table('posts')->where('id', $id)->update(['user_id' => 999]);
        $this->getJson('/api/mcp/v1/facebook/posts/'.$id)->assertNotFound();
    }

    public function test_write_permission_subscription_and_valid_content_are_required(): void
    {
        $input = $this->input($this->page());
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertUnauthorized();
        $this->signIn(['mcp:read']);
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertForbidden();
        $this->signIn(['mcp:posts:write'], false);
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertForbidden();
        $this->signIn();
        $this->postJson('/api/mcp/v1/facebook/posts', array_replace($input, ['content' => '']))->assertUnprocessable();
        $usage = Mockery::mock(PackageUsageService::class);
        $usage->shouldReceive('persist')->once()->andThrow(ValidationException::withMessages(['package' => 'Quota exceeded']));
        $this->app->instance(PackageUsageService::class, $usage);
        $this->postJson('/api/mcp/v1/facebook/posts', $input)->assertUnprocessable();
        $this->assertDatabaseCount('posts', 0); $this->assertDatabaseCount('mcp_post_requests', 0);
    }
}
