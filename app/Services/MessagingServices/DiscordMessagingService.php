<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\Conversation;
use App\Services\ApiService;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
/**
 * Discord - REST API (discord.com/api/v10) for sending, but there is no
 * webhook (or any REST polling substitute - GET /users/@me/channels
 * always returns an empty list for bot accounts) for a bot to learn about
 * inbound DMs. The Gateway WebSocket's MESSAGE_CREATE dispatch is the
 * *only* way a bot can discover a new Direct Message at all - confirmed
 * this session against discord/discord-api-docs issue threads, not just
 * general docs. That's why this platform, uniquely in this module, needs
 * RunDiscordGatewayListener - a persistent daemon process, not a webhook
 * controller or a scheduled poll - to receive anything. This class only
 * covers the REST half (sending, DM channel resolution, bot token
 * verification); the daemon command owns the Gateway connection and
 * calls back into handleGatewayMessage() when it sees one.
 *
 * Also worth knowing: Discord users generally must share a server (guild)
 * with your bot before they can DM it (subject to their own privacy
 * settings) - there's no equivalent of "any phone number can message your
 * WhatsApp number." A customer has to already be a member of a Discord
 * server your bot is in.
 *
 * IMPORTANT about redirect()/handleOAuthCallback() below: Discord's OAuth2
 * "authorize" flow (docs.discord.com/developers/topics/oauth2) is real and
 * self-service, but it is NOT an alternative way to obtain the credential
 * used to send messages or open the Gateway connection - that is, and
 * always must be, the static bot token from the Developer Portal's Bot
 * tab (verifyBotToken()/storeDiscord() above). What this OAuth flow
 * actually does is add the bot to a server the authorizing user manages
 * (`scope=bot`) via a proper consent screen instead of a hand-built invite
 * link, and optionally returns a short-lived access token scoped to that
 * one authorization grant - useful for confirming which guild the bot was
 * added to, not for authenticating as the bot. A customer still needs to
 * share *some* server with the bot before DMs work at all (see above), so
 * this flow is what gets the bot onto one.
 */
