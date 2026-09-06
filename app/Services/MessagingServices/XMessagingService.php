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

/**
 * X (Twitter) Direct Messages - X API v2 (api.x.com/2/), a different
 * product/surface from the Ads API this app's ad campaign module talks to,
 * with different (simpler) auth: OAuth 2.0 user-context with PKCE and a
 * plain Bearer token, not OAuth 1.0a HMAC request signing.
 *
 * No webhook path exists here - the Account Activity API's real-time DM
 * webhooks require X's Enterprise/Premium tier, which isn't realistically
 * obtainable, so inbound messages are instead picked up by scheduled
 * polling (see PollXDirectMessagesCommand) against GET /2/dm_events,
 * using each channel's stored pagination cursor (message_channels.meta.
 * since_id) to fetch only what's new since the last run.
 *
 * Endpoints verified this session via developer.x.com: POST
 * /2/dm_conversations/with/:participant_id/messages (new 1:1 conversation),
 * POST /2/dm_conversations/:id/messages (existing conversation), GET
 * /2/dm_events (polling, paginated, events up to 30 days old).
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
     * App-only OAuth 2.0 Bearer Token (client_credentials grant) - needed
     * to manage webhooks (GET/POST /2/webhooks), which are app-level, not
     * per-user. Confirmed via docs.x.com/x-api/webhooks/introduction:
     * "All endpoints require OAuth2 App Only Bearer Token authentication."
     * Fetched fresh each call rather than cached - registerWebhookIfNeeded()
     * only runs when no webhook_id is stored yet (effectively once), not a
     * hot path worth adding cache invalidation complexity for.
     */
    private function appOnlyBearerToken(): ?string
    {
        $response = $this->apiService->post(
            adminSetting('messaging.x.token_url') ?: 'https://api.x.com/2/oauth2/token',
            $this->basicAuthHeader(),
            ['grant_type' => 'client_credentials'],
            'form'
        );

        return $response['success'] ? ($response['data']['access_token'] ?? null) : null;
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
     * {webhook_id}/subscriptions/all, authenticated with THIS account's
     * own OAuth 2.0 user access_token (scopes dm.read/dm.write/
     * tweet.read/users.read - already what redirect() requests) - not
     * app-only auth, unlike webhook registration above.
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

        $response = $this->apiService->post(
            "https://api.x.com/2/account_activity/webhooks/{$webhookId}/subscriptions/all",
            ['Authorization' => "Bearer {$accessToken}"],
            [],
            'json'
        );

        if ($response['success'] && ($response['data']['data']['subscribed'] ?? false)) {
            $channel->update(['webhook_subscribed' => true]);
            Log::info('X Account Activity subscription created.', ['channel_id' => $channel->id]);

            return;
        }

        Log::warning('X Account Activity subscription failed - this account will rely on scheduled polling instead of real-time delivery (likely the Pay Per Use tier\'s 3-subscription cap).', [
            'channel_id' => $channel->id,
            'body'       => $response['data'] ?? null,
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
                customerAvatarUrl: $sender['profile_image_url'] ?? null,
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
     * webhooks/quickstart). Uses ads.x.client_secret - confirmed via
     * XAdService's own oauth_consumer_key usage that ads.x.client_id/
     * client_secret are genuinely this app's OAuth 1.0a "API Secret Key"
     * (Consumer Secret), a completely different credential pair from the
     * OAuth 2.0 messaging.x.client_id/client_secret used for the PKCE
     * connect flow above - X's docs are explicit CRC/signature
     * verification use the consumer secret, never a bearer/access token.
     */
    public function crcResponseToken(string $crcToken): string
    {
        return 'sha256=' . base64_encode(hash_hmac('sha256', $crcToken, (string) adminSetting('ads.x.client_secret'), true));
    }

    /**
     * Verifies the x-twitter-webhooks-signature header on an inbound
     * event POST - same consumer secret, HMAC-SHA256 over the raw
     * request body (confirmed via docs.x.com/x-api/webhooks/quickstart).
     */
    public function verifySignature(Request $request): bool
    {
        $header = $request->header('x-twitter-webhooks-signature', '');
        $expected = 'sha256=' . base64_encode(hash_hmac('sha256', $request->getContent(), (string) adminSetting('ads.x.client_secret'), true));

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
        $externalId = $payload['for_user_id'] ?? null;
        $channel = $externalId ? MessageChannel::where('platform', 'x')->where('external_id', $externalId)->first() : null;

        if (!$channel) {
            Log::warning('X Account Activity webhook event arrived for an unrecognized for_user_id - dropped.', [
                'for_user_id'      => $externalId,
                'known_x_external_ids' => MessageChannel::where('platform', 'x')->pluck('external_id'),
            ]);

            return false;
        }

        $events = $payload['direct_message_events'] ?? [];
        $users = collect($payload['users'] ?? []);
        $processed = false;

        foreach ($events as $event) {
            $senderId = $event['message_create']['sender_id'] ?? $event['sender_id'] ?? null;
            $text = $event['message_create']['message_data']['text'] ?? $event['text'] ?? null;
            $conversationId = $event['message_create']['target']['recipient_id'] ?? $event['dm_conversation_id'] ?? null;

            // No sender, or this is an echo of our own outbound send -
            // that already got a local Message row at send time (same
            // skip logic as pollMessages()).
            if (!$senderId || $senderId === $channel->external_id) {
                continue;
            }

            $sender = $users->get($senderId) ?? $users->firstWhere('id', $senderId) ?? [];

            ProcessInboundMessage::dispatch(
                socialAccountId: $channel->social_account_id,
                customerExternalId: $senderId,
                customerName: $sender['name'] ?? $sender['username'] ?? $sender['screen_name'] ?? null,
                customerAvatarUrl: $sender['profile_image_url'] ?? null,
                externalConversationId: $conversationId,
                externalMessageId: $event['id'] ?? null,
                body: $text,
            );

            $processed = true;
        }

        return $processed;
    }
}
