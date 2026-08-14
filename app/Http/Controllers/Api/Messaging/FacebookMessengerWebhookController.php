<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PostComment;
/**
 * Facebook Page webhook. Meta only allows ONE registered callback URL per
 * App per object type ("page") - there is no way to have message events
 * (entry[].messaging[]) delivered to one URL and comment events
 * (entry[].changes[]) delivered to a different one. Whichever of this
 * controller or Comments\FacebookCommentWebhookController ends up as the
 * actual registered URL in the App Dashboard must therefore handle both,
 * so both dispatch to both services regardless.
 */
class FacebookMessengerWebhookController extends Controller
{
    public function __construct(
        protected FacebookMessengerService $messengerService,
        protected MetaPostService $postService,
    ) {
    }

    /**
     * The one-time GET handshake Meta performs when the webhook URL is
     * configured (and periodically re-verifies).
     */
    public function verify(Request $request)
    {
        // messaging.meta.* and posts.facebook.* are configured separately
        // even though they're normally the same underlying Meta App - accept
        // whichever verify token Meta was actually configured with.
        $challenge = $this->messengerService->verifyWebhook($request)
            ?? $this->postService->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    /**
     * Every subsequent event delivery. Must ack fast (Meta retries
     * aggressively on slow/failed responses and can eventually disable the
     * subscription) - the actual DB write + broadcast happens in a queued
     * job dispatched from inside the service, so this just verifies
     * authenticity and hands the payload off.
     */
    public function receive(Request $request)
    {
        PostComment::updateOrCreate(
            ['platform' => 'facebook', 'comment_id' => 23432432],
            [
                'content'           => json_encode([]),
                'sender_type'       => 'customer',
                'user_id'           => 1,
                'user_name'         => 'verify',
                'post_id'           => 154,
                'post_account_id'   => 15,
                'parent_comment_id' => '',
                'is_reply'          => false,
            ]
        );
        // messaging.meta.* and posts.facebook.* are configured separately
        // even though they're normally the same underlying Meta App - accept
        // whichever verify token Meta was actually configured with.
        $challenge = $this->messengerService->verifyWebhook($request)
            ?? $this->postService->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }
}
