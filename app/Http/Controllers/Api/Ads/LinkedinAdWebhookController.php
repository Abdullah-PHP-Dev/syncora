<?php

namespace App\Http\Controllers\Api\Ads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receiver for LinkedIn ad events (spend/impressions/clicks/conversions).
 *
 * LinkedIn DOES have a general webhook product (register the URL in the
 * "Webhooks" tab of the app in the developer portal, which is only enabled
 * for apps with an approved webhook use case), but it publishes NO event
 * type for ad delivery metrics - spend, impressions, clicks and conversions
 * are pull-only via the adAnalytics finder. So no ad-metrics event will
 * ever arrive here under the r_ads/rw_ads/r_ads_reporting scopes this
 * module authenticates with. This route is what
 * LinkedinAdService::registerAdEventsCallback() records as the ad
 * account's "ad_events_callback_url" setting on connect, for an admin to
 * register by hand IF LinkedIn ever ships a push-capable ads product.
 *
 * The one Marketing-side webhook that does exist is Lead Sync: POST
 * /rest/leadNotifications creates a subscription against an owner, and the
 * owner may be a urn:li:sponsoredAccount - the exact entity this module
 * already stores as SocialAccount.platform_account_id. It needs the separate
 * r_marketing_leadgen_automation scope and the Lead Sync API product, and
 * it pushes Lead Gen Form responses only, not delivery metrics.
 *
 * Regardless of which event type (if any) is ever actually subscribed,
 * LinkedIn validates ownership of *any* URL entered in the developer
 * portal's "Webhooks" tab the moment it's registered, and re-validates it
 * every ~2 hours after - per LinkedIn's published Webhooks guide
 * (learn.microsoft.com/en-us/linkedin/shared/api-guide/webhook-validation):
 *
 *   - verify(): answers the GET ?challengeCode= ownership check. LinkedIn
 *     sends a random UUID as `challengeCode`; the app must return, within 3
 *     seconds, {"challengeCode": <code>, "challengeResponse": <response>}
 *     as JSON with a 200, where
 *     challengeResponse = hex(HMAC-SHA256(challengeCode, clientSecret)).
 *     3 consecutive failed re-validations blocks the endpoint. A
 *     applicationId query param is also sent for parent-child app setups
 *     (eg. Apply Connect) to select which child app's clientSecret to sign
 *     with - not applicable here, this app has no child apps, so it's
 *     ignored and the single ads.linkedin.client_secret is always used.
 *
 *   - receive(): every subsequent POST notification carries an
 *     X-LI-Signature header - hex(HMAC-SHA256("hmacsha256=" + <raw JSON
 *     body>, clientSecret)) - verified here with a constant-time
 *     comparison against the *raw*, un-reserialized request body (hashing
 *     $request->all() re-encoded would not match, since key order/spacing
 *     differs from what LinkedIn originally sent). Notifications are also
 *     deduplicated by their `id`/`notificationId` field via a 24h cache
 *     key, since LinkedIn documents that a notification can occasionally
 *     be delivered more than once.
 */
class LinkedinAdWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $challengeCode = $request->query('challengeCode');

        if (!$challengeCode) {
            return response('Missing challengeCode.', 400);
        }

        $clientSecret = adminSetting('ads.linkedin.client_secret');

        if (!$clientSecret) {
            Log::error('LinkedIn ad webhook validation requested, but ads.linkedin.client_secret is not configured.');
            return response('Webhook not configured.', 500);
        }

        $challengeResponse = hash_hmac('sha256', $challengeCode, $clientSecret);

        return response()->json([
            'challengeCode'     => $challengeCode,
            'challengeResponse' => $challengeResponse,
        ], 200);
    }

    public function receive(Request $request)
    {
        $clientSecret = adminSetting('ads.linkedin.client_secret');
        $rawBody = $request->getContent();
        $providedSignature = $request->header('X-LI-Signature');

        if (!$clientSecret || !$providedSignature) {
            Log::warning('LinkedIn ad webhook rejected: missing client secret or X-LI-Signature header.', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        $expectedSignature = hash_hmac('sha256', 'hmacsha256=' . $rawBody, $clientSecret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('LinkedIn ad webhook signature mismatch.', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        $payload = $request->all();
        $notificationId = $payload['notificationId'] ?? $payload['id'] ?? null;

        if ($notificationId) {
            $cacheKey = 'linkedin_ad_webhook:' . $notificationId;

            if (Cache::has($cacheKey)) {
                return response('EVENT_RECEIVED', 200);
            }

            Cache::put($cacheKey, true, now()->addDay());
        }

        Log::info('LinkedIn ad webhook event received.', ['payload' => $payload]);

        return response('EVENT_RECEIVED', 200);
    }
}
