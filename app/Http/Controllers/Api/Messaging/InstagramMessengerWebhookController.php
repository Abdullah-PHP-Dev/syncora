<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\InstagramMessengerService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PostComment;

/**
 * Instagram webhook. Meta only allows ONE registered callback URL per App
 * per object type ("instagram") - there is no way to have message events
 * (entry[].messaging[]) delivered to one URL and comment events
 * (entry[].changes[]) delivered to a different one. Whichever of this
 * controller or Comments\InstagramCommentWebhookController ends up as the
 * actual registered URL in the App Dashboard must therefore handle both,
 * so both dispatch to both services regardless.
 */
class InstagramMessengerWebhookController extends Controller
{
    public function __construct(
        protected InstagramMessengerService $messengerService,
        protected MetaPostService $postService,
    ) {
    }

    public function verify(Request $request)
    {
         PostComment::updateOrCreate(
            ['platform' => 'facebook', 'comment_id' => '234324234'],
            [
                'content'           => json_encode($request->all()),
                'sender_type'       => 'customer',
                'user_id'           => 1,
                'user_name'         => 'adad1',
                'post_id'           => 131,
                'post_account_id'   => 15,
                'parent_comment_id' => null,
                'is_reply'          => 0,
            ]
        );
        // messaging.instagram.* and posts.facebook.* are configured
        // separately even though they're normally the same underlying Meta
        // App - accept whichever verify token Meta was actually configured
        // with.
        $challenge = $this->messengerService->verifyWebhook($request)
            ?? $this->postService->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (!$this->messengerService->verifySignature($request) && !$this->postService->verifySignature($request)) {
            Log::warning('Instagram webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $payload = $request->all();

        $this->messengerService->handleWebhook($payload);
        $this->postService->handleCommentWebhook($payload, 'instagram');

        return response('EVENT_RECEIVED', 200);
    }
}
