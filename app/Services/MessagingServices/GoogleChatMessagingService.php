<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Google Chat API (chat.googleapis.com) - yes, Google does provide a real,
 * self-service API for this: register any Google Cloud project (free),
 * enable the Google Chat API, and configure a "Chat app" backed by an
 * HTTP endpoint. No approval wait to start building or testing, the same
 * bar as LINE/Discord/Slack rather than WhatsApp Embedded Signup's
 * Tech Provider process. Reaching users *outside* your own Workspace
 * domain (or any user at all, if the developer's Google Cloud project is
 * on a personal @gmail.com account rather than a Workspace account)
 * does need a Google Workspace Marketplace listing, which for a
 * @gmail.com-owned project can only be published *publicly* (subject to
 * Google's review) - worth knowing going in, the same kind of real
 * distribution constraint this module already accepted for Slack (app
 * review past a certain scale) and Discord (privileged intents past 100
 * servers).
 *
 * Auth is Google Cloud's standard service-account model in both
 * directions, not a platform-specific scheme:
 *
 * 1. Outbound (sending): a self-signed JWT (iss/sub = the service
 *    account's client_email, scope = chat.bot) is exchanged at Google's
 *    OAuth token endpoint for a bearer access token - the classic "OAuth
 *    2.0 for Server to Server Applications" flow, needing no user consent
 *    since Chat's "app authentication" mode is designed exactly for this.
 *
 * 2. Inbound (receiving): every request Google Chat sends to the
 *    configured HTTP endpoint carries a bearer JWT self-signed by Google's
 *    own *system* service account (chat@system.gserviceaccount.com,
 *    identical for every Chat app on Earth), verified here against
 *    Google's published JWKS the same shape as Teams' Bot Framework
 *    verification - except the audience claim is the Cloud project's
 *    *project number*, a value that only exists in the GCP Console itself
 *    and isn't part of a service account key file, so - like Zalo's OA
 *    Secret Key - it has to be collected as its own field when connecting
 *    a channel (see MessageChannelController::storeGoogleChat()).
 *
 * A 1:1 DM space is simpler to reply into than Teams' equivalent: Google
 * auto-adds the Chat app as a member the moment a user starts messaging
 * it, and the space's own resource name (`spaces/AAAA...`) is a stable,
 * directly-reusable identifier for every future reply - no serviceUrl-
 * style per-conversation value to track alongside it.
 *
 * Endpoints/shapes verified this session against developers.google.com:
 * the JWT-bearer service-account token exchange, the MESSAGE interaction
 * event payload shape, spaces.messages.create, the chat@system.
 * gserviceaccount.com JWKS verification requirements, and the media
 * download endpoint for file attachments (media.download via
 * attachmentDataRef.resourceName, not the human-facing downloadUri).
 */
class GoogleChatMessagingService
{
    private string $baseUrl;
    private string $tokenUrl;
    private string $scope;
    private string $jwksUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('messaging.google_chat.base_url') ?: 'https://chat.googleapis.com/v1/';
        $this->tokenUrl = adminSetting('messaging.google_chat.token_url') ?: 'https://oauth2.googleapis.com/token';
        $this->scope = adminSetting('messaging.google_chat.scope') ?: 'https://www.googleapis.com/auth/chat.bot';
        $this->jwksUrl = adminSetting('messaging.google_chat.jwks_url') ?: 'https://www.googleapis.com/service_accounts/v1/jwk/chat@system.gserviceaccount.com';
    }

    private function fetchAccessToken(string $clientEmail, string $privateKey): ?array
    {
        $now = time();

        try {
            // A malformed private_key (a pasted JSON key with truncated or
            // corrupted PEM content) makes the underlying openssl_sign()
            // call throw rather than fail gracefully - caught here so a
            // bad paste surfaces as the same "could not authenticate"
            // response as any other invalid credential, not a 500.
            $assertion = JWT::encode([
                'iss'   => $clientEmail,
                'scope' => $this->scope,
                'aud'   => $this->tokenUrl,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $privateKey, 'RS256');
        } catch (\Throwable) {
            return null;
        }

        $response = $this->apiService->post($this->tokenUrl, [], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ], 'form');

        return $response['success'] ? $response['data'] : null;
    }

    /**
     * No "who am I" REST call exists for a bare service account either -
     * same situation as Teams' App ID/Password. Successfully minting a
     * token confirms the client_email/private_key pair is a real,
     * correctly-formatted service account credential.
     */
    public function verifyCredentials(string $clientEmail, string $privateKey): array
    {
        $token = $this->fetchAccessToken($clientEmail, $privateKey);

        if (!$token || empty($token['access_token'])) {
            return ['success' => false];
        }

        return ['success' => true];
    }

    /**
     * Only ever caches a genuine token, never a failure - Cache::remember()
     * would otherwise cache a null result from a transient Google outage
     * (or credentials that were wrong and have since been fixed) for the
     * same ~50 minutes as a real token, silently blocking every send
     * until the cache expires on its own.
     */
    private function ensureAccessToken(MessageChannel $channel): ?string
    {
        $cacheKey = "google_chat_token_{$channel->id}";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $token = $this->fetchAccessToken($channel->external_id, $channel->access_token);
        $accessToken = $token['access_token'] ?? null;

        if ($accessToken) {
            Cache::put($cacheKey, $accessToken, 3000);
        }

        return $accessToken;
    }

    /**
     * Unlike Teams, there's no "wait for the first message" guard needed
     * here beyond the generic missing-conversation-id case: the space
     * name is created the moment the user starts the DM, and this app is
     * auto-added as a member of it at that same moment, so any space
     * name captured from an inbound event is already postable-to for as
     * long as that DM exists.
     */
    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        if (!$conversation->external_conversation_id) {
            return ['success' => false, 'error' => 'Cannot start a Google Chat conversation from this side - wait for the customer to message the app first.'];
        }

        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Google Chat API.'];
        }

        $text = $data['body'] ?? '';

        // Same "append the URL, let the client render it" simplification
        // already used for Discord/Slack/X/Teams media sends in this
        // module, rather than shaping a Chat-specific card/attachment.
        if (!empty($data['media_url'])) {
            $text = trim($text . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post(
            $this->baseUrl . $conversation->external_conversation_id . '/messages',
            ['Authorization' => "Bearer {$accessToken}"],
            ['text' => $text]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Google Chat API request failed.'];
        }

        return ['success' => true, 'external_message_id' => $response['data']['name'] ?? null];
    }

    /**
     * external_message_id already IS the message's full resource name
     * ("spaces/{id}/messages/{id}", see sendMessage() above) - both update
     * and delete address it directly, no separate id assembly needed. Only
     * app-authenticated messages the Chat app itself created can be
     * updated/deleted this way, which is exactly what every message this
     * service ever sends is.
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $channel = $message->conversation->channel;
        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Google Chat API.'];
        }

        $response = $this->apiService->put(
            $this->baseUrl . $message->external_message_id . '?updateMask=text',
            ['Authorization' => "Bearer {$accessToken}"],
            ['text' => $newBody]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Google Chat API request failed.'];
        }

        return ['success' => true];
    }

    public function deleteMessage(Message $message): array
    {
        $channel = $message->conversation->channel;
        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Google Chat API.'];
        }

        $response = $this->apiService->delete(
            $this->baseUrl . $message->external_message_id,
            ['Authorization' => "Bearer {$accessToken}"]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Google Chat API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Google's system service account key set - fixed and identical for
     * every Chat app that exists, so (unlike the per-channel token cache
     * above) this is cached once globally rather than per-channel.
     */
    /**
     * Only ever caches a genuine key set, never a failure - caching an
     * empty ['keys' => []] for 24h on one transient fetch failure would
     * reject every inbound Google Chat message as unverifiable for the
     * rest of that window, even once Google's endpoint recovers seconds
     * later.
     */
    private function getJwks(): array
    {
        $cached = Cache::get('google_chat_jwks');

        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::get($this->jwksUrl);
        } catch (\Throwable) {
            return ['keys' => []];
        }

        if (!$response->successful()) {
            return ['keys' => []];
        }

        $jwks = $response->json();
        Cache::put('google_chat_jwks', $jwks, 86400);

        return $jwks;
    }

    /**
     * Issuer is fixed and identical across every Chat app; audience is
     * the *project number* collected when this channel was connected
     * (see class docblock) - a token minted for a different Cloud project
     * must not be accepted just because Google itself signed it.
     */
    public function verifyRequestToken(Request $request, MessageChannel $channel): bool
    {
        $header = (string) $request->header('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        $jwt = substr($header, 7);

        try {
            $keys = JWK::parseKeySet($this->getJwks(), 'RS256');
            $claims = JWT::decode($jwt, $keys);
        } catch (\Throwable) {
            return false;
        }

        return ($claims->iss ?? null) === 'chat@system.gserviceaccount.com'
            && ($claims->aud ?? null) === (string) ($channel->meta['project_number'] ?? null);
    }

    /**
     * Only `MESSAGE` interaction events from a DM space are a customer
     * message worth showing in the inbox - Chat also delivers
     * ADDED_TO_SPACE/REMOVED_FROM_SPACE and (for group spaces, out of
     * scope here) other event types to the same endpoint. No bot-echo
     * case to filter, same reasoning as Teams: this app's own replies go
     * out via spaces.messages.create directly, never mirrored back
     * through this endpoint.
     */
    public function handleEvent(array $payload, MessageChannel $channel): void
    {
        Conversation::Create([
            'platform'      => 'google',
            'external_conversation_id'        => null,
            'meta'        => json_decode($payload['type']),
            'user_id'     => 1,
            'customer_external_id' =>'34543',
            'unread_count'   => 1,
            'status' => true,
            'assigned_user_id' => 1
        ]);
        if (($payload['type'] ?? null) !== 'MESSAGE') {
            return;
        }

        $message = $payload['message'] ?? [];
        $space = $message['space'] ?? $payload['space'] ?? [];

        if (($space['type'] ?? null) !== 'DM') {
            return;
        }

        $sender = $message['sender'] ?? $payload['user'] ?? [];
        $spaceName = $space['name'] ?? null;

        if (empty($sender['name']) || !$spaceName) {
            return;
        }

        $attachments = [];

        foreach ($message['attachment'] ?? [] as $attachment) {
            $result = $this->rehostAttachment($channel, $attachment);

            if ($result) {
                $attachments[] = $result;
            }
        }

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: $sender['name'],
            customerName: $sender['displayName'] ?? null,
            customerAvatarUrl: $sender['avatarUrl'] ?? null,
            externalConversationId: $spaceName,
            externalMessageId: $message['name'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $message['argumentText'] ?? $message['text'] ?? null,
            attachments: $attachments,
        );
    }

    /**
     * Chat apps are explicitly told to use attachmentDataRef.resourceName
     * with the Media API (`GET .../{resourceName}?alt=media`, app-
     * authenticated) rather than the attachment's human-facing
     * downloadUri, which isn't meant for programmatic use.
     */
    private function rehostAttachment(MessageChannel $channel, array $attachment): ?array
    {
        $resourceName = $attachment['attachmentDataRef']['resourceName'] ?? null;

        if (!$resourceName) {
            return null;
        }

        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return null;
        }

        // Raw Http facade throws a ConnectionException on a DNS/network
        // failure rather than returning an unsuccessful Response - this
        // runs synchronously inside the webhook request (handleEvent()),
        // so a transient failure fetching the attachment must not crash
        // the whole request.
        try {
            $response = Http::withToken($accessToken)->get($this->baseUrl . $resourceName, ['alt' => 'media']);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $contentType = $attachment['contentType'] ?? $response->header('Content-Type') ?: 'application/octet-stream';
        $type = match (true) {
            str_starts_with($contentType, 'image/') => 'image',
            str_starts_with($contentType, 'video/') => 'video',
            str_starts_with($contentType, 'audio/') => 'audio',
            default => 'file',
        };

        $fileName = uniqid() . '_' . ($attachment['contentName'] ?? 'file');
        $s3Path = "uploads/google_chat/media/{$fileName}";

        Storage::disk('r2')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return [
            'type'      => $type,
            'url'       => Storage::disk('r2')->url($s3Path),
            'mime_type' => $contentType,
            'file_name' => $attachment['contentName'] ?? null,
        ];
    }
}
