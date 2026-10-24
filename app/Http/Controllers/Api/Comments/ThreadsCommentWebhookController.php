<?php

namespace App\Http\Controllers\Api\Comments;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\PostServices\ThreadsReplyWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Threads reply webhook - see ThreadsReplyWebhookService. Every POST is kept in webhook_logs. */
class ThreadsCommentWebhookController extends Controller
{
    public function __construct(protected ThreadsReplyWebhookService $replies)
    {
    }

    public function verify(Request $request)
    {
        $challenge = $this->replies->verify($request);

        return $challenge !== null ? response($challenge, 200) : response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        $payload = $request->all();
        $log = [
            'platform' => 'threads',
            'event_type' => trim(($payload['topic'] ?? '') . '.' . ($payload['values']['field'] ?? ''), '.') ?: null,
            'payload' => $payload,
            'ip' => $request->ip(),
        ];

        if (! $this->replies->verifySignature($request)) {
            Log::warning('Threads webhook signature mismatch', ['ip' => $request->ip()]);
            WebhookLog::create($log + ['signature_valid' => false, 'processed' => false, 'note' => 'Signature verification failed - request rejected before handling.']);

            return response('Forbidden', 403);
        }

        $stored = $this->replies->handle($payload);
        WebhookLog::create($log + ['signature_valid' => true, 'processed' => $stored > 0, 'note' => $stored ? "Stored {$stored} reply(ies)." : 'No matching connected account, post or reply - nothing stored.']);

        return response('EVENT_RECEIVED', 200);
    }
}
