<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * X Account Activity API webhook - real-time DM delivery for whichever
 * connected accounts XMessagingService::subscribeAccountActivity()
 * successfully subscribed (capped at 3 total app-wide on the "Pay Per
 * Use" tier - see that method's docblock). Every other connected
 * account keeps getting DMs via PollXDirectMessages instead; this
 * controller existing doesn't replace that command, both run.
 *
 * Two responsibilities on the same URL, split by HTTP method (see
 * routes/api.php): GET is X's periodic CRC (Challenge-Response Check)
 * re-validation, POST is real event delivery.
 */
class XActivityWebhookController extends Controller
{
    public function __construct(protected XMessagingService $service)
    {
    }

    /**
     * X calls this with ?crc_token=... both once when the webhook is
     * first registered and periodically afterward - a webhook that fails
     * to answer correctly gets automatically disabled on X's side.
     */
    public function crc(Request $request)
    {
        $crcToken = $request->query('crc_token');

        if (!$crcToken) {
            return response()->json(['error' => 'Missing crc_token'], 400);
        }

        return response()->json([
            'response_token' => $this->service->crcResponseToken($crcToken),
        ]);
    }

    /**
     * Real event delivery. Logged to WebhookLog the same way
     * TiktokWebhookController does - including a failed signature check
     * - so "is X actually reaching this endpoint" and "is my signature
     * verification passing" are one query away instead of grepping
     * laravel.log.
     */
    public function receive(Request $request)
    {
        $signatureValid = $this->service->verifySignature($request);

        if (!$signatureValid) {
            Log::warning('X Account Activity webhook signature mismatch.', ['ip' => $request->ip()]);

            WebhookLog::create([
                'platform'        => 'x',
                'event_type'      => 'direct_message_events',
                'signature_valid' => false,
                'processed'       => false,
                'note'            => 'Signature verification failed - request rejected before handling.',
                'payload'         => $request->all(),
                'ip'              => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        $processed = $this->service->handleWebhook($request->all());

        WebhookLog::create([
            'platform'        => 'x',
            'event_type'      => 'direct_message_events',
            'signature_valid' => true,
            'processed'       => $processed,
            'note'            => $processed
                ? 'Message dispatched to ProcessInboundMessage.'
                : 'Signature OK, but not handled as a new message (no direct_message_events in payload, an echo of our own send, or an unrecognized for_user_id - see laravel.log for which).',
            'payload'         => $request->all(),
            'ip'              => $request->ip(),
        ]);

        return response('OK', 200);
    }
}
