<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaAssetService;
use App\Services\PackageAccessService;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class McpMediaUploadTest extends TestCase
{
    private function signIn(array $abilities = ['mcp:media:write'], bool $active = true): User
    {
        $user = new User;
        $user->forceFill(['id' => 123, 'is_active' => $active]);
        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    private function allowMedia(User $user): void
    {
        $access = Mockery::mock(PackageAccessService::class);
        $access->shouldReceive('authorize')->once()->with($user, 'media', true);
        $this->app->instance(PackageAccessService::class, $access);
    }

    public function test_anonymous_upload_is_rejected(): void
    {
        $this->postJson('/api/mcp/v1/media-gallery')->assertUnauthorized();
    }

    public function test_read_only_tokens_and_inactive_users_cannot_upload(): void
    {
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldNotReceive('store');
        $this->app->instance(MediaAssetService::class, $media);
        $this->signIn(['mcp:read']);
        $this->postJson('/api/mcp/v1/media-gallery')->assertForbidden();
        $this->signIn(['mcp:media:write'], false);
        $this->postJson('/api/mcp/v1/media-gallery')->assertForbidden();
    }

    public function test_media_subscription_denial_prevents_storage(): void
    {
        $user = $this->signIn();
        $access = Mockery::mock(PackageAccessService::class);
        $access->shouldReceive('authorize')->once()->with($user, 'media', true)->andReturnUsing(fn () => abort(403));
        $this->app->instance(PackageAccessService::class, $access);
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldNotReceive('store');
        $this->app->instance(MediaAssetService::class, $media);
        $this->postJson('/api/mcp/v1/media-gallery')->assertForbidden();
    }

    public function test_invalid_file_is_rejected_before_storage(): void
    {
        $this->allowMedia($this->signIn());
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldNotReceive('store');
        $this->app->instance(MediaAssetService::class, $media);
        $temporary = tmpfile();
        fwrite($temporary, 'not an image');
        try {
            $this->postJson('/api/mcp/v1/media-gallery', [
                'file' => new UploadedFile(stream_get_meta_data($temporary)['uri'], 'image.png', null, null, true),
            ])->assertUnprocessable()->assertJsonValidationErrors('file');
        } finally {
            fclose($temporary);
        }
    }

    public function test_upload_uses_authenticated_owner_and_filters_response(): void
    {
        $this->allowMedia($this->signIn());
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $asset = new MediaAsset;
        $asset->forceFill(['id' => 8, 'url' => 'https://cdn.example/image.png', 'user_id' => 123, 'disk' => 'r2', 'path' => 'internal/path', 'type' => 'image']);
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldReceive('store')->once()->with(Mockery::type(UploadedFile::class), 123)->andReturn($asset);
        $this->app->instance(MediaAssetService::class, $media);
        $response = $this->postJson('/api/mcp/v1/media-gallery', [
            'file' => UploadedFile::fake()->createWithContent('image.png', $bytes),
            'user_id' => 999,
        ])->assertCreated()->assertJsonPath('media.id', 8);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('user_id', $response->json('media'));
        $this->assertArrayNotHasKey('path', $response->json('media'));
    }
    public function test_video_upload_uses_gallery_service_and_returns_video_metadata(): void
    {
        $this->allowMedia($this->signIn());
        $asset = new MediaAsset;
        $asset->forceFill(['id' => 9, 'url' => 'https://cdn.example/clip.mp4', 'type' => 'video', 'duration' => 7, 'video_codec' => 'h264']);
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldReceive('store')->once()->with(Mockery::type(UploadedFile::class), 123)->andReturn($asset);
        $this->app->instance(MediaAssetService::class, $media);
        $temporary = tmpfile();
        fwrite($temporary, pack('N', 24).'ftypisom'.pack('N', 0).'isommp42');
        try {
            $this->postJson('/api/mcp/v1/media-gallery', [
                'file' => new UploadedFile(stream_get_meta_data($temporary)['uri'], 'clip.mp4', null, null, true),
            ])->assertCreated()->assertJsonPath('media.type', 'video')->assertJsonPath('media.duration', 7);
        } finally {
            fclose($temporary);
        }
    }

    public function test_video_size_limit_is_enforced_before_storage(): void
    {
        $this->allowMedia($this->signIn());
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldNotReceive('store');
        $this->app->instance(MediaAssetService::class, $media);
        $this->postJson('/api/mcp/v1/media-gallery', [
            'file' => UploadedFile::fake()->create('clip.mp4', 51201, 'video/mp4'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_image_size_limit_remains_ten_mebibytes(): void
    {
        $this->allowMedia($this->signIn());
        $media = Mockery::mock(MediaAssetService::class);
        $media->shouldNotReceive('store');
        $this->app->instance(MediaAssetService::class, $media);
        $this->postJson('/api/mcp/v1/media-gallery', [
            'file' => UploadedFile::fake()->create('image.png', 10241, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

}
