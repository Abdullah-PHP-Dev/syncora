<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TikTok's single, fixed app-level webhook for the Content Posting /
 * Login Kit app (posts.tiktok.client_id/client_secret) - pasted by hand
 * into TikTok Developer Portal > this app > Webhooks, not registered via
 * an API call (unlike TiktokMessagingService::subscribeToWebhooks(),
 * which POSTs to business/webhook/update/ for the separate Business API
 * app that owns messaging.webhook.tiktok.receive / TiktokWebhookController
 * in Api/Messaging - a different TikTok app with its own client_key/
 * client_secret, see that service's own docblock).
 *
 * Per developers.tiktok.com/docs/en/webhooks-events, TikTok only ever
 * sends four event types, and only one is relevant to what this app does
 * with the Content Posting API:
 *
 * - authorization.removed: fires when a user's access token is revoked
 *   on TikTok's side without this app being told through its own UI -
 *   disconnecting from inside TikTok itself, the account getting
 *   banned/deleted, or its age changing. Handled below: marks the
 *   matching SocialAccount invalid so a later publish/token-refresh
 *   attempt fails fast with a clear "reconnect" state instead of
 *   repeatedly hitting TikTok with a token that's already dead, and
 *   drops it from the posting-account picker (PostController::composer()/
 *   create() filter on has_posting_permission).
 * - video.upload.failed / video.publish.completed: confirmed directly
 *   against TikTok's own docs to be scoped to "Video Kit" (their
 *   client-side share-from-your-app SDK) - a different product from the
 *   server-to-server Direct Post (PULL_FROM_URL) flow TiktokPostService
 *   actually uses. This app never triggers a Video Kit upload, so these
 *   two will never fire for it - logged if TikTok ever sends one anyway,
 *   not acted on.
 * - portability.download.ready: Data Portability API - unrelated to
 *   anything this app integrates with. Logged only.
 *
 * There is deliberately no event handled here for Content Posting
 * publish completion itself - TikTok's own Content Posting API guide
 * states polling post/publish/status/fetch/ is the only way to track
 * that, which is exactly what ResolveTiktokPublishStatus already does.
 * TikTok does not offer a webhook for it.
 */
class TiktokContentWebhookController extends Controller
{
    public function receive(Request $request)
    {
        if ($request->isMethod('get')) {
            return response('TikTok Content Posting webhook endpoint reachable. Real events arrive via POST only - this GET response is not logged.', 200);
        }

        if (!$this->hasValidSignature($request)) {
            Log::warning('TikTok content-posting webhook signature mismatch.', ['ip' => $request->ip()]);

            WebhookLog::create([
                'platform'        => 'tiktok_content',
                'event_type'      => $request->input('event'),
                'signature_valid' => false,
                'processed'       => false,
                'note'            => 'Signature verification failed - request rejected before handling.',
                'payload'         => $request->all(),
                'ip'              => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $note = null;

        switch ($event) {
            case 'authorization.removed':
                $note = $this->handleAuthorizationRemoved($payload);
                break;

            case 'video.upload.failed':
            case 'video.publish.completed':
                $note = "{$event} is a Video Kit event - this app's Content Posting integration never triggers a Video Kit upload, so this shouldn't be reachable. Logged only.";
                Log::info("TikTok content-posting webhook received a {$event} event.", ['payload' => $payload]);
                break;

            case 'portability.download.ready':
                $note = 'Data Portability API event - unrelated to this app\'s TikTok integration. Logged only.';
                Log::info('TikTok content-posting webhook received a portability.download.ready event.', ['payload' => $payload]);
                break;

            default:
                $note = 'Unrecognized event type.';
                Log::info('TikTok content-posting webhook received an unrecognized event.', ['event' => $event, 'payload' => $payload]);
        }

        WebhookLog::create([
            'platform'        => 'tiktok_content',
            'event_type'      => $event,
            'signature_valid' => true,
            'processed'       => $event === 'authorization.removed',
            'note'            => $note,
            'payload'         => $payload,
            'ip'              => $request->ip(),
        ]);

        return response('OK', 200);
    }

    /**
     * developers.tiktok.com/docs/en/webhooks-verification: header is
     * "Tiktok-Signature: t={timestamp},s={signature}" where signature =
     * HMAC-SHA256("{t}.{raw_json_body}", client_secret). Uses the
     * Content Posting app's own secret (posts.tiktok.client_secret) -
     * not ads.tiktok.client_secret, which belongs to the separate
     * Business API app TiktokMessagingService::verifySignature()
     * verifies against. Same shape as that method, duplicated rather
     * than shared since the two are genuinely different TikTok app
     * registrations with different secrets.
     */
    private function hasValidSignature(Request $request): bool
    {
        $header = $request->header('Tiktok-Signature', '');
        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $parts[$key] = $value;
        }

        if (empty($parts['t']) || empty($parts['s'])) {
            return false;
        }

        $expected = hash_hmac('sha256', $parts['t'] . '.' . $request->getContent(), (string) adminSetting('posts.tiktok.client_secret'));

        return hash_equals($expected, $parts['s']);
    }

    /**
     * user_openid is TikTok's own account id for this app - exactly
     * what this app already stores as SocialAccount.platform_account_id
     * for a TikTok Content Posting connection (see
     * SocialAuthService::callbackTiktok()), so no lookup translation is
     * needed. Reason codes per TikTok's docs: 0 unknown, 1 user
     * disconnected the app, 2 account deleted, 3 account age changed,
     * 4 account banned, 5 developer revoked authorization.
     */
    private function handleAuthorizationRemoved(array $payload): string
    {
        $userOpenId = $payload['user_openid'] ?? null;
        $content = json_decode($payload['content'] ?? '{}', true) ?? [];
        $reason = $content['reason'] ?? null;

        $reasons = [
            0 => 'an unknown reason',
            1 => 'the user disconnecting the app from TikTok',
            2 => "the user's TikTok account being deleted",
            3 => "a change to the user's account age",
            4 => "the user's TikTok account being banned",
            5 => 'the developer revoking authorization',
        ];
        $reasonText = $reasons[$reason] ?? "reason code {$reason}";

        $account = SocialAccount::where('platform', 'tiktok')
            ->where('platform_account_id', $userOpenId)
            ->first();

        if (!$account) {
            Log::info('TikTok authorization.removed received for an account not connected here - ignored.', ['user_openid' => $userOpenId]);

            return "No matching SocialAccount for user_openid {$userOpenId} - nothing to update.";
        }

        $account->update([
            'is_token_valid'         => false,
            'has_posting_permission' => false,
        ]);

        Log::warning('TikTok account disconnected via authorization.removed webhook.', [
            'account_id' => $account->id,
            'reason'     => $reasonText,
        ]);

        return "SocialAccount #{$account->id} marked invalid - authorization removed due to {$reasonText}.";
    }
}
