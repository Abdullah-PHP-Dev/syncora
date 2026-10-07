<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Business Messaging API.
 *
 * Unlike most platforms here, TikTok has no general-purpose DM API a
 * regular app can just call - direct messages are only reachable through
 * this specific product, and only once TikTok has granted the developer
 * app the "Business Messaging" permission (a manual approval step on
 * TikTok's side, separate from having Ads API access - see
 * business-api.tiktok.com/portal/docs/business-messaging-api-get-started).
 * Everything below is implemented against endpoints/payloads verified
 * directly from that documentation (fetched via a rendering proxy, since
 * the portal is a JS SPA a plain fetch can't read) - not guessed. If
 * calls fail with a permission/scope error, that almost always means
 * Business Messaging hasn't been approved for this app yet, not a bug
 * here.
 *
 * Reuses the ads.tiktok.client_id/client_secret admin settings, same
 * developer app as TikTok Ads - Business Messaging is a permission
 * granted to an existing app, not a separate app registration (confirmed
 * against this app's own TikTok Developer Portal screen: the same App ID
 * that has the Ads redirect URLs registered also has the Messaging
 * callback URLs registered alongside them).
 *
 * IMPORTANT, and the reason this class exists in its current form: TikTok
 * has THREE distinct OAuth authorize flows sharing overlapping domains,
 * and getting the wrong one silently produces a code that looks fine but
 * always fails token exchange:
 *   1. Advertiser authorization (business-api.tiktok.com/portal/auth,
 *      app_id param) - TiktokAdService's flow, for the Marketing/Ads API.
 *      Code valid 1 hour.
 *   2. Login Kit (www.tiktok.com/v2/auth/authorize/, client_key + PKCE
 *      code_challenge) - SocialAuthService::redirectTiktok()'s flow, for
 *      consumer-facing content posting (video.publish etc.).
 *   3. TikTok account holder authorization (www.tiktok.com/v2/auth/
 *      authorize - note: no trailing slash, no PKCE - client_key only) -
 *      what THIS class uses. Confirmed via TikTok's own "Comparing
 *      authorization for different APIs" doc table: the Accounts API/
 *      Mentions API family (which is what tt_user/oauth2/token/ - this
 *      class's token endpoint - belongs to) authorizes "via TikTok
 *      organic account" through this specific flow, with a 10-minute,
 *      single-use code - exactly matching the "Authorization code is
 *      expired" failures every earlier attempt (first using flow #1,
 *      then incorrectly assuming flow #2) hit, regardless of how fast
 *      the code was redeemed, because neither was ever the right kind of
 *      code for tt_user/oauth2/token/ to accept.
 * redirect()'s exact URL shape (no trailing slash, no PKCE, and the scope
 * list) is copied from this app's own real, portal-generated "TikTok
 * account holder authorization URL" (My Apps > Basic Information, right
 * below "Advertiser authorization URL") - not assembled from docs
 * examples, which is exactly how the first two wrong attempts happened.
 */
class TiktokMessagingService
{
    public function __construct(protected ApiService $apiService)
    {
    }

    private function base(): string
    {
        return adminSetting('ads.tiktok.base_url') ?: 'https://business-api.tiktok.com/open_api/v1.3/';
    }


    // The TikTok DM connect flow (redirect / handleCallback, audit flow #14)
    // was removed: it never received a messaging scope. Connecting returns
    // as a TikTok for Business step in the Connection Hub once Business
    // Messaging is approved (flag tiktok.business_messaging).

