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
 * Microsoft Teams - unlike every other platform in this module, there's
 * no direct "Teams API" a bot talks to. Teams is one of several channels
 * fronted by the Azure Bot Service / Bot Framework: a bot is registered
 * once (an Azure Bot resource, giving a Microsoft App ID + App Password),
 * and the *Bot Framework Connector* relays activities between the bot and
 * whichever channels it's configured for. That relay is what this class
 * actually talks to (login.microsoftonline.com for tokens, the
 * per-activity `serviceUrl` for the Connector API itself) - "Teams" here
 * just means the bot's Teams app manifest has been added/sideloaded so
 * users can 1:1 chat with it, not a separate integration surface.
 *
 * Three things this makes structurally different from this module's other
 * platforms:
 *
 * 1. Auth in *both* directions is Azure AD, not a platform-specific
 *    scheme: outbound calls get a Connector API bearer token via an
 *    OAuth 2.0 client_credentials grant (App ID + App Password), and
 *    inbound requests carry a bearer JWT that has to be validated the
 *    same way any OIDC-issued token would be - signature checked against
 *    Microsoft's published JWKS (login.botframework.com/v1/.well-known/
 *    keys), not an HMAC shared secret like LINE/Zalo/Slack.
 *
 * 2. There's no per-app shared webhook the way Slack/Zalo/Meta have, nor
 *    a setWebhook API call like Telegram - each Azure Bot registration's
 *    "Messaging endpoint" is configured by hand in the Azure Portal, one
 *    URL per registration, the same shape as LINE's manual paste-the-URL
 *    setup (see MessageChannelController::storeTeams()). That also means
 *    one MessageChannel row = one full bot registration here, not one
 *    workspace install of a shared app the way Slack channels are.
 *
 * 3. Replying requires the `serviceUrl` from that conversation's most
 *    recently received activity - Microsoft's own docs are explicit this
 *    can differ per conversation/cloud and must never be hardcoded, which
 *    is why handleActivity() below persists it onto conversations.meta
 *    (see the migration that added that column) rather than treating it
 *    as a fixed per-channel constant the way every other platform's base
 *    URL is.
 *
 * Endpoints/shapes verified this session against learn.microsoft.com:
 * the client_credentials token exchange, the Activity schema (message
 * type, from/conversation/serviceUrl/attachments), the file vs. inline-
 * image attachment authentication split (a file attachment's
 * `content.downloadUrl` is pre-authenticated; an image attachment's
 * `contentUrl` needs the bot's own bearer token), and the JWT validation
 * requirements (issuer https://api.botframework.com, audience = the
 * bot's own App ID, RS256 signature via the published JWKS).
 */
class TeamsMessagingService
{
    private string $tokenUrl;
    private string $scope;
    private string $jwksUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->tokenUrl = adminSetting('messaging.teams.token_url') ?: 'https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token';
        $this->scope = adminSetting('messaging.teams.scope') ?: 'https://api.botframework.com/.default';
        $this->jwksUrl = adminSetting('messaging.teams.jwks_url') ?: 'https://login.botframework.com/v1/.well-known/keys';
    }

    private function fetchAccessToken(string $appId, string $appPassword): ?array
    {
        $response = $this->apiService->post($this->tokenUrl, [], [
            'grant_type'    => 'client_credentials',
            'client_id'     => $appId,
            'client_secret' => $appPassword,
            'scope'         => $this->scope,
        ], 'form');

        return $response['success'] ? $response['data'] : null;
    }

    /**
     * There's no lightweight "who am I" REST call in the Bot Framework the
     * way Telegram has getMe or Discord has /users/@me - successfully
     * minting a Connector API token *is* the verification that an App
     * ID/Password pair is real, so storeTeams() uses this instead.
     */
    public function verifyCredentials(string $appId, string $appPassword): array
    {
        $token = $this->fetchAccessToken($appId, $appPassword);

        if (!$token || empty($token['access_token'])) {
            return ['success' => false];
        }

        return ['success' => true];
    }

    /**
     * Cached rather than fetched per-send - client_credentials tokens are
     * normally valid for an hour, and minting a fresh one on every single
     * outbound message would be both wasteful and, at any real send
     * volume, eventually rate-limited by Azure AD.
     */
    /**
     * Only ever caches a genuine token, never a failure - Cache::remember()
     * would otherwise cache a null result from a transient Azure AD outage
     * (or credentials that were wrong and have since been fixed) for the
     * same ~50 minutes as a real token, silently blocking every send
     * until the cache expires on its own.
     */
    private function ensureAccessToken(MessageChannel $channel): ?string
    {
        $cacheKey = "teams_token_{$channel->id}";
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
     * Unlike Discord/Slack, there's no API call that can cold-open a
     * Teams conversation from just a user id - the Connector API can only
     * post into a conversation whose serviceUrl+id it already knows,
     * which only exists once that customer has sent at least one message
     * (see class docblock point 3). Same practical constraint this module
     * already accepted for Discord ("must share a server first") and
     * Slack (needs a resolvable DM channel) - just enforced earlier here,
     * before any API call is even attempted.
     */
    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $serviceUrl = $conversation->meta['service_url'] ?? null;

        if (!$serviceUrl || !$conversation->external_conversation_id) {
            return ['success' => false, 'error' => 'Cannot start a Teams conversation from this side - wait for the customer to message the bot first.'];
        }

        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Bot Framework Connector API.'];
        }

        $text = $data['body'] ?? '';

        // Same "append the URL, let the client render it" simplification
        // already used for Discord/Slack/X media sends in this module,
        // rather than shaping a Teams-specific image/video attachment.
        if (!empty($data['media_url'])) {
            $text = trim($text . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post(
            rtrim($serviceUrl, '/') . "/v3/conversations/{$conversation->external_conversation_id}/activities",
            ['Authorization' => "Bearer {$accessToken}"],
            ['type' => 'message', 'text' => $text]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Bot Framework Connector API request failed.'];
        }

        return ['success' => true, 'external_message_id' => $response['data']['id'] ?? null];
    }

    /**
     * Update & Delete Bot Messages is a documented, Teams-supported
     * Bot Framework capability - PUT/DELETE against the same activity
     * resource sendMessage() got its id (external_message_id) from,
     * scoped by the same per-conversation serviceUrl.
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $channel = $message->conversation->channel;
        $serviceUrl = $message->conversation->meta['service_url'] ?? null;

        if (!$serviceUrl) {
            return ['success' => false, 'error' => 'Missing Teams conversation service URL.'];
        }

        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Bot Framework Connector API.'];
        }

        $response = $this->apiService->put(
            rtrim($serviceUrl, '/') . "/v3/conversations/{$message->conversation->external_conversation_id}/activities/{$message->external_message_id}",
            ['Authorization' => "Bearer {$accessToken}"],
            ['type' => 'message', 'text' => $newBody]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Bot Framework Connector API request failed.'];
        }

        return ['success' => true];
    }

    public function deleteMessage(Message $message): array
    {
        $channel = $message->conversation->channel;
        $serviceUrl = $message->conversation->meta['service_url'] ?? null;

        if (!$serviceUrl) {
            return ['success' => false, 'error' => 'Missing Teams conversation service URL.'];
        }

        $accessToken = $this->ensureAccessToken($channel);

        if (!$accessToken) {
            return ['success' => false, 'error' => 'Could not authenticate with the Bot Framework Connector API.'];
        }

        $response = $this->apiService->delete(
            rtrim($serviceUrl, '/') . "/v3/conversations/{$message->conversation->external_conversation_id}/activities/{$message->external_message_id}",
            ['Authorization' => "Bearer {$accessToken}"]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Bot Framework Connector API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Only ever caches a genuine key set, never a failure - caching an
     * empty ['keys' => []] for 24h on one transient fetch failure would
     * reject every inbound Teams message as unverifiable for the rest of
     * that window, even once Microsoft's endpoint recovers seconds later.
     */
    private function getJwks(): array
    {
        $cached = Cache::get('teams_jwks');

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
        Cache::put('teams_jwks', $jwks, 86400);

        return $jwks;
    }

    /**
     * Full OIDC-style validation, matching what Microsoft's own docs
     * require a bot do since there's no SDK doing it here: signature
     * checked against the published JWKS, issuer pinned to the Bot
     * Framework's own value, and audience pinned to *this channel's* App
     * ID specifically - a token minted for a different bot must not be
     * accepted just because it's otherwise well-formed.
     */
    public function verifyActivityToken(Request $request, MessageChannel $channel): bool
    {
        $header = (string) $request->header('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        $jwt = substr($header, 7);

        try {
            // Bot Framework's published JWKS omits "alg" on each key (valid
            // per RFC 7517 - it's optional there), so parseKeySet() needs an
            // explicit default or it rejects every key in the set. RS256 is
            // the only algorithm the OpenID metadata document advertises.
            $keys = JWK::parseKeySet($this->getJwks(), 'RS256');
            $claims = JWT::decode($jwt, $keys);
        } catch (\Throwable) {
            return false;
        }

        return ($claims->iss ?? null) === 'https://api.botframework.com'
            && ($claims->aud ?? null) === $channel->external_id;
    }

    /**
     * Only `type === 'message'` activities are a customer message - Teams
     * also delivers conversationUpdate (member added/removed),
     * typing, and installationUpdate activities to the same endpoint,
     * none of which represent something to show in the inbox. There's no
     * bot-echo case to filter here the way Slack/Discord need: activities
     * this bot sends go out via the Connector API directly, they're never
     * mirrored back through this same webhook.
     */
    public function handleActivity(array $activity, MessageChannel $channel): void
    {
        if (($activity['type'] ?? null) !== 'message') {
            return;
        }

        $from = $activity['from'] ?? [];
        $conversationId = $activity['conversation']['id'] ?? null;
        $serviceUrl = $activity['serviceUrl'] ?? null;

        if (empty($from['id']) || !$conversationId || !$serviceUrl) {
            return;
        }

        $attachments = [];

        foreach ($activity['attachments'] ?? [] as $attachment) {
            $result = $this->rehostAttachment($channel, $attachment);

            if ($result) {
                $attachments[] = $result;
            }
        }

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: $from['id'],
            customerName: $from['name'] ?? null,
            externalConversationId: $conversationId,
            externalMessageId: $activity['id'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $activity['text'] ?? null,
            attachments: $attachments,
            conversationMeta: [
                'service_url' => $serviceUrl,
                'tenant_id'   => $activity['conversation']['tenantId'] ?? null,
            ],
        );
    }

    /**
     * A genuine file upload's `content.downloadUrl` is a pre-authenticated,
     * short-lived SharePoint link - fetched with no auth header. An inline
     * image's `contentUrl` instead needs the bot's own bearer token, the
     * same auth-gated-media shape as LINE/Slack's attachment hosts. Either
     * way the result is re-hosted to S3 rather than stored as one of
     * these (both are short-lived/expiring links).
     */
    private function rehostAttachment(MessageChannel $channel, array $attachment): ?array
    {
        $contentType = $attachment['contentType'] ?? '';

        // Raw Http facade calls below throw a ConnectionException on a
        // DNS/network failure rather than returning an unsuccessful
        // Response - handleActivity() calls this synchronously while
        // still handling the webhook request, so a dead attachment URL
        // must not crash the whole request.
        try {
            if ($contentType === 'application/vnd.microsoft.teams.file.download.info') {
                $url = $attachment['content']['downloadUrl'] ?? null;

                if (!$url) {
                    return null;
                }

                $response = Http::get($url);
                $mimeType = 'application/octet-stream';
                $type = 'file';
            } elseif (str_starts_with($contentType, 'image/')) {
                $url = $attachment['contentUrl'] ?? null;

                if (!$url) {
                    return null;
                }

                $accessToken = $this->ensureAccessToken($channel);
                $response = $accessToken ? Http::withToken($accessToken)->get($url) : null;
                $mimeType = $contentType;
                $type = 'image';
            } else {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        if (!$response || !$response->successful()) {
            return null;
        }

        $fileName = uniqid() . '_' . ($attachment['name'] ?? 'file');
        $s3Path = "uploads/teams/media/{$fileName}";

        Storage::disk('r2')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return [
            'type'      => $type,
            'url'       => Storage::disk('r2')->url($s3Path),
            'mime_type' => $mimeType,
            'file_name' => $attachment['name'] ?? null,
        ];
    }
}
