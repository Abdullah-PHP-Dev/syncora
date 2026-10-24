<?php

namespace App\Services\PostServices;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Services\Connections\Drivers\ThreadsDriver;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Threads reply webhooks (developers.facebook.com/docs/threads/webhooks):
 * topic "moderate", field "replies" - replies on the connected account's
 * posts. Subscribed once, app-wide, in the App Dashboard (Threads use case >
 * Webhooks) with callback /api/comments/threads; delivered only when the
 * account granted threads_read_replies, the app is Live (Advanced Access)
 * and the post's owner is a public profile.
 *
 * Payload: target_id = the subscribed Threads user (SocialAccount
 * platform_account_id); values.value = the reply - id, username, text,
 * timestamp, replied_to.id (the post, or the reply it answers) and
 * root_post.id (the post, Post.post_id).
 */
class ThreadsReplyWebhookService
{
    /** Meta's hub.challenge handshake. */
    public function verify(Request $request): ?string
    {
        $expected = (string) (adminSetting('threads.facebook.webhook_verify_token')
            ?: adminSetting('posts.facebook.webhook_verify_token', 'socialeaz-98897'));

        return $request->query('hub_mode') === 'subscribe' && hash_equals($expected, (string) $request->query('hub_verify_token'))
            ? (string) $request->query('hub_challenge')
            : null;
    }

    /**
     * X-Hub-Signature-256 (HMAC-SHA256 of the raw body), as every Meta
     * webhook is signed - with the Threads app's secret, or the main Meta
     * app's when Threads is a use case of that same app.
     */
    public function verifySignature(Request $request): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        foreach (array_filter([ThreadsDriver::clientSecret(), (string) adminSetting('posts.facebook.client_secret')]) as $secret) {
            if (hash_equals(hash_hmac('sha256', $request->getContent(), $secret), substr($header, 7))) {
                return true;
            }
        }

        return false;
    }

    /** @return int replies stored (new or updated) */
    public function handle(array $payload): int
    {
        // "values" is one {field, value} object; tolerate a list as well.
        $values = isset($payload['values']['field']) ? [$payload['values']] : (array) ($payload['values'] ?? []);
        $stored = 0;

        foreach ($values as $change) {
            if (($change['field'] ?? null) === 'replies' && $this->storeReply((string) ($payload['target_id'] ?? ''), (array) ($change['value'] ?? []))) {
                $stored++;
            }
        }

        return $stored;
    }

    private function storeReply(string $targetId, array $reply): bool
    {
        $replyId = (string) ($reply['id'] ?? '');
        $rootId = (string) ($reply['root_post']['id'] ?? '');

        if ($replyId === '' || trim((string) ($reply['text'] ?? '')) === '') {
            return false;
        }

        $account = SocialAccount::where('platform', 'threads')
            ->whereIn('platform_account_id', array_filter([$targetId, (string) ($reply['root_post']['owner_id'] ?? '')]))
            ->first();

        if (! $account) {
            return false;
        }

        $post = $rootId !== '' ? Post::where('social_account_id', $account->id)->where('post_id', $rootId)->first() : null;
        // Answering another reply rather than the post itself.
        $repliedTo = (string) ($reply['replied_to']['id'] ?? '');
        $parent = $repliedTo !== '' && $repliedTo !== $rootId
            ? PostComment::where('platform', 'threads')->where('comment_id', $repliedTo)->first()
            : null;
        $isOwn = $account->username && strcasecmp((string) ($reply['username'] ?? ''), $account->username) === 0;

        $comment = PostComment::updateOrCreate(
            ['platform' => 'threads', 'comment_id' => $replyId],
            [
                'content' => (string) $reply['text'],
                'sender_type' => $isOwn ? 'support' : 'customer',
                'user_id' => $post?->user_id ?? $account->user_id,
                'post_id' => $post?->id,
                'social_account_id' => $account->id,
                'parent_comment_id' => $parent?->id,
                'is_reply' => (bool) $parent,
                'user_name' => $reply['username'] ?? 'Anonymous',
                'status' => 'approved',
                'posted_at' => isset($reply['timestamp']) ? Carbon::parse($reply['timestamp']) : now(),
                'imported_at' => now(),
            ] + (isset($reply['profile_picture_url']) ? ['user_avatar_url' => $reply['profile_picture_url']] : [])
        );

        // Meta retries deliveries: count each reply once.
        if ($comment->wasRecentlyCreated && $post) {
            $post->forceFill(['comments' => (int) $post->comments + 1])->save();
        }

        return true;
    }
}