    /**
     * Whether TikTok's granted scope string (comma- or space-separated)
     * includes a direct-message scope. TikTok doesn't publish the
     * Business Messaging scope names outside the approved-app portal, so
     * this matches the messaging families by prefix (biz.dm.* - the one
     * this app's portal lists once approved - plus dm./im./message.)
     * rather than one exact string.
     */
    public static function grantsMessaging(?string $scope): bool
    {
        foreach (preg_split('/[\s,]+/', (string) $scope, -1, PREG_SPLIT_NO_EMPTY) as $granted) {
            if (preg_match('/^(biz\.dm\.|biz\.message|dm\.|im\.|message\.)/i', trim($granted))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Profile of the connected TikTok account (Accounts API, same token
     * family as Business Messaging): GET business/get/ with business_id +
     * a JSON-array 'fields' param. Returns [] on any failure.
     *
     * @return array{display_name?: string, username?: string, profile_image?: string}
     */
    public function fetchBusinessProfile(string $accessToken, string $businessId): array
    {
        try {
            $response = $this->apiService->get($this->base() . 'business/get/', ['Access-Token' => $accessToken], [
                'business_id' => $businessId,
                'fields'      => json_encode(['display_name', 'username', 'profile_image']),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TikTok business profile fetch failed.', ['error' => $e->getMessage()]);

            return [];
        }

        if (!$response['success'] || (int) ($response['data']['code'] ?? -1) !== 0) {
            Log::warning('TikTok business profile fetch failed.', ['body' => $response['data'] ?? ($response['error'] ?? null)]);

            return [];
        }

        $profile = $response['data']['data'] ?? [];

        return array_filter([
            'display_name'  => $profile['display_name'] ?? null,
            'username'      => $profile['username'] ?? null,
            'profile_image' => $profile['profile_image'] ?? null,
        ]);
    }

    public function subscribeToWebhooks(): void
    {
        $response = $this->apiService->post($this->base() . 'business/webhook/update/', ['Content-Type' => 'application/json'], [
            'app_id'       => (string) adminSetting('ads.tiktok.client_id'),
            'secret'       => (string) adminSetting('ads.tiktok.client_secret'),
            'event_type'   => 'DIRECT_MESSAGE',
            'callback_url' => route('messaging.webhook.tiktok.receive'),
        ], 'json');

        if (!$response['success'] || (int) ($response['data']['code'] ?? -1) !== 0) {
            Log::warning('TikTok Business Messaging webhook registration failed - inbound messages will not be delivered.', [
                'status' => $response['status'] ?? null,
                'body'   => $response['body'] ?? ($response['data'] ?? null),
                'error'  => $response['error'] ?? null,
            ]);

            return;
        }

        Log::info('TikTok Business Messaging webhook registered.', ['callback_url' => route('messaging.webhook.tiktok.receive')]);
    }

    /**
     * Pulls the most recent SINGLE (already-accepted, not STRANGER/
     * request) conversations and, for each, its last few messages - the
     * same "recent history on first connect" role backfillRecentPosts/
     * backfillRecentConversations play for every other platform. Message
     * ordering within each conversation isn't guaranteed by the API
     * (max 20 most recent per the docs), so ProcessInboundMessage's own
     * external_message_id dedup is what keeps this idempotent if this
     * ever runs twice for the same channel.
     */
    public function backfillRecentConversations(MessageChannel $channel, int $conversationLimit = 10): void
    {
        $accessToken = $channel->socialAccount->access_token;

        $listResponse = $this->apiService->get($this->base() . 'business/message/conversation/list/', ['Access-Token' => $accessToken], [
            'business_id'       => $channel->external_id,
            'conversation_type' => 'SINGLE',
            'limit'             => $conversationLimit,
        ]);

        if (!$listResponse['success'] || (int) ($listResponse['data']['code'] ?? -1) !== 0) {
            Log::warning('TikTok conversation list fetch failed during backfill.', ['channel_id' => $channel->id, 'body' => $listResponse['data'] ?? null]);
            return;
        }

        foreach ($listResponse['data']['data']['conversations'] ?? [] as $conv) {
            $conversationId = $conv['conversation_id'] ?? null;

            if (!$conversationId) {
                continue;
            }

            $messagesResponse = $this->apiService->get($this->base() . 'business/message/content/list/', ['Access-Token' => $accessToken], [
                'business_id'      => $channel->external_id,
                'conversation_id'  => $conversationId,
            ]);

            if (!$messagesResponse['success'] || (int) ($messagesResponse['data']['code'] ?? -1) !== 0) {
                continue;
            }

            foreach ($messagesResponse['data']['data']['messages'] ?? [] as $message) {
                // Skip our own sent messages - they got a local row at
                // send time already, and would otherwise show up here as
                // a duplicate "from" the business account itself.
                if (($message['from_user']['role'] ?? null) === 'BUSINESS_ACCOUNT') {
                    continue;
                }

                ProcessInboundMessage::dispatch(
                    socialAccountId: $channel->social_account_id,
                    customerExternalId: $message['from_user']['id'] ?? ($message['sender'] ?? $conversationId),
                    customerName: $message['sender'] ?? null,
                    externalConversationId: $conversationId,
                    externalMessageId: $message['message_id'] ?? null,
                    body: $message['text']['body'] ?? null,
                );
            }
        }
    }

    /**
     * message_type TEXT/recipient_type CONVERSATION/text.body, and the
     * response envelope shape (code/message/data.message.message_id) -
     * all verified against the "Send a message to a conversation" doc
     * page. Media (IMAGE) needs a separate "Upload an image" call first
     * to get a media_id - left out for now, same call this session made
     * for X DMs (see XMessagingService::sendMessage) rather than half-
     * build a second upload pipeline; the media URL is appended to the
     * text instead of being silently dropped.
     */
    public function sendMessage(Conversation $conversation, array $data)
    {
        // Conversation::channel() actually returns the SocialAccount (has
        // access_token directly on it), not a MessageChannel - confirmed
        // live via a real send test, which is also how this got caught:
        // the first version of this method read $channel->external_id and
        // $channel->socialAccount->access_token, both wrong for what
        // ->channel actually resolves to. external_id (this platform's
        // business_id) lives one hop further via ->messageChannel, per
        // Conversation::channel()'s own docblock.
        $account = $conversation->channel;
        $businessId = $account->messageChannel->external_id ?? null;

        $body = $data['body'] ?? '';
        if (!empty($data['media_url'])) {
            $body = trim($body . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post($this->base() . 'business/message/send/', [
            'Access-Token' => $account->access_token,
            'Content-Type' => 'application/json',
        ], [
            'business_id'    => $businessId,
            'message_type'   => 'TEXT',
            'recipient_type' => 'CONVERSATION',
            'recipient'      => $conversation->external_conversation_id,
            'text'           => ['body' => $body],
        ], 'json');

        if (!$response['success'] || (int) ($response['data']['code'] ?? -1) !== 0) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'TikTok Business Messaging API request failed.'];
        }

        return [
            'success'             => true,
            'external_message_id' => $response['data']['data']['message']['message_id'] ?? null,
        ];
    }

    /**
     * TikTok-Signature: "t=<unix_timestamp>,s=<hex_hmac>" - the hashed
     * material is "{timestamp}.{raw_json_body}", HMAC-SHA256 keyed with
     * the app's client_secret. Verified against TikTok's own webhook
     * signature verification guide (developers.tiktok.com/doc/webhooks-
     * verification) - same shape as Meta's X-Hub-Signature-256 elsewhere
     * in this codebase (MetaMessagingTrait::verifyMetaSignature), just a
     * different header format and a timestamp folded into the signed
     * material instead of the raw body alone.
     */
    public function verifySignature(Request $request): bool
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

        $expected = hash_hmac('sha256', $parts['t'] . '.' . $request->getContent(), (string) adminSetting('ads.tiktok.client_secret'));

        return hash_equals($expected, $parts['s']);
    }

    /**
     * im_receive_msg's top-level envelope wraps the actual message as a
     * JSON *string* in `content` (not a nested object) - confirmed
     * against TikTok's own webhook subscription doc's example payload.
     * im_receive_msg_eu (EEA/Switzerland/UK senders) carries deliberately
     * reduced data per TikTok's privacy rules for that region and isn't
     * handled differently here - whatever fields it omits just come
     * through as null, same as any other platform's optional fields.
     *
     * Returns bool (was void) - true only when a message was actually
     * dispatched, false on every early-return path - purely so
     * TiktokWebhookController::receive() can record an accurate
     * WebhookLog.processed flag without duplicating this method's own
     * decision logic. Only caller is that controller (confirmed via a
     * full grep before this change), so widening the return type is
     * safe - nothing else depends on this staying void.
     */
    public function handleWebhook(array $payload): bool
    {
        // Both early returns below used to be silent - exactly the kind
        // of gap this class kept turning out to have (token exchange,
        // webhook registration, both already fixed the same way). If
        // real DM events are arriving but never showing up as Messages,
        // these two log lines are what tell us which stage is actually
        // failing, rather than guessing again.
        if (($payload['event'] ?? null) !== 'im_receive_msg' && ($payload['event'] ?? null) !== 'im_receive_msg_eu') {
            Log::info('TikTok webhook received a non-message event (or unrecognized event field) - ignored.', ['event' => $payload['event'] ?? null, 'payload_keys' => array_keys($payload)]);
            return false;
        }

        $content = json_decode($payload['content'] ?? '', true);

        if (!is_array($content)) {
            Log::warning('TikTok webhook im_receive_msg had an unparsable content field.', ['raw' => $payload['content'] ?? null]);
            return false;
        }

        $businessId = $content['to_user']['id'] ?? $payload['user_openid'] ?? null;
        $channel = $businessId ? MessageChannel::where('platform', 'tiktok')->where('external_id', $businessId)->first() : null;

        if (!$channel) {
            Log::warning('TikTok webhook message arrived for a business_id with no matching connected channel - dropped.', [
                'business_id' => $businessId,
                'known_tiktok_external_ids' => MessageChannel::where('platform', 'tiktok')->pluck('external_id'),
            ]);
            return false;
        }

        ProcessInboundMessage::dispatch(
            socialAccountId: $channel->social_account_id,
            customerExternalId: $content['from_user']['id'] ?? $content['from'] ?? 'unknown',
            customerName: $content['from'] ?? null,
            externalConversationId: $content['conversation_id'] ?? null,
            externalMessageId: $content['message_id'] ?? null,
            body: $content['text']['body'] ?? null,
        );

        return true;
    }
}
