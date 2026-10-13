<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostServices\TiktokCommentsService;
use Illuminate\Console\Command;

/**
 * Pulls comments and replies on recent TikTok posts into post_comments.
 * TikTok's posting (Login Kit) app has no comment webhook, so comments
 * are polled through the "TikTok comments" connection (Accounts API,
 * TiktokCommentsService). Scheduled hourly.
 */
class SyncTiktokComments extends Command
{
    protected $signature = 'tiktok:sync-comments
                            {--days=30 : Posts created within this many days}
                            {--post= : Only this post (app post id)}';

    protected $description = 'Import comments and replies on recent TikTok posts';

    public function handle(TiktokCommentsService $comments): int
    {
        $posts = Post::with('socialAccount')
            ->where('platform', 'tiktok')
            ->whereIn('status', ['completed', 'published'])
            ->whereNotNull('post_id')
            ->when(
                $this->option('post'),
                fn ($q, $id) => $q->where('id', $id),
                fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            )
            ->orderByDesc('id')
            ->get()
            // Only posts already on TikTok (a numeric id, not a publish_id).
            ->filter(fn (Post $post) => ctype_digit((string) $post->post_id));

        $synced = $failed = 0;
        $rows = [];
        foreach ($posts as $post) {
            $result = $comments->syncComments($post);
            $result['success'] ? $synced += $result['synced'] : $failed++;
            $rows[] = [$post->id, $post->post_id, $result['success'] ? "{$result['synced']} comment(s)" : $result['message']];
        }

        if ($rows) {
            $this->table(['Post', 'TikTok id', 'Result'], $rows);
        }
        $this->info("Imported {$synced} comment(s) from " . ($posts->count() - $failed) . " of {$posts->count()} TikTok post(s).");

        return self::SUCCESS;
    }
}
