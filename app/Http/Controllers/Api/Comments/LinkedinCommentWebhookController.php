<?php

namespace App\Http\Controllers\Api\Comments;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receiver for LinkedIn organic engagement events (comments/likes/shares).
 * LinkedIn's standard Community Management API - which is what this
 * module authenticates against in PostAccountController::redirectLinkedin()
 * - has no self-serve push/webhook product for these events, unlike Meta's
 * App-level callback URL. This route is what
 * LinkedInPostService::subscribeToWebhooks() records as the org's
 * "webhook_callback_url" setting on connect, ready for an admin to
 * register by hand IF the org is later approved for a push-capable
 * LinkedIn product. Until/unless that happens, comment/like/share data is
 * kept current by polling instead - see
 * LinkedInPostService::backfillRecentPosts(), the same pull-only model
 * already used for YouTube (see YoutubeWebhookController).
 *
 * No GET verify handshake is registered here (unlike the Meta comment
 * webhooks) because LinkedIn has no challenge-response step to answer in
 * the first place.
 */
class LinkedinCommentWebhookController extends Controller
{
    public function receive(Request $request)
    {
        $payload = $request->all();

        Log::info('LinkedIn webhook event received.', ['payload' => $payload]);

        $postUrn = $payload['postUrn'] ?? $payload['object'] ?? null;
        $commentUrn = $payload['commentUrn'] ?? $payload['id'] ?? null;

        if (!$postUrn || !$commentUrn) {
            return response('EVENT_RECEIVED', 200);
        }

        $post = Post::where('platform', 'linkedin')->where('post_id', $postUrn)->first();

        if (!$post) {
            return response('EVENT_RECEIVED', 200);
        }

        PostComment::updateOrCreate(
            ['comment_id' => $commentUrn, 'post_id' => $post->id],
            [
                'platform'        => 'linkedin',
                'user_id'         => $post->user_id,
                'social_account_id' => $post->social_account_id,
                'user_name'       => $payload['actor'] ?? 'LinkedIn user',
                'content'         => $payload['message']['text'] ?? ($payload['text'] ?? ''),
                'sender_type'     => 'customer',
                'is_reply'        => false,
                'status'          => 'approved',
                'posted_at'       => now(),
                'imported_at'     => now(),
            ]
        );

        return response('EVENT_RECEIVED', 200);
    }
}
