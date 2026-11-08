<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PackageAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class McpListMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id(); $table->integer('user_id');
            foreach (['type', 'url', 'thumbnail_url', 'original_name', 'mime_type', 'video_codec', 'compatible_platforms', 'disk', 'path'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['size', 'width', 'height'] as $field) { $table->integer($field)->nullable(); }
            $table->float('duration')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
    }

    private function signIn(array $abilities = ['mcp:read'], bool $active = true, bool $allow = true): void
    {
        $user = new User;
        $user->forceFill(['id' => 123, 'is_active' => $active]);
        Sanctum::actingAs($user, $abilities);
        $access = Mockery::mock(PackageAccessService::class);
        if ($allow) {
            $access->shouldReceive('authorize')->with($user, 'media', false)->andReturnNull();
        } else {
            $access->shouldReceive('authorize')->with($user, 'media', false)->andReturnUsing(fn () => abort(403));
        }
        $this->app->instance(PackageAccessService::class, $access);
    }

    private function asset(array $attributes = []): int
    {
        return DB::table('media_assets')->insertGetId($attributes + [
            'user_id' => 123, 'type' => 'image', 'original_name' => 'photo.png',
            'url' => 'https://cdn.example/photo.png', 'mime_type' => 'image/png',
            'size' => 100, 'compatible_platforms' => '["instagram"]', 'disk' => 'r2', 'path' => 'internal/path',
            'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00',
        ]);
    }

    public function test_listing_scopes_ownership_hides_deleted_assets_and_storage_fields(): void
    {
        $this->signIn();
        $id = $this->asset();
        $this->asset(['user_id' => 999]);
        $this->asset(['deleted_at' => now()]);
        $response = $this->getJson('/api/mcp/v1/media-gallery?user_id=999')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.compatible_platforms.0', 'instagram');
        $this->assertCount(1, $response->json('data'));
        $this->assertArrayNotHasKey('path', $response->json('data.0'));
        $this->assertArrayNotHasKey('disk', $response->json('data.0'));
        $this->assertArrayNotHasKey('user_id', $response->json('data.0'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_filename_search_treats_wildcards_as_literal_and_filters_type(): void
    {
        $this->signIn();
        $id = $this->asset(['type' => 'video', 'original_name' => 'launch_100%!.mp4', 'mime_type' => 'video/mp4']);
        $this->asset(['type' => 'video', 'original_name' => 'launchX100zzz.mp4']);
        $this->asset(['type' => 'image', 'original_name' => 'launch_100%!.png']);
        $this->getJson('/api/mcp/v1/media-gallery?'.http_build_query(['q' => '_100%!', 'type' => 'video']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    }

    public function test_pagination_is_stable_and_sorting_works(): void
    {
        $this->signIn();
        $first = $this->asset(['size' => 10, 'original_name' => 'a.png']);
        $second = $this->asset(['size' => 20, 'original_name' => 'b.png']);
        $this->getJson('/api/mcp/v1/media-gallery?per_page=1')->assertOk()
            ->assertJsonPath('data.0.id', $second)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/mcp/v1/media-gallery?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $first);
        $this->getJson('/api/mcp/v1/media-gallery?sort=name')->assertOk()->assertJsonPath('data.0.id', $first);
        $this->getJson('/api/mcp/v1/media-gallery?sort=largest')->assertOk()->assertJsonPath('data.0.id', $second);
    }

    public function test_empty_gallery_has_zero_total(): void
    {
        $this->signIn();
        $this->getJson('/api/mcp/v1/media-gallery')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_authentication_ability_active_user_and_package_are_enforced(): void
    {
        $this->getJson('/api/mcp/v1/media-gallery')->assertUnauthorized();
        $this->signIn(['mcp:media:write']);
        $this->getJson('/api/mcp/v1/media-gallery')->assertForbidden();
        $this->signIn(['mcp:read'], false);
        $this->getJson('/api/mcp/v1/media-gallery')->assertForbidden();
        $this->signIn(['mcp:read'], true, false);
        $this->getJson('/api/mcp/v1/media-gallery')->assertForbidden();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->signIn();
        foreach (['per_page=51', 'page=0', 'type=audio', 'sort=invalid'] as $query) {
            $this->getJson('/api/mcp/v1/media-gallery?'.$query)->assertUnprocessable();
        }
    }
}
