<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\MessagingServices\TiktokMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * No GET verify() handshake here (unlike the Meta-family controllers) -
 * TikTok's webhook subscription is configured via an authenticated POST
 * to business/webhook/update/ (see TiktokMessagingService::
 * subscribeToWebhooks()), not a hub.challenge-style GET challenge.
 *
 * Every hit is recorded to WebhookLog - including a failed signature
 * check - so "is TikTok actually reaching this endpoint at all" and "is
 * my signature verification passing" are answerable with a query
 * (WebhookLog::where('platform','tiktok')->latest()->get()) instead of
 * grepping laravel.log. This was added specifically because there was no
 * other way to confirm live whether TikTok was calling this URL at all -
 * the log file has exactly one relevant line per event and no query
 * interface.
 */
class TiktokWebhookController extends Controller
{
    public function __construct(protected TiktokMessagingService $service)
    {
    }

    public function receive(Request $request)
    {
        $signatureValid = $this->service->verifySignature($request);

        if (!$signatureValid) {
            Log::warning('TikTok Business Messaging webhook signature mismatch.', ['ip' => $request->ip()]);

            WebhookLog::create([
                'platform'         => 'tiktok',
                'event_type'       => $request->input('event'),
                'signature_valid'  => false,
                'processed'        => false,
                'note'             => 'Signature verification failed - request rejected before handling.',
                'payload'          => $request->all(),
                'ip'               => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        $processed = $this->service->handleWebhook($request->all());

        WebhookLog::create([
            'platform'        => 'tiktok',
            'event_type'      => $request->input('event'),
            'signature_valid' => true,
            'processed'       => $processed,
            'note'            => $processed
                ? 'Message dispatched to ProcessInboundMessage.'
                : 'Signature OK, but not handled as a new message (non-message event, unparsable content, or unknown business_id - see laravel.log for which).',
            'payload'         => $request->all(),
            'ip'              => $request->ip(),
        ]);

        return response('OK', 200);
    }
}
