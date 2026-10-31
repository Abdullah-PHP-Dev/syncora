<?php

namespace App\Services\PostServices;

use App\Models\Post;
use App\Models\PostMedia;
use App\Services\MediaAssetService;
use App\Support\PostCapabilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Edit and delete for the posts listing and preview. Posts that aren't on
 * the platform yet (scheduled, draft, failed) change only here; published
 * posts change on the platform first, and only where PostCapabilities says
 * its API allows it.
 */
class PostManager
{
    private const SERVICES = [
        'facebook' => MetaPostService::class,
        'instagram' => InstagramPostService::class,
        'google' => GooglePostService::class,
        'youtube' => YoutubePostService::class,
        'tiktok' => TiktokPostService::class,
        'x' => XPostService::class,
        'linkedin' => LinkedInPostService::class,
        'threads' => ThreadsPostService::class,
        'pinterest' => PinterestPostService::class,
        'whatsapp' => WhatsAppPostService::class,
    ];

    /** @return array{success: bool, message: string, remote?: bool} */
    public function delete(Post $post): array
    {
        $caps = PostCapabilities::for($post);
        if (!$caps['can_delete']) {
            return ['success' => false, 'message' => __('posts_listing.caps.' . ($caps['reason'] ?? 'platform_read_only'))];
        }

        if ($caps['delete_remote']) {
            $result = $this->onPlatform($post, fn ($service) => $service->destroy($post));
            // 404: already removed on the platform - only the local copy is left.
            if (!($result['success'] ?? false) && (int) ($result['status'] ?? 0) !== 404) {
                return ['success' => false, 'message' => __('posts_listing.manage.delete_failed_platform', ['reason' => $this->reason($result)])];
            }
        }

        // WhatsApp broadcasts can't be recalled; its service clears the recipient list.
        if ($post->platform === 'whatsapp') {
            app(WhatsAppPostService::class)->destroy($post);
        }

        $this->purgeLocal($post);

        return ['success' => true, 'remote' => $caps['delete_remote'], 'message' => __($caps['delete_remote'] ? 'posts_listing.manage.deleted_platform' : 'posts_listing.manage.deleted')];
    }

    /**
     * $data holds only fields from PostCapabilities' edit_fields (validated by the caller).
     *
     * @return array{success: bool, message: string}
     */
    public function update(Post $post, array $data): array
    {
        $caps = PostCapabilities::for($post);
        $data = array_intersect_key($data, array_flip($caps['edit_fields']));
        if (!$caps['can_edit'] || $data === []) {
            return ['success' => false, 'message' => __('posts_listing.caps.' . ($caps['reason'] ?? 'platform_read_only'))];
        }

        if ($caps['edit_remote']) {
            $result = $this->onPlatform($post, fn ($service) => $service->editPublished($post, $data + ['title' => $post->title, 'content' => $post->content]));
            if (!($result['success'] ?? false)) {
                return ['success' => false, 'message' => __('posts_listing.manage.update_failed_platform', ['reason' => $this->reason($result)])];
            }
        }

        $post->fill($data)->save();

        return ['success' => true, 'message' => __($caps['edit_remote'] ? 'posts_listing.manage.updated_platform' : 'posts_listing.manage.updated')];
    }

    /**
     * Run a platform call. The services' token helpers mark a post "failed"
     * when a refresh fails - right while publishing, wrong for an edit or
     * delete of a post that is live - so a published post's status is kept.
     */
    private function onPlatform(Post $post, callable $call): array
    {
        $service = app(self::SERVICES[$post->platform]);
        $before = $post->only(['status', 'error_message']);

        try {
            $result = $call($service);
        } catch (\Throwable $e) {
            Log::warning('Post management call failed', ['post' => $post->id, 'platform' => $post->platform, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $fresh = $post->fresh();
        if ($fresh && $fresh->only(['status', 'error_message']) !== $before) {
            $fresh->forceFill($before)->save();
            $post->forceFill($before);
        }

        return is_array($result) ? $result : ['success' => (bool) $result];
    }

    private function reason(array $result): string
    {
        $reason = $result['message'] ?? $result['error'] ?? null;

        return is_string($reason) && $reason !== '' ? Post::cleanErrorMessage($reason) : __('posts_listing.manage.no_reason');
    }

    /**
     * Remove the post and its media files. A media_url can be shared by other
     * PostMedia rows (every platform of one submission points at the same
     * upload), so a file is deleted only once nothing else references it.
     */
    private function purgeLocal(Post $post): void
    {
        foreach ($post->media()->get() as $media) {
            if (PostMedia::where('media_url', $media->media_url)->where('id', '!=', $media->id)->exists()) {
                continue;
            }
            // Media Gallery files belong to the gallery: kept while the item is still in it.
            if (app(MediaAssetService::class)->releaseIfOrphaned($media->media_url, $media->id)) {
                continue;
            }
            $path = ltrim((string) parse_url((string) $media->media_url, PHP_URL_PATH), '/');
            if ($path !== '') {
                Storage::disk('r2')->delete($path);
            }
        }

        $post->delete();
    }
}
