<?php

namespace App\Http\Controllers\Api\Comments;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\InstagramMessengerService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
/**
 * Instagram webhook. Meta only allows ONE registered callback URL per App
 * per object type ("instagram") - there is no way to have comment events
 * (entry[].changes[]) delivered to one URL and message events
 * (entry[].messaging[]) delivered to a different one. Whichever of this
 * controller or InstagramMessengerWebhookController ends up as the actual
 * registered URL in the App Dashboard must therefore handle both, so both
 * dispatch to both services regardless.
 */
class InstagramCommentWebhookController extends Controller
{
    public function __construct(
        protected MetaPostService $postService,
        protected InstagramMessengerService $messengerService,
    ) {
    }

    public function verify(Request $request)
    {
        // messaging.instagram.* and posts.facebook.* are configured
        // separately even though they're normally the same underlying Meta
        // App - accept whichever verify token Meta was actually configured
        // with.
        $challenge = $this->postService->verifyWebhook($request)
            ?? $this->messengerService->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (!$this->postService->verifySignature($request) && !$this->messengerService->verifySignature($request)) {
            Log::warning('Instagram webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $payload = $request->all();

        $this->postService->handleCommentWebhook($payload, 'instagram');
        $this->messengerService->handleWebhook($payload);

        return response('EVENT_RECEIVED', 200);
    }
}
