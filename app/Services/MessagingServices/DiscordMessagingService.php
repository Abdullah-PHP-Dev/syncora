<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\Conversation;
use App\Services\ApiService;

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
 */
class DiscordMessagingService
{
    private string $baseUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('messaging.discord.base_url') ?: 'https://discord.com/api/v10/';
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
