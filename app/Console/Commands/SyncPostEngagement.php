<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\TiktokPostService;
use Illuminate\Console\Command;

/**
 * Re-reads likes, shares, comments and views for recent Facebook and
 * TikTok posts, and TikTok account totals. Facebook sends no webhook for
 * shares to people's own timelines; TikTok sends none for counts.
 */
class SyncPostEngagement extends Command
{
    protected $signature = 'posts:sync-engagement
                            {--days=30 : Posts published within this many days}
                            {--post= : Only this post (app post id)}';

    protected $description = 'Refresh likes, shares, comments and views for recent Facebook and TikTok posts';

    public function handle(MetaPostService $meta, TiktokPostService $tiktok): int
    {
        $services = ['facebook' => $meta, 'tiktok' => $tiktok];

        $posts = Post::with('socialAccount')
            ->whereIn('platform', array_keys($services))
            ->whereNotNull('post_id')
            ->whereIn('status', ['completed', 'published'])
            ->when(
                $this->option('post'),
                fn ($q, $id) => $q->where('id', $id),
                fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            )
            ->orderByDesc('id')
            ->get();

        $synced = $posts->filter(fn (Post $post) => $services[$post->platform]->syncEngagement($post))->count();
        $this->info("Synced {$synced} of {$posts->count()} post(s).");

        // TikTok account totals (followers, likes, videos) - no webhook for these either.
        if (! $this->option('post')) {
            SocialAccount::where('platform', 'tiktok')->where('has_posting_permission', true)->get()
                ->each(fn ($account) => $tiktok->syncAccountStats($account));
        }

        return self::SUCCESS;
    }
}
