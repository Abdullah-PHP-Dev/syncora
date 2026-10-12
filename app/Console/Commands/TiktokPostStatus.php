<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostServices\TiktokPostService;
use Illuminate\Console\Command;

/**
 * Asks TikTok where a post is (POST /v2/post/publish/status/fetch/) and
 * updates it: the real post id and link once public, "private" when it
 * finished as SELF_ONLY (no public page), or TikTok's fail_reason.
 */
class TiktokPostStatus extends Command
{
    protected $signature = 'tiktok:post-status {post : App post id}';

    protected $description = 'Check a TikTok post\'s publish status with TikTok and update it';

    public function handle(TiktokPostService $tiktok): int
    {
        $post = Post::with('socialAccount', 'media')->where('platform', 'tiktok')->find($this->argument('post'));
        if (! $post) {
            $this->error('No TikTok post with that id.');

            return self::FAILURE;
        }

        $publishId = $post->platform_metadata['publish_id'] ?? (str_starts_with((string) $post->post_id, 'v_pub') || str_starts_with((string) $post->post_id, 'p_pub') ? $post->post_id : null);
        $account = $post->socialAccount;
        $this->line("Post #{$post->id} | status: {$post->status} | post_id: {$post->post_id} | publish_id: " . ($publishId ?? '-'));

        if (! $publishId) {
            $this->warn('No publish id saved - this post was never sent to TikTok, or is already resolved.');

            return self::FAILURE;
        }
        if (! $account?->access_token || ! $tiktok->refreshAccountToken($account)) {
            $this->error('The TikTok account token is missing or expired - reconnect TikTok in Connections.');

            return self::FAILURE;
        }

        $result = $tiktok->checkPublishStatus($account->access_token, $publishId);
        $this->table(['TikTok status', 'Post id', 'Fail reason', 'Downloaded bytes', 'API error'], [[
            $result['status'] ?? '-', $result['video_id'] ?? '-', $result['fail_reason'] ?? '-', $result['downloaded'] ?? '-', $result['error'] ?? '-',
        ]]);

        $type = $tiktok->isVideoPost($post) ? 'video' : 'photo';
        $update = match (true) {
            ! empty($result['video_id']) => ['status' => 'completed', 'post_id' => (string) $result['video_id'], 'post_url' => "https://www.tiktok.com/@{$account->username}/{$type}/{$result['video_id']}", 'error_message' => null],
            ($result['status'] ?? null) === 'PUBLISH_COMPLETE' => ['status' => 'completed', 'error_message' => "Published to TikTok as private (only you can see it) - TikTok gives private posts no public link."],
            ($result['status'] ?? null) === 'FAILED' => ['status' => 'failed', 'error_message' => 'TikTok could not publish this post' . (! empty($result['fail_reason']) ? " ({$result['fail_reason']})" : '') . '.'],
            default => null,
        };

        if ($update) {
            $post->update($update);
            $this->info('Post updated: ' . ($update['error_message'] ?? $update['post_url']));
        } else {
            $this->warn('Not finished yet - TikTok is still processing it. Run this again in a few minutes.');
        }

        return self::SUCCESS;
    }
}
