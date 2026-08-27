<?php

namespace App\Jobs\Posts;

use App\Models\Post;
use App\Services\PostServices\TiktokPostService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TikTok's post/publish/status/fetch/ can stay in a processing state well
 * past the ~10s TiktokPostService::publishPost() is willing to block an
 * HTTP request for (longer/larger videos in particular) - this job picks
 * up where that synchronous fast-path check leaves off, re-checking on a
 * delay (self-redispatching, not sleeping inside a queue worker) until
 * either the real video id/url is resolved or a generous attempt budget
 * (20 attempts, 15s apart - up to 5 minutes) is used up.
 */
class ResolveTiktokPublishStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $postId,
        public string $publishId,
        public int $attempt = 1,
    ) {
    }

    public function handle(TiktokPostService $service): void
    {
        $post = Post::find($this->postId);

        if (!$post || $post->platform !== 'tiktok') {
            return;
        }

        // Something else already resolved this (eg. a retried/duplicated
        // dispatch) - nothing left to do.
        if ($post->post_id !== $this->publishId) {
            return;
        }

        $account = $post->socialAccount;

        if (!$account) {
            return;
        }

        $result = $service->checkPublishStatus($account->access_token, $this->publishId);

        if (!empty($result['video_id'])) {
            $post->update([
                'post_id'       => $result['video_id'],
                'post_url'      => 'https://www.tiktok.com/@' . $account->username . '/video/' . $result['video_id'],
                'error_message' => null,
            ]);

            return;
        }

        if (($result['status'] ?? null) === 'FAILED') {
            $post->update(['error_message' => 'TikTok reported the upload failed after publishing.']);

            return;
        }

        if ($this->attempt >= 20) {
            $post->update(['error_message' => "Published to TikTok, but its public URL never became available after several minutes - check the account directly."]);

            return;
        }

        self::dispatch($this->postId, $this->publishId, $this->attempt + 1)->delay(now()->addSeconds(15));
    }
}
