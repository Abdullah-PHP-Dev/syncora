<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\TiktokPostService;
use Illuminate\Console\Command;

/**
 * Re-reads likes, shares, comments and views for recent Facebook,
 * Instagram and TikTok posts, and TikTok account totals. Facebook sends no
 * webhook for shares to people's own timelines; Instagram none for
 * insights; TikTok none for counts.
 */
class SyncPostEngagement extends Command
{
    protected $signature = 'posts:sync-engagement
                            {--days=30 : Posts published within this many days}
                            {--post= : Only this post (app post id)}';

    protected $description = 'Refresh likes, shares, comments and views for recent Facebook, Instagram and TikTok posts';

    public function handle(MetaPostService $meta, InstagramPostService $instagram, TiktokPostService $tiktok): int
    {
        $services = ['facebook' => $meta, 'instagram' => $instagram, 'tiktok' => $tiktok];

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

        // TikTok posts still waiting for their public id (TikTok gives it
        // only after its review) - pick it up even without the webhook.
        $resolved = 0;
        foreach ($posts->filter(fn (Post $post) => $post->platform === 'tiktok' && preg_match('/^[vp]_pub/', (string) $post->post_id)) as $post) {
            $result = $post->socialAccount && $tiktok->refreshAccountToken($post->socialAccount)
                ? $tiktok->checkPublishStatus($post->socialAccount->access_token, $post->post_id)
                : [];
            if (! empty($result['video_id'])) {
                $type = $tiktok->isVideoPost($post->loadMissing('media')) ? 'video' : 'photo';
                $post->update(['post_id' => (string) $result['video_id'], 'post_url' => "https://www.tiktok.com/@{$post->socialAccount->username}/{$type}/{$result['video_id']}", 'error_message' => null]);
                $resolved++;
            }
        }
        if ($resolved) {
            $this->info("TikTok: {$resolved} post(s) became public.");
        }

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