class DiscordMessagingService
{
    private string $baseUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('chats.discord.base_url') ?: 'https://discord.com/api/v10/';
    }

    private function authHeader(MessageChannel $channel): array
    {
        return ['Authorization' => 'Bot ' . $channel->access_token];
    }

    public function verifyBotToken(string $token): array
    {
        $response = $this->apiService->get($this->baseUrl . 'users/@me', ['Authorization' => 'Bot ' . $token]);

        if (!$response['success']) {
            return ['success' => false];
        }

        return ['success' => true, 'bot' => $response['data']];
    }

    /**
     * Must exactly match the URL registered in the Developer Portal's
     * OAuth2 > General > Redirects list, character for character - built
     * from the app's own current URL (not config('services.app_url'),
     * which every other platform's redirect()/handleCallback() in this
     * module relies on but is presently misconfigured to a different
     * domain) so this keeps working correctly across environments without
     * inheriting that problem.
     */
    private function redirectUri(): string
    {
        return url('/admin/messaging/channels/discord');
    }

    /**
     * scope=bot is the "add this bot to a server" grant - permissions=0
     * deliberately requests no guild permission bits, since DMing a user
     * isn't governed by guild permissions at all (only by that user's own
     * privacy settings, see class docblock); there's nothing this app
     * needs a guild permission bit for. response_type=code is what makes
     * Discord also hand back an authorization code on redirect, which
     * handleOAuthCallback() exchanges for the grant's access token/guild
     * info below.
     */
    public function redirect(string $state)
    {
        $scopes = [
            'identify', 'guilds', 'gdm.join', 'rpc.notifications.read', 
            'rpc.video.read', 'rpc.screenshare.write', 'messages.read', 
            'applications.store.update', 'openid', 'applications.commands.permissions.update', 
            'email', 'guilds.join', 'bot', 'rpc.voice.read', 
            'rpc.video.write', 'rpc.activities.write', 'applications.builds.read', 
            'applications.entitlements', 'connections', 'guilds.members.read', 
            'rpc', 'rpc.voice.write', 'rpc.screenshare.read', 
            'webhook.incoming', 'applications.commands', 'role_connections.write'
        ];

        $queryParams = [
            'client_id'        => adminSetting('chats.discord.client_id'),
            'permissions'      => '8866461766385655', // Updated to match your exact URL permissions
            'response_type'    => 'code',
            'redirect_uri'     => $this->redirectUri(),
            'integration_type' => '0',
            'scope'            => implode(' ', $scopes),
            'state'            => $state,
        ];

        $url = 'https://discord.com/oauth2/authorize?' . http_build_query($queryParams);

        return Redirect::away($url);
    }

    /**
     * Exchanges the authorization code for this grant's access token, and
     * - since that token isn't the bot token this app actually sends with
     * (see class docblock) - looks up the already-connected MessageChannel
     * to attach the resulting guild info to, matched via the Discord
     * Application's Client ID, which Discord assigns as the *same*
     * snowflake as the bot user's own id (i.e. the value storeDiscord()
     * already saved as external_id). If no channel has been connected
     * with a bot token yet, that's surfaced as its own clear error rather
     * than silently doing nothing, since this grant alone can't create a
     * working channel.
     */
    public function handleOAuthCallback(string $code): array
    {
        $clientId = adminSetting('chats.discord.client_id');

        $tokenResponse = $this->apiService->post($this->baseUrl . 'oauth2/token', [], [
            'client_id'     => $clientId,
            'client_secret' => adminSetting('chats.discord.client_secret'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
        ], 'form');
       
        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error_description'] ?? 'Failed to exchange the Discord authorization code.'];
        }
        $data = $tokenResponse['data'];
       

        $guild = $data['guild'] ?? [];
        $webhook = $data['webhook'] ?? [];

        $channel = MessageChannel::updateOrCreate(
            [
                'platform'    => 'discord',
                'user_id'     => Auth::id(),
                'external_id' => $guild['id'] ?? null,
            ],
            [
                'name'           => $guild['name'] ?? null,
                'username'       => $guild['name'] ?? null,
                'avatar_url'     => isset($guild['icon']) 
                    ? "https://cdn.discordapp.com/icons/{$guild['id']}/{$guild['icon']}.png" 
                    : null,
                'access_token'   => $data['access_token'] ?? null,
                'refresh_token'  => $data['refresh_token'] ?? null,
                'expires_at'     => isset($data['expires_in']) 
                    ? Carbon::now()->addSeconds($data['expires_in']) 
                    : null,
                'meta'           => json_encode([
                    'token_type'     => $data['token_type'] ?? null,
                    'scope'          => $data['scope'] ?? null,
                    'id_token'       => $data['id_token'] ?? null,
                    'guild'          => $guild,
                    'webhook'        => $webhook,
                    'webhook_url'    => $webhook['url'] ?? null,
                    'channel_id'     => $webhook['channel_id'] ?? null,
                ]),
                'status'         => true,
                'last_synced_at' => Carbon::now(),
            ]
        );
        //$channel = MessageChannel::where('platform', 'discord')->where('external_id', $clientId)->first();

        if (!$channel) {
            return ['success' => false, 'error' => 'Connect this bot with its bot token first (see "Connect Discord Bot" below), then authorize it to a server.'];
        }

        return ['success' => true, 'guild' => $guild, 'channel' => $channel];
    }

    /**
     * POST /users/@me/channels with a recipient_id is the standard,
     * idempotent way a bot opens (or re-fetches the existing) DM channel
     * with a user it already knows the ID of - resolved fresh on every
     * send rather than trusting a possibly-stale cached channel ID, since
     * the call is cheap and always returns the same channel if one
     * already exists.
     */
    private function resolveDmChannel(MessageChannel $channel, string $userId): ?string
    {
        $response = $this->apiService->post($this->baseUrl . 'users/@me/channels', $this->authHeader($channel), [
            'recipient_id' => $userId,
        ]);

        return $response['success'] ? ($response['data']['id'] ?? null) : null;
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $dmChannelId = $this->resolveDmChannel($channel, $conversation->customer_external_id);

        if (!$dmChannelId) {
            return ['success' => false, 'error' => 'Could not open a DM channel with this Discord user - they may not share a server with the bot, or may have DMs from server members disabled.'];
        }

        $content = $data['body'] ?? '';

        // Discord auto-unfurls a plain URL in the message content into a
        // rich embed, so - same simplification used for X DMs earlier in
        // this module - the media URL is appended to the text rather than
        // uploading as multipart/form-data.
        if (!empty($data['media_url'])) {
            $content = trim($content . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post($this->baseUrl . "channels/{$dmChannelId}/messages", $this->authHeader($channel), [
            'content' => $content,
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'Discord API request failed.'];
        }

        return [
            'success'                 => true,
            'external_message_id'     => $response['data']['id'] ?? null,
            'external_conversation_id' => $dmChannelId,
        ];
    }

    /**
     * DM channel resolved fresh here too, same reasoning as sendMessage()
     * - cheap, idempotent, and avoids trusting a possibly-stale stored
     * channel id.
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $channel = $message->conversation->channel;
        $dmChannelId = $this->resolveDmChannel($channel, $message->conversation->customer_external_id);

        if (!$dmChannelId) {
            return ['success' => false, 'error' => 'Could not resolve the Discord DM channel.'];
        }

        $response = $this->apiService->patch($this->baseUrl . "channels/{$dmChannelId}/messages/{$message->external_message_id}", $this->authHeader($channel), [
            'content' => $newBody,
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'Discord API request failed.'];
        }

        return ['success' => true];
    }

    public function deleteMessage(Message $message): array
    {
        $channel = $message->conversation->channel;
        $dmChannelId = $this->resolveDmChannel($channel, $message->conversation->customer_external_id);

        if (!$dmChannelId) {
            return ['success' => false, 'error' => 'Could not resolve the Discord DM channel.'];
        }

        $response = $this->apiService->delete($this->baseUrl . "channels/{$dmChannelId}/messages/{$message->external_message_id}", $this->authHeader($channel));

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'Discord API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Called by RunDiscordGatewayListener when a MESSAGE_CREATE dispatch
     * arrives for a DM (no guild_id) from a non-bot author. Everything
     * about parsing the Gateway payload itself lives in the daemon
     * command; this only knows how to turn one already-identified inbound
     * message into the generic ProcessInboundMessage shape every other
     * platform in this module funnels through.
     */
    public function handleGatewayMessage(array $message, MessageChannel $channel): void
    {
        $author = $message['author'] ?? [];
        $attachments = [];

        foreach ($message['attachments'] ?? [] as $attachment) {
            $contentType = $attachment['content_type'] ?? '';
            $type = match (true) {
                str_starts_with($contentType, 'image/') => 'image',
                str_starts_with($contentType, 'video/') => 'video',
                str_starts_with($contentType, 'audio/') => 'audio',
                default => 'file',
            };

            // Discord's CDN attachment URLs are directly public (unlike
            // WhatsApp/LINE's auth-gated media hosts), so no re-hosting
            // step is needed here.
            $attachments[] = [
                'type'      => $type,
                'url'       => $attachment['url'],
                'mime_type' => $contentType ?: null,
                'file_name' => $attachment['filename'] ?? null,
                'file_size' => $attachment['size'] ?? null,
            ];
        }

        $avatarUrl = !empty($author['avatar']) && !empty($author['id'])
            ? "https://cdn.discordapp.com/avatars/{$author['id']}/{$author['avatar']}.png"
            : null;

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: $author['id'],
            customerName: $author['global_name'] ?? $author['username'] ?? null,
            customerAvatarUrl: $avatarUrl,
            externalConversationId: $message['channel_id'] ?? null,
            externalMessageId: $message['id'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $message['content'] ?? null,
            attachments: $attachments,
        );
    }
}
