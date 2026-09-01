<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

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
 * granted to an existing app, not a separate app registration.
 *
 * Important: this uses a DIFFERENT token-exchange endpoint than
 * TiktokAdService (tt_user/oauth2/token/ with client_id/client_secret,
 * not oauth2/access_token/ with app_id/secret) - confirmed from TikTok's
 * own Business Messaging docs. The authorize step (business-api.tiktok.
 * com/portal/auth) is the same for both products.
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

    /**
     * TikTok's account-holder redirect URL docs read as requiring a
     * trailing "/" - the real registered "Advertiser redirect URLs" in
     * the Developer Portal (confirmed against a live screenshot of this
     * app's own app config) do NOT have one, and TikTok rejects the
     * request ("redirect URI does not match") when this doesn't match
     * one of those entries character-for-character. The portal, not the
     * docs prose, is the source of truth here.
     */
    private function callbackUrl(): string
    {
        // oauthCallbackUrl() reverse-resolves the path from routes/web.php
        // and strips the locale prefix a bare route() call would add (see
        // app/Helpers/Helper.php).
        return oauthCallbackUrl('admin.messaging.auth.tiktok.callback');
    }

    public function redirect($state)
    {
        $url = 'https://business-api.tiktok.com/portal/auth?' . http_build_query([
            'app_id'       => adminSetting('ads.tiktok.client_id'),
            'state'        => $state,
            'redirect_uri' => $this->callbackUrl(),
        ]);

        return Redirect::away($url);
    }

    /**
     * business_id (required on every Business Messaging call below) is
     * literally the open_id this endpoint returns - confirmed verbatim on
     * the "Get a list of conversations" doc page ("This value comes from
     * the open_id field returned in the response from the
     * /tt_user/oauth2/token/ endpoint"), not a separate lookup.
     */
    public function handleCallback(string $code): array
    {
        $tokenResponse = $this->apiService->post($this->base() . 'tt_user/oauth2/token/', ['Content-Type' => 'application/json'], [
            'client_id'     => (string) adminSetting('ads.tiktok.client_id'),
            'client_secret' => (string) adminSetting('ads.tiktok.client_secret'),
            'grant_type'    => 'authorization_code',
            'auth_code'     => $code,
            'redirect_uri'  => $this->callbackUrl(),
        ], 'json');

        if (!$tokenResponse['success'] || (int) ($tokenResponse['data']['code'] ?? -1) !== 0) {
            return [
                'success' => false,
                'error' => $tokenResponse['data']['message'] ?? 'Failed to exchange code for a TikTok access token.',
            ];
        }

        $data = $tokenResponse['data']['data'] ?? [];
        $accessToken = $data['access_token'] ?? null;
        $businessId = $data['open_id'] ?? null;

        if (!$accessToken || !$businessId) {
            return ['success' => false, 'error' => 'TikTok did not return an access token or business account id.'];
        }

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'tiktok', 'platform_account_id' => 'msg_' . $businessId, 'user_id' => Auth::id()],
            [
                'name'                     => 'TikTok Business Account',
                'account_type'             => 'business_messaging',
                'access_token'             => $accessToken,
                'refresh_token'            => $data['refresh_token'] ?? null,
                'is_token_valid'           => true,
                'expires_at'               => Carbon::now()->addSeconds($data['expires_in'] ?? 86400),
                'has_messaging_permission' => true,
                'metadata'                 => ['scope' => $data['scope'] ?? null],
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'tiktok', 'external_id' => $businessId],
            ['social_account_id' => $account->id]
        );

        // Best-effort, same "don't let a secondary call block the primary
        // connect" pattern used throughout SocialAuthService::
        // callbackFacebook() - the channel/account above are already saved
        // either way.
        try {
            $this->subscribeToWebhooks();
        } catch (\Throwable $e) {
            Log::warning('TikTok Business Messaging webhook subscribe failed after connect.', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
        }
        try {
            $this->backfillRecentConversations($channel);
        } catch (\Throwable $e) {
            Log::warning('TikTok Business Messaging conversation backfill failed after connect.', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'data' => $channel];
    }

    /**
     * business/webhook/update/ is app-level, not per-account (confirmed:
     * it's authenticated with app_id/secret, no Access-Token/business_id
     * involved) - one call covers every TikTok Business Account connected
     * through this app, so it's safe (and cheap) to call again on every
     * new connect rather than trying to track "already subscribed"
     * per-app state.
     */
    public function subscribeToWebhooks(): void
    {
        $this->apiService->post($this->base() . 'business/webhook/update/', ['Content-Type' => 'application/json'], [
            'app_id'       => (string) adminSetting('ads.tiktok.client_id'),
            'secret'       => (string) adminSetting('ads.tiktok.client_secret'),
            'event_type'   => 'DIRECT_MESSAGE',
            'callback_url' => route('messaging.webhook.tiktok.receive'),
        ], 'json');
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
     */
    public function handleWebhook(array $payload): void
    {
        if (($payload['event'] ?? null) !== 'im_receive_msg' && ($payload['event'] ?? null) !== 'im_receive_msg_eu') {
            return;
        }

        $content = json_decode($payload['content'] ?? '', true);

        if (!is_array($content)) {
            Log::warning('TikTok webhook im_receive_msg had an unparsable content field.', ['raw' => $payload['content'] ?? null]);
            return;
        }

        $businessId = $content['to_user']['id'] ?? $payload['user_openid'] ?? null;
        $channel = $businessId ? MessageChannel::where('platform', 'tiktok')->where('external_id', $businessId)->first() : null;

        if (!$channel) {
            return;
        }

        ProcessInboundMessage::dispatch(
            socialAccountId: $channel->social_account_id,
            customerExternalId: $content['from_user']['id'] ?? $content['from'] ?? 'unknown',
            customerName: $content['from'] ?? null,
            externalConversationId: $content['conversation_id'] ?? null,
            externalMessageId: $content['message_id'] ?? null,
            body: $content['text']['body'] ?? null,
        );
    }
}
