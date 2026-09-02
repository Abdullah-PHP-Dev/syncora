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
 * granted to an existing app, not a separate app registration (confirmed
 * against this app's own TikTok Developer Portal screen: the same App ID
 * that has the Ads redirect URLs registered also has the Messaging
 * callback URLs registered alongside them).
 *
 * IMPORTANT, and the reason this class exists in its current form: the
 * AUTHORIZE step is NOT business-api.tiktok.com/portal/auth (the
 * Advertiser-authorization flow TiktokAdService uses) - it's TikTok's
 * consumer-facing Login Kit endpoint, www.tiktok.com/v2/auth/authorize/
 * (client_key + PKCE), the exact same domain/shape already proven working
 * in this codebase for TikTok content posting (see SocialAuthService::
 * redirectTiktok()). Sending users through the Advertiser flow instead
 * produced a code that business/tt_user/oauth2/token/ (correctly, this
 * endpoint IS right - verified via real request/response dumps) always
 * rejected as "expired", regardless of how quickly it was redeemed -
 * because it was never the right kind of code to begin with. "tt_user"
 * (TikTok User / Account Holder) vs. "advertiser" is the actual
 * distinction TikTok draws between these two authorize flows; Business
 * Messaging needs the Account Holder one, which is Login Kit's, even
 * though the token it's redeemed for is minted by the Business API
 * domain.
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

    /**
     * Login Kit's authorize endpoint - client_key + PKCE, matching
     * SocialAuthService::redirectTiktok() exactly (same working shape,
     * different scope). code_verifier is stashed in session (own key, so
     * connecting TikTok Messaging in one tab doesn't clobber a
     * concurrently in-progress TikTok posting connect using the same
     * PKCE mechanism in another) and read back in handleCallback().
     *
     * Scope: message.list.manage/send/read are the Business Messaging
     * scopes visible in TikTok's own docs (the tt_user/oauth2/token/
     * example response's scope list); user.info.basic is included for
     * basic identity, matching every other Login Kit scope request in
     * this app.
     */
    public function redirect($state)
    {
        $codeVerifier = bin2hex(random_bytes(32));
        session(['messaging_tiktok_code_verifier' => $codeVerifier]);

        $url = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key'            => adminSetting('ads.tiktok.client_id'),
            'response_type'         => 'code',
            'scope'                 => 'user.info.basic',
            'redirect_uri'          => $this->callbackUrl(),
            'state'                 => $state,
            'code_challenge'        => hash('sha256', $codeVerifier),
            'code_challenge_method' => 'S256',
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
        $codeVerifier = session('messaging_tiktok_code_verifier');
        session()->forget('messaging_tiktok_code_verifier');

        $tokenResponse = $this->apiService->post($this->base() . 'tt_user/oauth2/token/', ['Content-Type' => 'application/json'], [
            'client_id'     => (string) adminSetting('ads.tiktok.client_id'),
            'client_secret' => (string) adminSetting('ads.tiktok.client_secret'),
            'grant_type'    => 'authorization_code',
            'auth_code'     => $code,
            'redirect_uri'  => $this->callbackUrl(),
            // Not documented as a required field on this specific endpoint
            // (client_secret alone is normally enough to authenticate a
            // confidential client), but harmless to include since
            // redirect() now generates a real PKCE pair - and if TikTok
            // does check it for a Login-Kit-issued code, omitting it
            // would be the difference between working and not.
            'code_verifier' => $codeVerifier,
        ], 'json');
        if (!$tokenResponse['success'] || (int) ($tokenResponse['data']['code'] ?? -1) !== 0) {
            // ApiService::sendRequest() returns two different shapes on
            // failure: a real HTTP response (has 'data'/'body'/'status')
            // for anything TikTok itself answered, even a 4xx, OR - only
            // on a genuine connection-level exception (timeout, DNS, TLS)
            // - just ['success' => false, 'error' => $e->getMessage()],
            // no 'data' key at all. The original version of this method
            // only ever read $tokenResponse['data']['message'], so any
            // exception-path failure (or a TikTok error body without a
            // 'message' field) silently fell through to the generic
            // fallback below - hiding the real reason from both the user
            // and this log. Logged in full either way so a real failure
            // is diagnosable from storage/logs without needing to
            // reproduce it live (auth codes are single-use, 10-minute
            // validity - the exact request that failed can't be replayed).
            $reason = $tokenResponse['data']['message'] ?? $tokenResponse['error'] ?? null;

            Log::warning('TikTok Business Messaging token exchange failed.', [
                'status' => $tokenResponse['status'] ?? null,
                'body'   => $tokenResponse['body'] ?? ($tokenResponse['data'] ?? null),
                'error'  => $tokenResponse['error'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => $reason ?? 'Failed to exchange code for a TikTok access token.',
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
     * per-app state. event_type: 'DIRECT_MESSAGE' is the only value this
     * endpoint accepts (confirmed against TikTok's own doc page) - it's
     * what actually turns on delivery of im_receive_msg/im_receive_msg_eu
     * events (real user messages) to callback_url; without a successful
     * call here, TiktokWebhookController::receive() never gets hit at
     * all, no matter how correct its own handling is.
     *
     * This previously had zero response validation - a fire-and-forget
     * call whose failure (wrong scope, wrong domain, TikTok-side error)
     * would be completely invisible, the same blind spot handleCallback()
     * had before it got the same treatment. The outer try/catch in
     * handleCallback() only catches connection-level exceptions; a 200
     * response carrying an error code needs its own check, same pattern
     * as every other TikTok call in this class.
     *
     * DANGER - do not call this ad-hoc (eg. via tinker) from any
     * environment other than the one that should own the live webhook
     * right now: this is a single, app-wide TikTok-side setting keyed on
     * app_id/event_type, not scoped per environment in any way TikTok
     * can tell. route('messaging.webhook.tiktok.receive') resolves from
     * whichever APP_URL the calling environment has - running this
     * locally silently overwrites production's real webhook to point at
     * an unreachable dev URL, breaking live inbound message delivery
     * until someone notices and re-registers the right callback_url (this
     * happened once already testing this method - see git history).
     */
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
