<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Support\Connections\GrantedScopes;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    /**
     * One scope set for BOTH X connect flows (Content Posting in
     * PostAccountController::redirectX() and Messaging here). They share an
     * X app and upsert the same social_accounts row (same X user id), so a
     * narrower set in either flow replaced the other's working token -
     * connecting Messaging broke posting (no tweet.write/media.write) and
     * reconnecting Posting broke DMs. media.write is required by X API v2
     * media upload for OAuth 2.0 user tokens.
     */
    public const OAUTH_SCOPES = 'tweet.read tweet.write users.read media.write dm.read dm.write offline.access';

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
            'scope'                 => self::OAUTH_SCOPES,
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
        $grantedScopes = GrantedScopes::fromTokenResponse($tokenResponse['data']);

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
                'expires_at'               => Carbon::now()->addSeconds($tokenResponse['data']['expires_in'] ?? 7200),
                'is_token_valid'           => true,
                ...GrantedScopes::attributes($grantedScopes),
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

        // dm.received = classic (unencrypted) DMs; chat.received = XChat
        // (end-to-end encrypted) messages, surfaced as a "new encrypted
        // message" notice - see handleWebhook(). dm.sent is deliberately
        // not subscribed: handleWebhook() drops every event sent BY this
        // account, and on capped tiers each subscription counts.
        foreach (['dm.received', 'chat.received'] as $eventType) {
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

    public function ensureFreshToken(MessageChannel $channel): string
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
                'expires_at'    => Carbon::now()->addSeconds($response['data']['expires_in'] ?? 7200),
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
        // Conversation::channel() is the SocialAccount (see the model) - the
        // X-specific token/expiry state lives on its MessageChannel. Passing
        // the SocialAccount here was a TypeError on every X reply.
        $channel = $conversation->channel->messageChannel;

        if (!$channel) {
            return ['success' => false, 'error' => 'This X account is no longer connected for messaging - reconnect it in Channels.'];
        }

        // X Chat (end-to-end encrypted) conversation: the reply must be
        // encrypted + signed with the Chat XDK and sent through the X Chat
        // API - plaintext via the legacy DM endpoint isn't delivered there.
        if ($xChatConversationId = ($conversation->meta['x_chat_conversation_id'] ?? null)) {
            $result = app(XChat\XChatService::class)->sendText(
                $conversation->channel,
                $xChatConversationId,
                (string) $conversation->customer_external_id,
                (string) ($data['body'] ?? ''),
                !empty($data['media_url']) ? ['url' => $data['media_url'], 'file_name' => $data['file_name'] ?? null] : null
            );

            // X's encrypted media store (/2/chat/media/upload) refuses
            // uploads (503) while the regular media upload works for the
            // same token - send the file as a regular DM to the same person
            // instead (NOT end-to-end encrypted). Text-only replies stay
            // on X Chat.
            if (!$result['success'] && ($result['reason'] ?? null) === 'chat_media_unavailable' && !empty($data['media_url'])) {
                Log::info('X Chat: encrypted media upload unavailable, sending the file as a regular DM.', ['social_account_id' => $conversation->social_account_id]);

                $fallback = $this->sendLegacyDm($channel, $this->base . 'dm_conversations/with/' . $conversation->customer_external_id . '/messages', $data);
                // Keep the conversation on X Chat routing: no external_conversation_id.
                unset($fallback['external_conversation_id']);

                return $fallback;
            }

            return $result;
        }

        $endpoint = $conversation->external_conversation_id
            ? $this->base . 'dm_conversations/' . $conversation->external_conversation_id . '/messages'
            : $this->base . 'dm_conversations/with/' . $conversation->customer_external_id . '/messages';

        $result = $this->sendLegacyDm($channel, $endpoint, $data);

        if ($result['success']) {
            $result['external_conversation_id'] ??= $conversation->external_conversation_id;
        }

        return $result;
    }

    /**
     * Legacy (unencrypted) DM: POST /2/dm_conversations/.../messages, with
     * a file uploaded through the v2 media upload (XDmMediaUploader) and
     * attached by media_id.
     */
    private function sendLegacyDm(MessageChannel $channel, string $endpoint, array $data): array
    {
        $accessToken = $this->ensureFreshToken($channel);

        $payload = array_filter(['text' => trim((string) ($data['body'] ?? ''))], fn ($v) => $v !== '');

        if (!empty($data['media_url'])) {
            // Upload through the v2 chunked media API (dm_image / dm_gif /
            // dm_video) and attach the media_id - see XDmMediaUploader.
            try {
                $file = Http::timeout(60)->get($data['media_url']);
                if (!$file->successful()) {
                    return ['success' => false, 'error' => 'Could not read the file to send (HTTP ' . $file->status() . ').'];
                }
                // Sniff the bytes; the storage Content-Type is only a fallback
                // when they look generic.
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($file->body()) ?: '';
                if (!preg_match('#^(image|video)/#', $mime)) {
                    $mime = strtok((string) $file->header('Content-Type'), ';') ?: $mime;
                }
                $payload['attachments'] = [['media_id' => app(XDmMediaUploader::class)->upload($accessToken, $file->body(), $mime ?: null)]];
            } catch (\RuntimeException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $response = $this->apiService->post($endpoint, ['Authorization' => "Bearer {$accessToken}"], $payload);
       
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['detail'] ?? $response['data']['title'] ?? 'X DM API request failed.'];
        }

        return [
            'success'               => true,
            'external_message_id'   => $response['data']['data']['dm_event_id'] ?? null,
            'external_conversation_id' => $response['data']['data']['dm_conversation_id'] ?? null,
        ];
    }

    /**
     * Called on a schedule (see PollXDirectMessagesCommand) rather than
     * from a webhook. Always reads the NEWEST page of DM events: X returns
     * dm_events newest-first, and its next_token points to OLDER events -
     * so the previous "resume from the stored pagination_token" walked
     * backwards through history each minute and missed new DMs until it
     * ran out of pages. Already-seen events are skipped by
     * ProcessInboundMessage's external_message_id check.
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

            if ($this->alreadyStored($channel, $event['id'] ?? null)) {
                continue; // don't re-download media for messages we have
            }

            $sender = $users->get($event['sender_id']);
            // DM media arrives as a t.co link in the text - store the file.
            $media = $this->resolveDmMedia($channel, (string) ($event['text'] ?? ''));
            ProcessInboundMessage::dispatch(
                socialAccountId: $channel->social_account_id,
                customerExternalId: $event['sender_id'],
                customerName: $sender['name'] ?? $sender['username'] ?? null,
                customerAvatarUrl: $this->upsizeXAvatar($sender['profile_image_url'] ?? null),
                externalConversationId: $event['dm_conversation_id'] ?? null,
                externalMessageId: $event['id'] ?? null,
                type: $media['type'],
                body: $media['body'],
                attachments: $media['attachments'],
            );
        }

        $channel->update([
            'meta'           => \Illuminate\Support\Arr::except($meta, ['pagination_token']),
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
    /**
     * Turn t.co DM media links in a message text into stored files (see
     * XChat\XChatMediaService::resolveDmMediaLinks()).
     *
     * @return array{type: string, body: ?string, attachments: array}
     */
    private function resolveDmMedia(MessageChannel $channel, string $text): array
    {
        $resolved = $text !== '' && $channel->socialAccount
            ? app(XChat\XChatMediaService::class)->resolveDmMediaLinks($channel->socialAccount, $text)
            : ['body' => $text, 'attachments' => []];

        return [
            'type'        => $resolved['attachments'] && $resolved['body'] === '' ? $resolved['attachments'][0]['type'] : 'text',
            'body'        => $resolved['body'] !== '' ? $resolved['body'] : null,
            'attachments' => $resolved['attachments'],
        ];
    }

    private function alreadyStored(MessageChannel $channel, ?string $externalMessageId): bool
    {
        return $externalMessageId && \App\Models\Messaging\Message::where('external_message_id', $externalMessageId)
            ->whereHas('conversation', fn ($q) => $q->where('social_account_id', $channel->social_account_id))
            ->exists();
    }

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
    /**
     * X Chat webhook event -> verified, decrypted inbox message.
     *
     *   event_uuid claimed once (webhook_event_receipts, unique) - redeliveries
     *   return without reprocessing;
     *   chat.received from a customer -> XChatDecryptionService (Chat XDK
     *   worker) -> XChatMessageMapper -> ProcessInboundMessage;
     *   our own chat.sent / echoes -> only their key-change event is kept;
     *   a retryable failure (no PIN yet, worker down, key not yet known)
     *   stores the message as pending with its ciphertext, decrypted later
     *   by messaging:x-chat-decrypt-pending - never lost, never shown as
     *   unverified content.
     */
    private function handleXChatEvent(array $payload, array $event, MessageChannel $channel): bool
    {
        $eventType = $payload['data']['event_type'] ?? 'chat.received';
        $eventUuid = $payload['data']['event_uuid'] ?? null;
        $senderId = $event['sender_id'] ?? null;
        $conversationId = $event['conversation_id'] ?? null;
        // Audit log without the bulky ciphertext: the encrypted blobs are
        // replaced by their size + hash (a pending message keeps its own
        // copy in messages.meta for the retry).
        $redacted = $payload;
        foreach (['encoded_event', 'conversation_key_change_event', 'conversation_token'] as $field) {
            if (isset($redacted['data']['payload'][$field]) && is_string($redacted['data']['payload'][$field])) {
                $value = $redacted['data']['payload'][$field];
                $redacted['data']['payload'][$field] = '[redacted: ' . strlen($value) . ' chars, sha256 ' . substr(hash('sha256', $value), 0, 16) . ']';
            }
        }
        $log = fn (bool $processed, string $note) => WebhookLog::create([
            'platform'        => 'x',
            'event_type'      => $eventType,
            'signature_valid' => true,
            'processed'       => $processed,
            'note'            => $note,
            'payload'         => $redacted,
            'ip'              => request()->ip(),
        ]);

        if ($eventUuid && DB::table('webhook_event_receipts')->insertOrIgnore([
            'platform' => 'x', 'event_uuid' => (string) $eventUuid, 'received_at' => now(),
        ]) === 0) {
            Log::info('X Chat: duplicate webhook delivery ignored.', ['event_uuid' => $eventUuid]);

            return true;
        }

        $keys = app(XChat\XChatKeyService::class);

        if ($eventType !== 'chat.received' || !$senderId || $senderId === $channel->external_id) {
            if (!empty($event['conversation_key_change_event']) && $conversationId) {
                $keys->recordKeyChangeEvent($conversationId, $event['conversation_key_version'] ?? null, $event['conversation_key_change_event']);
            }
            $log(false, 'X Chat event is not an inbound customer message (own send / echo) - key-change event recorded if present.');

            return false;
        }

        $account = $channel->socialAccount;
        $sender = $this->fetchXChatSenderProfile($senderId);
        $externalMessageId = $event['id'] ?? null;
        $common = [
            'socialAccountId'        => $channel->social_account_id,
            'customerExternalId'     => $senderId,
            'customerName'           => $sender['name'] ?? null,
            'customerAvatarUrl'      => $this->upsizeXAvatar($sender['profile_image_url'] ?? null),
            // Replies to X Chat conversations go through XChatService
            // (encrypted), keyed by this id - not the legacy DM API.
            'externalConversationId' => null,
            'conversationMeta'       => ['x_chat_conversation_id' => $conversationId],
        ];

        try {
            $decrypted = app(XChat\XChatDecryptionService::class)->decryptIncomingEvent($event, $account);
            $mapped = XChat\XChatMessageMapper::map($decrypted);

            if ($mapped['action'] === 'ignore') {
                $log(true, 'X Chat event decrypted - protocol event (' . ($decrypted['type'] ?? 'unknown') . '), no inbox message.');

                return true;
            }

            if ($mapped['action'] === 'edit') {
                $edited = $this->applyXChatEdit($channel->social_account_id, $mapped['target_message_id'], $mapped['body']);
                $log(true, $edited ? 'X Chat edit decrypted and applied.' : 'X Chat edit decrypted - original message not found.');

                return true;
            }

            // Encrypted attachments: downloaded + decrypted into real files.
            $resolved = XChat\XChatMessageMapper::withMedia($mapped, app(XChat\XChatMediaService::class), $account, (string) $conversationId, $decrypted['key_version'] ?? null);

            ProcessInboundMessage::dispatch(...$common + [
                'externalMessageId' => $externalMessageId ?? ($decrypted['message_id'] ?? null),
                'type'              => $resolved['type'],
                'body'              => $resolved['body'],
                'attachments'       => $resolved['attachments'],
                'messageMeta'       => ['x_chat' => [
                    'status'       => 'decrypted',
                    'verified'     => (bool) ($decrypted['verified'] ?? false),
                    'content_type' => $decrypted['content_type'] ?? null,
                    'key_version'  => $decrypted['key_version'] ?? null,
                ]],
            ]);
            $log(true, 'X Chat message decrypted and dispatched (' . $resolved['type'] . ', ' . count($resolved['attachments']) . ' attachment(s)).');

            return true;
        } catch (XChat\XChatException $e) {
            $pending = $e->isRetryable();

            ProcessInboundMessage::dispatch(...$common + [
                'externalMessageId' => $externalMessageId,
                'type'              => $pending ? 'text' : 'unsupported',
                'body'              => $pending ? XChat\XChatMessageMapper::PENDING_BODY : XChat\XChatMessageMapper::UNVERIFIED_BODY,
                'messageMeta'       => ['x_chat' => array_filter([
                    'status'  => $pending ? 'pending' : 'failed',
                    'reason'  => $e->reason,
                    // Ciphertext + public fields only - what a later retry needs.
                    'payload' => $pending ? array_intersect_key($event, array_flip([
                        'id', 'sender_id', 'conversation_id', 'conversation_key_version',
                        'encoded_event', 'conversation_key_change_event', 'message_event_signature',
                    ])) : null,
                ])],
            ]);

            Log::warning('X Chat: message stored undecrypted.', ['social_account_id' => $channel->social_account_id, 'reason' => $e->reason, 'retryable' => $pending]);
            $log(false, 'X Chat message NOT decrypted (' . $e->reason . ') - stored as ' . ($pending ? 'pending, will retry' : 'failed') . '.');

            return false;
        }
    }

    /** Apply a decrypted X Chat edit to the original inbound message. */
    public function applyXChatEdit(int $socialAccountId, ?string $targetMessageId, string $newText): bool
    {
        if (!$targetMessageId) {
            return false;
        }

        $message = \App\Models\Messaging\Message::where('external_message_id', $targetMessageId)
            ->whereHas('conversation', fn ($q) => $q->where('social_account_id', $socialAccountId))
            ->first();

        return (bool) $message?->update(['body' => $newText, 'edited_at' => now()]);
    }

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
        // 1b. X Chat (end-to-end encrypted) events - chat.received / chat.sent.
        // Decrypted with X's official Chat XDK; see handleXChatEvent().
        // --------------------------------------------------------------------------
        if (isset($effectivePayload['encoded_event'])) {
            return $this->handleXChatEvent($payload, $effectivePayload, $channel);
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

            // DM media: the text carries a t.co link to it, and the
            // media_url_https is private (needs the account's token) - so
            // download it into our storage instead of hotlinking.
            $media = $this->resolveDmMedia($channel, (string) $text);
            $attachments = $media['attachments'];
            if ($attachments) {
                $text = (string) $media['body'];
            } elseif (!empty($messageData['attachment']['media']['media_url_https'])) {
                $attachments[] = [
                    'type' => ($messageData['attachment']['media']['type'] ?? 'photo') === 'photo' ? 'image' : 'video',
                    'url'  => $messageData['attachment']['media']['media_url_https'],
                ];
            }

            // Dispatch job with generic payload expected by ProcessInboundMessage
            ProcessInboundMessage::dispatch(
                socialAccountId: $channel->social_account_id,
                customerExternalId: $senderId,
                customerName: $profileName,
                customerAvatarUrl: $this->upsizeXAvatar($profileImage),
                // Not $recipientId - that's this account's own user id, and
                // storing it as the conversation id sent every reply to
                // dm_conversations/{our id}/messages. null keeps any real
                // dm_conversation_id already known (from polling) and
                // otherwise replies via dm_conversations/with/{customer}.
                externalConversationId: null,
                externalMessageId: $messageId,
                type: !empty($attachments) && $text === '' ? $attachments[0]['type'] : 'text',
                body: $text !== '' ? $text : null,
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
