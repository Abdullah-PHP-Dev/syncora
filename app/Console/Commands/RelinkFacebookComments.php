<?php

namespace App\Console\Commands;

use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Services\PostServices\MetaPostService;
use Illuminate\Console\Command;

/**
 * Links Facebook comments saved with an empty post_id to their post.
 * Comments on video posts never matched (the webhook sends the feed post
 * id, the app saved the video id - see MetaPostService::findWebhookPost()).
 * A Facebook comment id starts with its post's id ("{post}_{comment}"),
 * so the post can be found again from the comment alone.
 */
class RelinkFacebookComments extends Command
{
    protected $signature = 'comments:facebook-relink {--dry-run : Show what would be linked without saving}';

    protected $description = 'Link Facebook comments that have no post_id to their post';

    public function handle(MetaPostService $meta): int
    {
        $comments = PostComment::where('platform', 'facebook')->whereNull('post_id')->whereNotNull('comment_id')->orderBy('id')->get();
        $accounts = SocialAccount::whereIn('id', $comments->pluck('social_account_id')->filter()->unique())->get()->keyBy('id');
        $linked = 0;
        $rows = [];

        foreach ($comments as $comment) {
            $account = $accounts[$comment->social_account_id] ?? null;
            $postPart = str_contains($comment->comment_id, '_') ? strstr($comment->comment_id, '_', true) : null;

            $post = ($account && $postPart) ? $meta->findWebhookPost($account, $account->platform_account_id . '_' . $postPart) : null;

            if ($post && ! $this->option('dry-run')) {
                $comment->update(['post_id' => $post->id]);
                // Replies saved under this comment belong to the same post.
                PostComment::where('parent_comment_id', $comment->id)->whereNull('post_id')->update(['post_id' => $post->id]);
            }

            $linked += $post ? 1 : 0;
            $rows[] = [$comment->id, $comment->comment_id, $post ? "post #{$post->id}" : 'not found (post not in the app)'];
        }

        if ($rows) {
            $this->table(['Comment', 'Facebook comment id', 'Result'], $rows);
        }
        $this->info(($this->option('dry-run') ? 'Would link ' : 'Linked ') . "{$linked} of {$comments->count()} comment(s).");

        return self::SUCCESS;
    }
}
