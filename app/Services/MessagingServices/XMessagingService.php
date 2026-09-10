<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\WebhookLog;

/**
 * X (Twitter) Direct Messages - X API v2 (api.x.com/2/), a different
 * product/surface from the Ads API this app's ad campaign module talks to,
 * with different (simpler) auth: OAuth 2.0 user-context with PKCE and a
 * plain Bearer token, not OAuth 1.0a HMAC request signing.
 *
 * Real-time delivery IS available, via the Account Activity API's
 * webhook mechanism - contrary to what this docblock previously said
 * (Enterprise/Premium-only, "not realistically obtainable"). It's
 * available on the "Pay Per Use" tier too, capped at 3 total
 * subscriptions app-wide (Enterprise removes that cap) - see
 * subscribeAccountActivity()'s docblock. handleCallback() registers the
 * shared app-level webhook once (registerWebhookIfNeeded()) and
 * subscribes each newly-connected account to it (subscribeAccountActivity())
 * on a best-effort basis; XActivityWebhookController receives the actual
 * events. Scheduled polling (see PollXDirectMessagesCommand, GET
 * /2/dm_events, each channel's stored pagination cursor in
 * message_channels.meta.pagination_token) still runs unconditionally
 * alongside this for every connected account regardless of
 * webhook_subscribed - real-time for whichever accounts fit under the
 * subscription cap, ~1-minute-latency polling for every account
 * (including those, as a safety net that costs nothing to leave running).
 *
 * Endpoints verified live this session via developer.x.com AND a working
 * reference implementation of this exact feature in another project:
 * POST /2/dm_conversations/with/:participant_id/messages (new 1:1
 * conversation), POST /2/dm_conversations/:id/messages (existing
 * conversation), GET /2/dm_events (polling, paginated, events up to 30
 * days old), POST /2/webhooks + GET /2/webhooks (app-level webhook
 * register/list), POST /2/activity/subscriptions (the actual DM-specific
 * subscription call - NOT /2/account_activity/webhooks/{id}/
 * subscriptions/all, which does not enable DM delivery despite being
 * what docs.x.com/x-api/account-activity/create-subscription describes -
 * see subscribeAccountActivity()'s docblock for how that was found).
 */
class XMessagingService
{
    private string $base;

    public function __construct(protected ApiService $apiService)
    {
        // Falls back to the real, hardcoded X API v2 URLs wherever the
        // matching admin_settings row is empty/missing - confirmed live
        // on labs.socialeaz.com that an empty messaging.x.authorize_url
        // produced a URL starting with a bare "?", which Redirect::away()
        // sends as a *relative* Location header the browser resolves
        // against the current page instead of leaving it, landing back on
        // this app's own /admin/messaging/auth/x/redirect with every
        // OAuth param still attached. (This fallback was present once
        // already and appears to have been lost in a later deploy -
        // re-adding it here.)
        $this->base = adminSetting('posts.x.base_url') ?: 'https://api.x.com/2/';
    }

    // oauthCallbackUrl() reverse-resolves from routes/web.php and strips
    // the locale prefix a bare route() call would add (see
    // app/Helpers/Helper.php). Was previously two separately hand-typed
    // config('services.app_url') . '...' strings (redirect() and
    // handleCallback() each had their own copy), which is exactly the
    // kind of drift risk this replaces.
    private function callbackUrl(): string
    {
        return oauthCallbackUrl('admin.messaging.auth.x.callback');
    }

