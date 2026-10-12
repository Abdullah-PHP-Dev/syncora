<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostServices\MetaPostService;
use Illuminate\Console\Command;

/**
 * Re-reads likes, shares and comment counts for recently published
 * Facebook posts. Shares can only be read this way - Facebook sends no
 * webhook when people share a Page's post to their own timelines.
 */
class SyncPostEngagement extends Command
{
    protected $signature = 'posts:sync-engagement
                            {--days=30 : Posts published within this many days}
                            {--post= : Only this post (app post id)}';

    protected $description = 'Refresh likes, shares and comment counts for recent Facebook posts';

    public function handle(MetaPostService $meta): int
    {
        $posts = Post::with('socialAccount')
            ->where('platform', 'facebook')
            ->whereNotNull('post_id')
            ->whereIn('status', ['completed', 'published'])
            ->when(
                $this->option('post'),
                fn ($q, $id) => $q->where('id', $id),
                fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            )
            ->orderByDesc('id')
            ->get();

        $synced = $posts->filter(fn (Post $post) => $meta->syncEngagement($post))->count();
        $this->info("Synced {$synced} of {$posts->count()} Facebook post(s).");

        return self::SUCCESS;
    }
}
