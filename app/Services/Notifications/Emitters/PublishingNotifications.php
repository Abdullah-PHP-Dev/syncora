<?php

namespace App\Services\Notifications\Emitters;

use App\Models\Post;
use App\Services\Notifications\Notify;

/**
 * PUB-01..08 from a post reaching a terminal status, whoever wrote it (PublishPosts, platform
 * services, the TikTok status resolver, the stuck-claim sweep).
 *
 * A multi-platform post (shared group_id) is reported once, when every channel has finished:
 * all live → one "published", some failed → one "partly published", none live → the failures
 * (which the email grouping window folds into a single message). Never one email per platform (R5).
 */
class PublishingNotifications
{
    public const SUCCESS = ['completed', 'published'];

    public const TERMINAL = ['completed', 'published', 'failed'];

    public const INTERRUPTED = 'Publishing was interrupted';

    public static function statusChanged(Post $post): void
    {
        if (! in_array($post->status, self::TERMINAL, true) || ! $post->user_id) {
            return;
        }
        if ($post->group_id) {
            $group = Post::where('user_id', $post->user_id)->where('group_id', $post->group_id)->get(['id', 'status', 'platform', 'content', 'error_message', 'social_account_id', 'user_id', 'updated_at']);
            if ($group->count() > 1) {
                if ($group->contains(fn (Post $p) => ! in_array($p->status, self::TERMINAL, true) && $p->status !== 'draft')) {
                    return; // Wait for the other channels.
                }
                $live = $group->filter(fn (Post $p) => in_array($p->status, self::SUCCESS, true));
                $failed = $group->where('status', 'failed');
                if ($live->isNotEmpty() && $failed->isNotEmpty()) {
                    Notify::event('publishing.partially_published', [
                        'workspace' => $post->user_id, 'subject' => $post, 'actor' => Support::actorId(),
                        'params' => ['succeeded' => $live->count(), 'total' => $live->count() + $failed->count(),
                            'failed' => $failed->map(fn (Post $p) => Support::platform($p->platform))->unique()->implode(', '),
                            'excerpt' => Support::excerpt($post->content)],
                        'url' => Support::url('admin.posts.edit', ['post' => $failed->first()->id], 'admin.posts.index'),
                        'dedup' => 'group:'.$post->group_id.':'.$group->max('updated_at'),
                    ]);

                    return;
                }
                if ($failed->isEmpty()) {
                    self::published($post, $group->map(fn (Post $p) => Support::platform($p->platform))->unique()->implode(', '), 'group:'.$post->group_id);

                    return;
                }
                $failed->each(fn (Post $p) => self::failed($p));

                return;
            }
        }
        in_array($post->status, self::SUCCESS, true) ? self::published($post, Support::platform($post->platform), (string) $post->id) : self::failed($post);
    }

    private static function published(Post $post, string $platforms, string $dedup): void
    {
        Notify::event('publishing.post_published', [
            'workspace' => $post->user_id, 'subject' => $post, 'actor' => Support::actorId(),
            'params' => ['platform' => $platforms, 'excerpt' => Support::excerpt($post->content)],
            'url' => Support::url('admin.posts.index'), 'dedup' => $dedup,
        ]);
    }

    private static function failed(Post $post): void
    {
        $reason = (string) ($post->error_message ?: '—');
        $event = match (true) {
            str_starts_with($reason, self::INTERRUPTED) => 'publishing.post_interrupted',
            self::needsReconnect($post, $reason) => 'publishing.post_failed_reconnect',
            default => 'publishing.post_failed',
        };
        $reconnect = $event === 'publishing.post_failed_reconnect';
        Notify::event($event, [
            'workspace' => $post->user_id, 'subject' => $post, 'actor' => Support::actorId(),
            'params' => ['platform' => Support::platform($post->platform), 'excerpt' => Support::excerpt($post->content), 'reason' => Support::excerpt($reason, 200)],
            'dates' => ['when' => $post->schedule_at ?? now()],
            'url' => $reconnect ? Support::url('admin.connections.index') : Support::url('admin.posts.edit', ['post' => $post->id], 'admin.posts.index'),
            'dedup' => $post->updated_at?->timestamp ?? now()->timestamp,
        ]);
    }

    /** Failed because the account's access is gone: reconnecting is the fix, not editing the post. */
    private static function needsReconnect(Post $post, string $reason): bool
    {
        if (rescue(fn () => $post->socialAccount && $post->socialAccount->is_token_valid === false, false, false)) {
            return true;
        }

        return (bool) preg_match('/(access token|token (has )?expired|session has expired|re-?auth|reconnect|invalid oauth|oauth.*(invalid|expired)|not authori[sz]ed|unauthori[sz]ed|permission.*(revoked|removed))/i', $reason);
    }
}
