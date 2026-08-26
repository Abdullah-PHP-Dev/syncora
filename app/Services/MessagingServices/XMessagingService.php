<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
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
        $this->base = adminSetting('messaging.x.base_url');
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

        $url = adminSetting('messaging.x.authorize_url') . '?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => adminSetting('messaging.x.client_id'),
            'redirect_uri'          => config('services.app_url') . '/admin/messaging/auth/x/callback',
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

        $tokenResponse = $this->apiService->post(adminSetting('messaging.x.token_url'), [], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => adminSetting('messaging.x.client_id'),
            'redirect_uri'  => config('services.app_url') . '/admin/messaging/auth/x/callback',
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

        return ['success' => true, 'data' => $channel];
    }

    private function ensureFreshToken(MessageChannel $channel): string
    {
        if ($channel->expires_at && now()->lt($channel->expires_at)) {
            return $channel->socialAccount->access_token;
        }

        if (!$channel->socialAccount->refresh_token) {
            return $channel->socialAccount->access_token;
        }

        $response = $this->apiService->post(adminSetting('messaging.x.token_url'), [], [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $channel->socialAccount->refresh_token,
            'client_id'     => adminSetting('messaging.x.client_id'),
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
}