    /**
     * HTTP Basic Auth (client_secret_basic) - the X Developer Console
     * screenshot confirms this app is registered as "Web App, Automated
     * App or Bot" (Confidential client), not "Native App" (Public
     * client). A confidential client's token endpoint calls MUST
     * authenticate with client_secret - PKCE's code_verifier alone
     * (which this class already sends) only proves possession of the
     * authorization request, it doesn't substitute for client
     * authentication on a confidential client. Neither token-exchange
     * call in this class sent client_secret anywhere until now, which
     * would fail token exchange with an invalid_client-style error the
     * moment a user actually got past X's consent screen. Basic Auth
     * (RFC 6749 2.3.1) is X's documented method for this, same as every
     * other confidential-client OAuth 2.0 provider.
     */
    private function basicAuthHeader(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode(
                adminSetting('posts.x.client_id') . ':' . adminSetting('posts.x.client_secret')
            ),
        ];
    }

    /**
     * OAuth 2.0 Authorization Code + PKCE - kicks off the connect flow for
     * a new channel.
     */
    public function redirect($state)
    {
        $codeVerifier = Str::random(64);
        session(['x_messaging_code_verifier' => $codeVerifier]);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $url = (adminSetting('posts.x.authorize_url') ?: 'https://x.com/i/oauth2/authorize') . '?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => adminSetting('posts.x.client_id'),
            'redirect_uri'          => $this->callbackUrl(),
            'scope'                 => 'dm.read dm.write tweet.read users.read offline.access',
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return Redirect::away($url);
    }

    /**
     * Exchanges the authorization code (+ the PKCE verifier stashed in the
     * session by redirect()) for an access/refresh token pair, then looks
     * up the authenticated user's own numeric ID via GET /2/users/me -
     * needed both as message_channels.external_id and to let
     * pollMessages() recognize (and skip) echoes of our own sent DMs.
     */
    public function handleCallback(string $code): array
    {
        $codeVerifier = session('x_messaging_code_verifier');

        if (!$codeVerifier) {
            return ['success' => false, 'error' => 'Missing PKCE code verifier - please restart the connection flow.'];
        }

        $tokenResponse = $this->apiService->post(adminSetting('posts.x.token_url') ?: 'https://api.x.com/2/oauth2/token', $this->basicAuthHeader(), [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => adminSetting('posts.x.client_id'),
            'redirect_uri'  => $this->callbackUrl(),
            'code_verifier' => $codeVerifier,
        ], 'form');

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for an X access token.'];
        }

        $accessToken = $tokenResponse['data']['access_token'];

        $userResponse = $this->apiService->get($this->base . 'users/me', ['Authorization' => "Bearer {$accessToken}"], [
            'user.fields' => 'profile_image_url,username,name',
        ]);

        if (!$userResponse['success']) {
            return ['success' => false, 'error' => 'Connected, but failed to fetch the X account profile.'];
        }

        $user = $userResponse['data']['data'];

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'x', 'platform_account_id' => $user['id'], 'user_id' => \Illuminate\Support\Facades\Auth::id()],
            [
                'name'                     => $user['name'] ?? $user['username'],
                'username'                 => $user['username'] ?? null,
                'avatar_url'               => $user['profile_image_url'] ?? null,
                'access_token'             => $accessToken,
                'refresh_token'            => $tokenResponse['data']['refresh_token'] ?? null,
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'x', 'external_id' => $user['id']],
            [
                'social_account_id' => $account->id,
                'expires_at'        => Carbon::now()->addSeconds($tokenResponse['data']['expires_in'] ?? 7200),
            ]
        );

        session()->forget('x_messaging_code_verifier');

        // Best-effort, same "don't let a secondary call block the primary
        // connect" pattern used throughout this app (TikTok's
        // subscribeToWebhooks(), Facebook/Instagram Messenger's own
        // per-page subscribe calls) - the account is already fully
        // connected and usable via PollXDirectMessages either way, real-
        // time delivery is a bonus on top, not a requirement to finish
        // connecting.
        try {
            $this->subscribeAccountActivity($channel, $accessToken);
        } catch (\Throwable $e) {
            Log::warning('X Account Activity webhook subscribe failed after connect.', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'data' => $channel];
    }

    /**
     * App-only OAuth 2.0 Bearer Token - needed to manage webhooks
     * (GET/POST /2/webhooks), which are app-level, not per-user.
     *
     * The genuinely correct credential pair for this was
     * posts.x.consumer_key/posts.x.consumer_secret all along - a
     * DIFFERENT, valid value from both posts.x.client_id/client_secret
     * (the OAuth 2.0 pair, correct for the user-context PKCE flow above,
     * wrong here) and ads.x.client_id/client_secret (coincidentally the
     * same length as posts.x.consumer_key/secret, which is what led to
     * wrongly assuming they were the same credential - they are not).
     *
     * Confirmed live, twice: (1) POST https://api.twitter.com/oauth2/token
     * (note: "api.twitter.com", NOT "api.x.com" - the "/2/oauth2/token"
     * endpoint used by the PKCE flow does NOT work for this; it returns a
     * 200 + a real-looking access_token that every resource endpoint then
     * rejects as "Unsupported Authentication... Unknown", confirmed
     * against both GET and POST /2/webhooks) with HTTP Basic Auth
     * (consumer_key:consumer_secret) and grant_type=client_credentials
     * alone returns a genuine token; (2) that exact token IS accepted by
     * GET /2/webhooks (real 200, real {"meta":{"result_count":0}}
     * response body), unlike every previously-tried alternative.
     */
    private function appOnlyBearerToken(): ?string
    {
        $response = $this->apiService->post(
            'https://api.twitter.com/oauth2/token',
            ['Authorization' => 'Basic ' . base64_encode(adminSetting('posts.x.consumer_key') . ':' . adminSetting('posts.x.consumer_secret'))],
            ['grant_type' => 'client_credentials'],
            'form'
        );

        if (!$response['success']) {
            Log::warning('X app-only bearer token request failed.', ['status' => $response['status'] ?? null, 'body' => $response['data'] ?? null]);
        }

        return $response['success'] ? ($response['data']['access_token'] ?? null) : null;
    }

    /**
     * Best-effort only, for enriching the XChat placeholder message in
     * handleWebhook() with a real name/avatar instead of "Unknown" - the
     * sender_id itself is real cleartext even though the message body
     * isn't, so this is a normal app-only GET /2/users/{id} lookup, same
     * auth as appOnlyBearerToken() already uses elsewhere in this class.
     * Any failure here must not block the placeholder message itself.
     */
    private function fetchXChatSenderProfile(string $senderId): array
    {
        $token = $this->appOnlyBearerToken();

        if (!$token) {
            return [];
        }

        $response = $this->apiService->get(
            "https://api.x.com/2/users/{$senderId}",
            ['Authorization' => "Bearer {$token}"],
            ['user.fields' => 'name,username,profile_image_url']
        );

        return $response['success'] ? ($response['data']['data'] ?? []) : [];
    }

    /**
     * X's profile_image_url fields come back as the tiny 48px "_normal"
     * rendition by default - swap it for the full-size "_400x400" version
     * so avatars in the inbox aren't blurry. No-op (returns as-is) for any
     * URL that doesn't match this exact X naming convention.
     */
    private function upsizeXAvatar(?string $url): ?string
    {
        return $url ? str_replace('_normal.', '_400x400.', $url) : $url;
    }

    /**
     * Registers this app's Account Activity webhook URL with X exactly
     * once - checks GET /2/webhooks for an already-registered one
     * matching our URL first (idempotent) rather than blindly re-POSTing
     * on every connect. The resulting webhook_id is cached in
     * admin_settings since subscribeAccountActivity() needs it in every
     * subsequent call's URL path. Confirmed via docs.x.com/x-api/webhooks:
     * POST /2/webhooks registers, GET /2/webhooks lists existing ones.
     */
    private function registerWebhookIfNeeded(): ?string
    {
        $existingId = adminSetting('messaging.x.webhook_id');
     
        if ($existingId) {
            return $existingId;
        }

        $bearerToken = $this->appOnlyBearerToken();

        if (!$bearerToken) {
            return null;
        }

        $webhookUrl = route('messaging.webhook.x_activity.receive');

        $listResponse = $this->apiService->get('https://api.x.com/2/webhooks', ['Authorization' => "Bearer {$bearerToken}"]);

        if ($listResponse['success']) {
            foreach ($listResponse['data']['data'] ?? [] as $webhook) {
                if (($webhook['url'] ?? null) === $webhookUrl) {
                    \App\Support\Settings::set('messaging.x.webhook_id', $webhook['id']);

                    return $webhook['id'];
                }
            }
        }

        $createResponse = $this->apiService->post(
            'https://api.x.com/2/webhooks',
            ['Authorization' => "Bearer {$bearerToken}"],
            ['url' => $webhookUrl],
            'json'
        );
       
        if (!$createResponse['success']) {
            Log::warning('X Account Activity webhook registration failed.', ['body' => $createResponse['data'] ?? null]);

            return null;
        }

        $webhookId = $createResponse['data']['data']['id'] ?? null;

        if ($webhookId) {
            \App\Support\Settings::set('messaging.x.webhook_id', $webhookId);
        }

        return $webhookId;
    }

    /**
     * Subscribes ONE connected account's activity (including DM events)
     * to the registered webhook. Confirmed via docs.x.com/x-api/account-
     * activity/create-subscription: POST /2/account_activity/webhooks/
     * {webhook_id}/subscriptions/all - THIS DOES NOT ACTUALLY ENABLE DM
     * DELIVERY. Found by reading a working reference implementation of
     * this exact feature in another project (tawasa): "/subscriptions/
     * all" only covers Posts/mentions/likes/follows-type activity: DMs
     * specifically require the separate POST /2/activity/subscriptions
     * endpoint below, with an explicit event_type per DM direction and a
     * required (not optional - X's own error is literally "$.filter: is
     * missing but it is required") filter.user_id scoping the
     * subscription to this one connected account's own numeric X id.
     * Both calls use this account's OAuth 2.0 user access_token (scopes
     * dm.read/dm.write/tweet.read/users.read - already what redirect()
     * requests), not app-only auth, unlike webhook registration above.
     *
     * The "Pay Per Use" tier this feature requires allows only 3 total
     * subscriptions app-wide (confirmed via docs.x.com/x-api/account-
     * activity/introduction - Enterprise is needed for more). The 4th+
     * account to connect will get a real rejection from X here - handled
     * gracefully (webhook_subscribed stays false on that channel) rather
     * than failing the connect: PollXDirectMessages already covers every
     * connected account regardless of webhook_subscribed, so an account
     * past the cap just keeps getting ~1-minute-latency polling delivery
     * instead of instant, never zero delivery.
     */
    private function subscribeAccountActivity(MessageChannel $channel, string $accessToken): void
    {
        $webhookId = $this->registerWebhookIfNeeded();

        if (!$webhookId) {
            return;
        }

        $userId = $channel->socialAccount->platform_account_id;
        $ok = true;
        $lastBody = null;

        foreach (['dm.received', 'dm.sent'] as $eventType) {
            $response = $this->apiService->post(
                'https://api.x.com/2/activity/subscriptions',
                ['Authorization' => "Bearer {$accessToken}"],
                [
                    'event_type' => $eventType,
                    'webhook_id' => $webhookId,
                    'filter'     => ['user_id' => $userId],
                ],
                'json'
            );

            // X's success response for this endpoint is an empty 204 - no
            // JSON body to check, only status. A 400 body containing
            // "Duplicate" means this event type is already subscribed for
            // this user, which is the end state actually wanted, not a
            // failure (confirmed via the reference implementation's own
            // identical handling).
            $isDuplicate = ($response['status'] ?? null) === 400 && str_contains((string) ($response['body'] ?? ''), 'Duplicate');

            if (!$response['success'] && !$isDuplicate) {
                $ok = false;
                $lastBody = $response['data'] ?? $response['body'] ?? null;
            }
        }

        if ($ok) {
            $channel->update(['webhook_subscribed' => true]);
            Log::info('X Account Activity subscription created.', ['channel_id' => $channel->id]);

            return;
        }

        Log::warning('X Account Activity subscription failed - this account will rely on scheduled polling instead of real-time delivery (likely the Pay Per Use tier\'s 3-subscription cap).', [
            'channel_id' => $channel->id,
            'body'       => $lastBody,
        ]);
    }

    private function ensureFreshToken(MessageChannel $channel): string
    {
        if ($channel->expires_at && now()->lt($channel->expires_at)) {
            return $channel->socialAccount->access_token;
        }

        if (!$channel->socialAccount->refresh_token) {
            return $channel->socialAccount->access_token;
        }

        $response = $this->apiService->post(adminSetting('messaging.x.token_url') ?: 'https://api.x.com/2/oauth2/token', $this->basicAuthHeader(), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $channel->socialAccount->refresh_token,
            'client_id'     => adminSetting('posts.x.client_id'),
        ], 'form');

        if ($response['success']) {
            $channel->socialAccount->update([
                'access_token'  => $response['data']['access_token'],
                'refresh_token' => $response['data']['refresh_token'] ?? $channel->socialAccount->refresh_token,
            ]);

            $channel->update([
                'expires_at' => Carbon::now()->addSeconds($response['data']['expires_in'] ?? 7200),
            ]);

            return $response['data']['access_token'];
        }

        return $channel->socialAccount->access_token;
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $accessToken = $this->ensureFreshToken($channel);

        $endpoint = $conversation->external_conversation_id
            ? $this->base . 'dm_conversations/' . $conversation->external_conversation_id . '/messages'
            : $this->base . 'dm_conversations/with/' . $conversation->customer_external_id . '/messages';

        $payload = ['text' => $data['body']];

        if (!empty($data['media_url'])) {
            // Sending media over the v2 DM API requires first uploading it
            // through the v1.1 media/upload endpoint (the same chunked
            // INIT/APPEND/FINALIZE flow XAdService already implements for
            // Ads creatives) to get a media_id, then attaching that here -
            // left out for now since it would just duplicate that logic
            // for a secondary, non-essential path; text sends are unaffected.
            $payload['text'] = trim($data['body'] . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post($endpoint, ['Authorization' => "Bearer {$accessToken}"], $payload);
       
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['detail'] ?? $response['data']['title'] ?? 'X DM API request failed.'];
        }

        return [
            'success'               => true,
            'external_message_id'   => $response['data']['data']['dm_event_id'] ?? null,
            'external_conversation_id' => $response['data']['data']['dm_conversation_id'] ?? $conversation->external_conversation_id,
        ];
    }

    /**
     * Called on a schedule (see PollXDirectMessagesCommand) rather than
     * from a webhook. Uses the channel's stored pagination cursor so each
     * run only fetches events that arrived since the last one.
     */
    public function pollMessages(MessageChannel $channel): void
    {
        $accessToken = $this->ensureFreshToken($channel);
        $meta = $channel->meta ?? [];

        $params = [
            'max_results'    => 100,
            'dm_event.fields' => 'id,text,event_type,dm_conversation_id,sender_id,created_at',
            'expansions'     => 'sender_id',
            'user.fields'    => 'name,username,profile_image_url',
        ];

        if (!empty($meta['pagination_token'])) {
            $params['pagination_token'] = $meta['pagination_token'];
        }

        $response = $this->apiService->get($this->base . 'dm_events', ['Authorization' => "Bearer {$accessToken}"], $params);
     
        if (!$response['success']) {
            return;
        }

        $users = collect($response['data']['includes']['users'] ?? [])->keyBy('id');
       
        foreach ($response['data']['data'] ?? [] as $event) {
            // Only inbound (customer-authored) messages need processing -
            // our own outbound sends already got a local Message row at
            // send time, and appear again here as an echo.
            if ($event['event_type'] !== 'MessageCreate' || $event['sender_id'] === $channel->external_id) {
                continue;
            }

            $sender = $users->get($event['sender_id']);
            ProcessInboundMessage::dispatch(
                socialAccountId: $channel->social_account_id,
                customerExternalId: $event['sender_id'],
                customerName: $sender['name'] ?? $sender['username'] ?? null,
                customerAvatarUrl: $this->upsizeXAvatar($sender['profile_image_url'] ?? null),
                externalConversationId: $event['dm_conversation_id'] ?? null,
                externalMessageId: $event['id'] ?? null,
                body: $event['text'] ?? null,
            );
        }

        $channel->update([
            'meta'           => array_merge($meta, ['pagination_token' => $response['data']['meta']['next_token'] ?? null]),
            'last_synced_at' => now(),
        ]);
    }

    /**
     * CRC (Challenge-Response Check) - X periodically re-validates a
     * registered webhook by GETting it with a crc_token query param and
     * expects {"response_token": "sha256=" + base64(hmac_sha256(consumer
     * _secret, crc_token))} back (confirmed via docs.x.com/x-api/
     * webhooks/quickstart).
     *
     * Uses posts.x.consumer_secret - previously used ads.x.client_secret,
     * which was a real, confirmed bug: ads.x.client_id/client_secret
     * happen to be the same LENGTH as posts.x.consumer_key/consumer_secret
     * (which led to wrongly treating them as the same credential), but
     * are actually different values. Confirmed live: registerWebhookIfNeeded()
     * reached X's CRC check for the first time once appOnlyBearerToken()
     * was fixed to use posts.x.consumer_key/secret, and X rejected the
     * response this method computed ("CrcValidationFailed:... Invalid
     * response_token") because it was still signing with the wrong
     * secret. The Consumer Key/Secret pair is one single, real credential
     * for this app - it should be signed with the same one
     * appOnlyBearerToken() now uses to authenticate, not a different one
     * that merely happened to look plausible.
     */
    public function crcResponseToken(string $crcToken): string
    {
        return 'sha256=' . base64_encode(hash_hmac('sha256', $crcToken, (string) adminSetting('posts.x.consumer_secret'), true));
    }

    /**
     * Verifies the x-twitter-webhooks-signature header on an inbound
     * event POST - same consumer secret, HMAC-SHA256 over the raw
     * request body (confirmed via docs.x.com/x-api/webhooks/quickstart).
     */
    public function verifySignature(Request $request): bool
    {
        $header = $request->header('x-twitter-webhooks-signature', '');
        $expected = 'sha256=' . base64_encode(hash_hmac('sha256', $request->getContent(), (string) adminSetting('posts.x.consumer_secret'), true));

        return $header !== '' && hash_equals($expected, $header);
    }

    /**
     * Parses an inbound Account Activity event payload for direct
     * message events and dispatches ProcessInboundMessage for each.
     *
     * Checks both the older Account Activity v1.1 nested shape
     * (message_create.sender_id / message_create.message_data.text /
     * message_create.target.recipient_id) and a flatter v2-style shape
     * matching what GET /2/dm_events already returns (sender_id/text/
     * dm_conversation_id directly) - genuinely NOT verified against a
     * real live payload (unlike every other endpoint this class
     * documents as confirmed), since that requires an actual live
     * Account Activity subscription actually receiving a real DM, which
     * this session's implementation work couldn't trigger. Whichever
     * shape doesn't apply just yields empty/null fields harmlessly; if
     * messages arrive but content shows up blank, this is the first
     * place to check against a real captured payload.
     *
     * Returns bool (true = at least one message dispatched) - same
     * shape as TiktokMessagingService::handleWebhook(), for the same
     * reason: lets the controller record an accurate WebhookLog.processed
     * flag without duplicating this method's own decision logic.
     */
    public function handleWebhook(array $payload): bool
    {
        // --------------------------------------------------------------------------
        // 1. Normalize Payload & Identify Channel
        // --------------------------------------------------------------------------
        // Legacy Account Activity API: {"for_user_id": "...", "direct_message_events": [...]}
        // Activity API v2: {"data": {"filter": {"user_id": "..."}, "payload": {"direct_message_events": [...]}}}
        $effectivePayload = $payload['data']['payload'] ?? $payload;
        $externalId = $payload['for_user_id'] ?? $payload['data']['filter']['user_id'] ?? null;

        $channel = $externalId 
            ? MessageChannel::where('platform', 'x')->where('external_id', $externalId)->first() 
            : null;

        if (!$channel) {
            Log::warning('X Webhook event arrived for an unrecognized for_user_id - dropped.', [
                'for_user_id' => $externalId,
                'known_x_external_ids' => MessageChannel::where('platform', 'x')->pluck('external_id'),
            ]);

            WebhookLog::create([
                'platform'        => 'x',
                'event_type'      => $payload['data']['event_type'] ?? 'direct_message_events',
                'signature_valid' => true,
                'processed'       => false,
                'note'            => 'Signature OK, but no MessageChannel matched this for_user_id.',
                'payload'         => $payload,
                'ip'              => request()->ip(),
            ]);

            return false;
        }

        // --------------------------------------------------------------------------
        // 1b. XChat (encrypted) events - a genuinely different shape from
        // classic direct_message_events, identified by encoded_event's
        // presence rather than absence of a key normalize() can't produce
        // here. encoded_event itself is real, unrecoverable ciphertext -
        // confirmed via docs.x.com/xchat: X itself "cannot read plaintext
        // content". sender_id/conversation_id/timestamp around it are real
        // cleartext though, so this still surfaces a real inbox entry
        // (sender, conversation, time) with a placeholder body instead of
        // the event silently producing nothing in `messages` - see
        // fetchXChatSenderProfile()'s docblock.
        // --------------------------------------------------------------------------
        if (isset($effectivePayload['encoded_event'])) {
            $eventType = $payload['data']['event_type'] ?? 'chat.received';
            $senderId = $effectivePayload['sender_id'] ?? null;
            $dispatched = false;

            if ($eventType === 'chat.received' && $senderId && $senderId !== $channel->external_id) {
                $sender = $this->fetchXChatSenderProfile($senderId);

                ProcessInboundMessage::dispatch(
                    socialAccountId: $channel->social_account_id,
                    customerExternalId: $senderId,
                    customerName: $sender['name'] ?? null,
                    customerAvatarUrl: $this->upsizeXAvatar($sender['profile_image_url'] ?? null),
                    externalConversationId: $effectivePayload['conversation_id'] ?? null,
                    externalMessageId: $effectivePayload['id'] ?? null,
                    body: json_encode($payload) ?? 'New encrypted message - open X to read (content not readable server-side, see handleWebhook() docblock).',
                );

                $dispatched = true;
            }

            WebhookLog::create([
                'platform'        => 'x',
                'event_type'      => $eventType,
                'signature_valid' => true,
                'processed'       => $dispatched,
                'note'            => $dispatched
                    ? 'XChat (encrypted) event - placeholder message dispatched (real text unavailable server-side, see handleWebhook() docblock).'
                    : 'XChat (encrypted) event received - not a new inbound message (an echo of our own send, or missing sender_id).',
                'payload'         => $payload,
                'ip'              => request()->ip(),
            ]);

            return $dispatched;
        }

        // --------------------------------------------------------------------------
        // 2. Process Direct Message Events
        // --------------------------------------------------------------------------
        $events = $effectivePayload['direct_message_events'] ?? [];
        $users = $effectivePayload['users'] ?? [];
        $processed = false;

        foreach ($events as $event) {
            if (($event['type'] ?? null) !== 'message_create') {
                continue;
            }

            $senderId = $event['message_create']['sender_id'] ?? null;
            $recipientId = $event['message_create']['target']['recipient_id'] ?? null;

            // Skip invalid events or outbound replies sent by ourselves
            if (!$senderId || $senderId === $channel->external_id) {
                continue;
            }

            $messageId = $event['id'] ?? null;
            $messageData = $event['message_create']['message_data'] ?? [];
            $text = $messageData['text'] ?? '';

            // Extract profile metadata from users payload array
            $profileName = $users[$senderId]['name']
                ?? $users[$senderId]['screen_name']
                ?? $users[$senderId]['data']['name']
                ?? $users[$senderId]['data']['username']
                ?? 'X User';

            $profileImage = $users[$senderId]['profile_image_url_https']
                ?? $users[$senderId]['profile_image_url']
                ?? $users[$senderId]['data']['profile_image_url']
                ?? null;

            // Extract media attachments
            $attachments = [];
            if (!empty($messageData['attachment']['media']['media_url_https'])) {
                $attachments[] = [
                    'type' => $messageData['attachment']['media']['type'] ?? 'image',
                    'url'  => $messageData['attachment']['media']['media_url_https'],
                ];
            }

            // Dispatch job with generic payload expected by ProcessInboundMessage
            ProcessInboundMessage::dispatch(
                socialAccountId: $channel->social_account_id,
                customerExternalId: $senderId,
                customerName: $profileName,
                customerAvatarUrl: $this->upsizeXAvatar($profileImage),
                externalConversationId: $recipientId,
                externalMessageId: $messageId,
                type: !empty($attachments) && empty($text) ? $attachments[0]['type'] : 'text',
                body: $text,
                attachments: $attachments
            );

            $processed = true;
        }

        // --------------------------------------------------------------------------
        // 3. Log Result
        // --------------------------------------------------------------------------
        WebhookLog::create([
            'platform'        => 'x',
            'event_type'      => $payload['data']['event_type'] ?? 'direct_message_events',
            'signature_valid' => true,
            'processed'       => $processed,
            'note'            => $processed
                ? 'Message dispatched to ProcessInboundMessage.'
                : 'Signature OK, but not handled as a new message (no valid message_create events or echo of our own send).',
            'payload'         => $payload,
            'ip'              => request()->ip(),
        ]);

        return $processed;
    }
}
