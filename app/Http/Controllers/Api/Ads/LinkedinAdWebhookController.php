<?php

namespace App\Http\Controllers\Api\Ads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receiver for LinkedIn ad events (spend/impressions/clicks/conversions).
 *
 * LinkedIn DOES have a general webhook product (register the URL in the
 * "Webhooks" tab of the app in the developer portal, which is only enabled
 * for apps with an approved webhook use case), but it publishes NO event
 * type for ad delivery metrics - spend, impressions, clicks and conversions
 * are pull-only via the adAnalytics finder. So nothing will call this
 * endpoint under the r_ads/rw_ads/r_ads_reporting scopes this module
 * authenticates with. This route is what
 * LinkedinAdService::registerAdEventsCallback() records as the ad
 * account's "ad_events_callback_url" setting on connect, for an admin to
 * register by hand IF LinkedIn ever ships a push-capable ads product.
 *
 * The one Marketing-side webhook that does exist is Lead Sync: POST
 * /rest/leadNotifications creates a subscription against an owner, and the
 * owner may be a urn:li:sponsoredAccount - the exact entity this module
 * already stores as AdAccount.ad_account_id. It needs the separate
 * r_marketing_leadgen_automation scope and the Lead Sync API product, and
 * it pushes Lead Gen Form responses only, not delivery metrics.
 *
 * Note that if a real LinkedIn webhook is ever pointed here, this endpoint
 * must first answer LinkedIn's ownership challenge: a GET carrying a
 * ?challengeCode= query param, answered within 3 seconds with a 200 and
 * {"challengeCode": <code>, "challengeResponse": <hex HMAC-SHA256 of the
 * code, keyed by the app's clientSecret>}. LinkedIn re-validates roughly
 * every 2 hours and blocks the endpoint after 3 consecutive failures. Push
 * payloads then arrive with an X-LI-Signature header - the hex HMAC-SHA256
 * of ("hmacsha256=" + raw JSON body), also keyed by clientSecret - which
 * must be verified against the raw, un-reserialized body, and events must
 * be deduplicated by their notification ID. None of that is implemented
 * here because no ads event stream exists to implement it against.
 */
class LinkedinAdWebhookController extends Controller
{
    public function receive(Request $request)
    {
        Log::info('LinkedIn ad webhook event received.', ['payload' => $request->all()]);

        return response('EVENT_RECEIVED', 200);
    }
}
