<?php

namespace App\Console\Commands;

use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Services\PostServices\MetaPostService;
use Illuminate\Console\Command;

/**
 * Fills in commenter pictures (and missing names) for Facebook comments
 * saved without one - those that arrived before comment webhooks looked
 * the picture up. Facebook's picture URLs are signed and expire after a
 * while, so --all refreshes every comment's picture too.
 */
class RefreshFacebookCommentAvatars extends Command
{
    protected $signature = 'comments:facebook-avatars
                            {--post= : Only this post (app post id)}
                            {--all : Refresh every picture, not just missing ones}
                            {--dry-run : Count the comments without calling Facebook}';

    protected $description = 'Fetch commenter avatars for Facebook comments';

    public function handle(MetaPostService $meta): int
    {
        $comments = PostComment::where('platform', 'facebook')
            ->whereNotNull('comment_id')
            ->where('comment_id', 'not like', 'seed_%')
            ->when(! $this->option('all'), fn ($q) => $q->where(fn ($q) => $q->whereNull('user_avatar_url')->orWhere('user_avatar_url', '')))
            ->when($this->option('post'), fn ($q, $post) => $q->where('post_id', $post))
            ->orderBy('id')
            ->get();

        $this->info($comments->count() . ' comment(s) to update.');
        if ($this->option('dry-run') || $comments->isEmpty()) {
            return self::SUCCESS;
        }

        $accounts = SocialAccount::whereIn('id', $comments->pluck('social_account_id')->filter()->unique())->get()->keyBy('id');
        $updated = 0;

        foreach ($comments as $comment) {
            $account = $accounts[$comment->social_account_id] ?? null;
            if (! $account?->access_token) {
                continue;
            }

            $profile = $comment->sender_type === 'support'
                ? ['avatar' => $account->avatar_url]
                : $meta->commenterProfile($comment->comment_id, $account);

            $changes = array_filter([
                'user_avatar_url' => $profile['avatar'] ?? null,
                'user_name' => in_array($comment->user_name, [null, '', 'Anonymous', 'Facebook user', 'support'], true) ? ($profile['name'] ?? ($comment->sender_type === 'support' ? $account->name : null)) : null,
            ]);

            if ($changes) {
                $comment->update($changes);
                $updated++;
            }
        }

        $this->info("Updated {$updated} of {$comments->count()} comment(s).");

        return self::SUCCESS;
    }
}
