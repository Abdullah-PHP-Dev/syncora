<?php

namespace App\Http\Controllers\Api\Comments;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Services\PostServices\LinkedInPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Organization Social Action Notifications - LinkedIn pushes
 * LIKE, COMMENT, SHARE, SHARE_MENTION, ADMIN_COMMENT, COMMENT_EDIT and
 * COMMENT_DELETE on a Page's posts (LIKE/COMMENT/SHARE only for PUBLIC posts).
 * learn.microsoft.com/linkedin/marketing/community-management/organizations/organization-social-action-notifications
 * learn.microsoft.com/linkedin/shared/api-guide/webhook-validation
 *
 *   - verify(): GET ?challengeCode= -> {"challengeCode", "challengeResponse"}
 *     where challengeResponse = hex(HMAC-SHA256(challengeCode, clientSecret)),
 *     within 3 seconds; LinkedIn re-validates every 2 hours.
 *   - receive(): body {"type": "ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS",
 *     "notifications": [{notificationId, action, organizationalEntity,
 *     sourcePost, generatedActivity, lastModifiedAt, ...}]}, signed with
 *     X-LI-Signature = hex(HMAC-SHA256("hmacsha256=" + raw body, clientSecret)).
 *
 * Notifications carry no counts, so each one re-reads the post through
 * LinkedInPostService::syncEngagement() (likes, comments, impressions,
 * clicks, shares and the latest comments) after the response is sent.
 * LinkedIn redelivers for 8 hours; duplicates (same notificationId and
 * lastModifiedAt) are skipped.
 */
class LinkedinCommentWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $code = (string) $request->query('challengeCode', '');
        $secret = (string) adminSetting('posts.linkedin.client_secret');
        if ($code === '') {
            return response('Missing challengeCode.', 400);
        }
        if ($secret === '') {
            Log::error('LinkedIn posting webhook validation requested, but posts.linkedin.client_secret is not configured.');

            return response('Webhook not configured.', 500);
        }

        return response()->json(['challengeCode' => $code, 'challengeResponse' => hash_hmac('sha256', $code, $secret)]);
    }

    public function receive(Request $request)
    {
        $secret = (string) adminSetting('posts.linkedin.client_secret');
        $signature = (string) $request->header('X-LI-Signature', '');
        if ($secret === '' || $signature === '' || ! hash_equals(hash_hmac('sha256', 'hmacsha256=' . $request->getContent(), $secret), strtolower($signature))) {
            Log::warning('LinkedIn posting webhook rejected: bad or missing X-LI-Signature.', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();
        if (($payload['type'] ?? null) !== 'ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS') {
            return response('EVENT_RECEIVED', 200);
        }

        $toSync = [];
        foreach ((array) ($payload['notifications'] ?? []) as $notification) {
            $key = 'linkedin-notification:' . ($notification['notificationId'] ?? '') . ':' . ($notification['lastModifiedAt'] ?? '');
            if (empty($notification['notificationId']) || ! Cache::add($key, true, now()->addDay())) {
                continue;
            }

            foreach ($this->postsFor($notification) as $post) {
                if (($notification['action'] ?? null) === 'COMMENT_DELETE') {
                    $this->forgetComment($post, (string) ($notification['generatedActivity'] ?? ''));
                }
                $toSync[$post->id] = $post->id;
            }
        }

        if ($toSync) {
            // Answer LinkedIn first; the reads happen after the response.
            dispatch(function () use ($toSync) {
                $service = app(LinkedInPostService::class);
                Post::with('socialAccount')->whereIn('id', $toSync)->get()->each(function (Post $post) use ($service) {
                    try {
                        $service->syncEngagement($post);
                    } catch (\Throwable $e) {
                        Log::warning('LinkedIn webhook sync failed.', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                    }
                });
            })->afterResponse();
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * The SocialEaz posts a notification is about. sourcePost is usually the
     * post's own URN (urn:li:share / urn:li:ugcPost, what publishing stores);
     * when LinkedIn sends the activity URN instead, the Page's recent posts
     * are re-read so nothing is missed.
     */
    private function postsFor(array $notification)
    {
        $org = (string) ($notification['organizationalEntity'] ?? '');
        $accounts = SocialAccount::where('platform', 'linkedin')
            ->where('platform_account_id', str_replace('urn:li:organization:', '', $org))
            ->pluck('id');
        if ($accounts->isEmpty()) {
            return collect();
        }

        $candidates = array_filter([
            $notification['sourcePost'] ?? null,
            $notification['decoratedSourcePost']['entity'] ?? null,
            $notification['decoratedSourcePost']['id'] ?? null,
        ], 'is_string');
        $matched = Post::where('platform', 'linkedin')->whereIn('social_account_id', $accounts)->whereIn('post_id', $candidates)->get();
        if ($matched->isNotEmpty()) {
            return $matched;
        }

        return Post::where('platform', 'linkedin')->whereIn('social_account_id', $accounts)
            ->whereIn('status', ['completed', 'published'])->whereNotNull('post_id')
            ->where('created_at', '>=', now()->subDays(30))->latest('id')->limit(10)->get();
    }

    /** generatedActivity is the comment URN: urn:li:comment:(urn:li:activity:..,{id}). */
    private function forgetComment(Post $post, string $commentUrn): void
    {
        if ($commentUrn === '') {
            return;
        }
        $ids = [$commentUrn];
        if (preg_match('/,(\d+)\)$/', $commentUrn, $m)) {
            $ids[] = $m[1];
        }
        PostComment::where('post_id', $post->id)->whereIn('comment_id', $ids)->delete();
    }
}
