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

        $native = [];
        if ($caps['edit_remote']) {
            $result = $this->onPlatform($post, fn ($service) => $service->editPublished($post, $data + ['title' => $post->title, 'content' => $post->content]));
            if (!($result['success'] ?? false)) {
                return ['success' => false, 'message' => __('posts_listing.manage.update_failed_platform', ['reason' => $this->reason($result)])];
            }
            // An X edit is a new post id (and link); keep pointing at the live one.
            $native = array_filter(array_intersect_key($result, array_flip(['post_id', 'post_url', 'platform_metadata'])), fn ($v) => $v !== null && $v !== '');
        }

        $post->fill($data)->forceFill($native)->save();

        return ['success' => true, 'message' => __($caps['edit_remote'] ? 'posts_listing.manage.updated_platform' : 'posts_listing.manage.updated')];
    }

    /**
     * Send a failed post again - only where PostCapabilities::retry() says a
     * resend can't duplicate it. It goes back to the publisher's queue
     * (social:publish-posts), which re-checks the package and claims it, so
     * nothing is sent from the web request.
     *
     * @return array{success: bool, message: string}
     */
    public function retry(Post $post, bool $confirmed = false): array
    {
        $caps = PostCapabilities::for($post);
        if (!$caps['can_retry']) {
            return ['success' => false, 'message' => __('posts_listing.caps.' . ($caps['reason'] ?? 'retry_unavailable'))];
        }
        if ($caps['retry_confirm'] && !$confirmed) {
            return ['success' => false, 'confirm' => true, 'message' => __('posts_listing.lifecycle.retry_confirm')];
        }

        $meta = $post->platform_metadata ?? [];
        unset($meta['publish_attempts']);
        $meta['manual_retries'] = (int) ($meta['manual_retries'] ?? 0) + 1;

        // Conditional: a second click (or tab) can't queue it twice.
        $queued = Post::whereKey($post->id)->where('status', 'failed')->whereNull('post_id')->update([
            'status' => 'pending',
            'error_message' => null,
            'platform_metadata' => json_encode($meta),
            'updated_at' => now(),
        ]);

        return $queued
            ? ['success' => true, 'message' => __('posts_listing.lifecycle.retry_queued')]
            : ['success' => false, 'message' => __('posts_listing.lifecycle.changed_meanwhile')];
    }

    /** Stop a scheduled post from going out; it stays as a draft. */
    public function cancelSchedule(Post $post): array
    {
        if (!PostCapabilities::for($post)['can_cancel']) {
            return ['success' => false, 'message' => __('posts_listing.caps.in_progress')];
        }

        // Only while the publisher hasn't claimed it (it flips pending -> publishing).
        $done = Post::whereKey($post->id)->whereIn('status', ['pending', 'scheduled'])->update([
            'status' => 'draft',
            'updated_at' => now(),
        ]);

        return $done
            ? ['success' => true, 'message' => __('posts_listing.lifecycle.cancelled')]
            : ['success' => false, 'message' => __('posts_listing.lifecycle.changed_meanwhile')];
    }

    /** Queue a draft: now, or at $at (app time, already validated as future). */
    public function publishDraft(Post $post, ?\Carbon\Carbon $at = null): array
    {
        if (!PostCapabilities::for($post)['can_publish']) {
            return ['success' => false, 'message' => __('posts_listing.caps.account_missing')];
        }

        $done = Post::whereKey($post->id)->where('status', 'draft')->update([
            'status' => 'pending',
            'schedule_mode' => $at ? 1 : 0,
            'schedule_at' => $at,
            'error_message' => null,
            'updated_at' => now(),
        ]);

        return $done
            ? ['success' => true, 'message' => __($at ? 'posts_listing.lifecycle.scheduled' : 'posts_listing.lifecycle.queued')]
            : ['success' => false, 'message' => __('posts_listing.lifecycle.changed_meanwhile')];
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
