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
 * subscribeToWebhooks()), not a hub.challenge-style GET challenge. The
 * route itself accepts GET too (Route::match(['get','post'], ...) in
 * routes/api.php) purely so this URL can be opened directly in a browser
 * as a manual reachability check - TikTok itself only ever sends POST.
 *
 * Every REAL (POST) hit is recorded to WebhookLog - including a failed
 * signature check - so "is TikTok actually reaching this endpoint at
 * all" and "is my signature verification passing" are answerable with a
 * query (WebhookLog::where('platform','tiktok')->latest()->get())
 * instead of grepping laravel.log. A GET is short-circuited before any
 * of that: it isn't a real delivery attempt (a browser sends no
 * Tiktok-Signature header and no event body), so running it through
 * signature verification would always "fail" and letting that write a
 * WebhookLog row made every manual browser check indistinguishable from
 * a genuine failed delivery - which defeated the entire point of this
 * log (confirmed live: this is exactly what happened when the GET method
 * was added to the route without this guard).
 */
class TiktokWebhookController extends Controller
{
    public function __construct(protected TiktokMessagingService $service)
    {
    }

    public function receive(Request $request)
    {
        if ($request->isMethod('get')) {
            return response('TikTok Business Messaging endpoint reachable. Real events arrive via POST only - this GET response is not logged.', 200);
        }

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
