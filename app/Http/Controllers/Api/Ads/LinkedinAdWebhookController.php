<?php

namespace App\Http\Controllers\Api\Ads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receiver for LinkedIn ad events (spend/impressions/clicks/conversions).
 * LinkedIn's Marketing API has no self-serve push/webhook product for
 * these - reporting is pull-only via the adAnalytics finder - so there is
 * no real event stream that will ever call this endpoint under the
 * r_ads/rw_ads/r_ads_reporting scopes this module authenticates with (see
 * LinkedinAdService's class docblock). This route is what
 * LinkedinAdService::registerAdEventsCallback() records as the ad
 * account's "ad_events_callback_url" setting on connect, for an admin to
 * register by hand IF the org is later approved for a push-capable
 * LinkedIn product.
 */
class LinkedinAdWebhookController extends Controller
{
    public function receive(Request $request)
    {
        Log::info('LinkedIn ad webhook event received.', ['payload' => $request->all()]);

        return response('EVENT_RECEIVED', 200);
    }
}
