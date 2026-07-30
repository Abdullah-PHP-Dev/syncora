<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use Illuminate\Http\Request;

/**
 * Telegram Bot API - by far the simplest of the five: a single static bot
 * token (no OAuth handshake at all, created once via @BotFather), and
 * webhook subscription is a one-time POST to setWebhook rather than the
 * GET-challenge dance Meta's products require. Telegram also accepts a
 * plain URL for photo/video/document fields when sending, so - unlike
 * WhatsApp - there's no separate upload/media-ID step needed to send
 * media the admin already has a URL for.
 */
class TelegramMessagingService
{
    public function __construct(protected ApiService $apiService)
    {
    }

    private function apiUrl(string $botToken, string $method): string
    {
        return rtrim(adminSetting('messaging.telegram.api_base'), '/') . "/bot{$botToken}/{$method}";
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $chatId = $conversation->customer_external_id;

        if (!empty($data['media_url'])) {
            $type = $data['media_type'] ?? 'photo';
            $method = match ($type) {
                'video'    => 'sendVideo',
                'document', 'file' => 'sendDocument',
                'audio'    => 'sendAudio',
                default    => 'sendPhoto',
            };
            $field = match ($method) {
                'sendVideo'    => 'video',
                'sendDocument' => 'document',
                'sendAudio'    => 'audio',
                default        => 'photo',
            };

            $payload = ['chat_id' => $chatId, $field => $data['media_url']];

            if (!empty($data['body'])) {
                $payload['caption'] = $data['body'];
            }
        } else {
            $method = 'sendMessage';
            $payload = ['chat_id' => $chatId, 'text' => $data['body']];
        }

        $response = $this->apiService->post($this->apiUrl($channel->access_token, $method), [], $payload);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['description'] ?? 'Telegram API request failed.'];
        }

        return ['success' => true, 'external_message_id' => (string) ($response['data']['result']['message_id'] ?? '')];
    }

    /**
     * Telegram doesn't sign webhook payloads - instead it echoes back
     * whatever secret_token was configured on setWebhook() as the
     * X-Telegram-Bot-Api-Secret-Token header on every call, which is
     * compared here. This app reuses message_channels.verify_token for
     * that secret, same column Meta's hub.verify_token uses for the other
     * three platforms.
     */
    public function verifySignature(Request $request, MessageChannel $channel): bool
    {
        return hash_equals((string) $channel->verify_token, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'));
    }

    public function registerWebhook(MessageChannel $channel, string $webhookUrl): array
    {
        $response = $this->apiService->post($this->apiUrl($channel->access_token, 'setWebhook'), [], [
            'url'          => $webhookUrl,
            'secret_token' => $channel->verify_token,
        ]);

        if (!$response['success'] || empty($response['data']['ok'])) {
            return ['success' => false, 'error' => $response['data']['description'] ?? 'Failed to register Telegram webhook.'];
        }

        return ['success' => true];
    }

    public function handleWebhook(array $payload, MessageChannel $channel): void
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? null;

        if (!$message) {
            return;
        }

        $from = $message['from'] ?? [];
        $customerName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($from['username'] ?? null);

        $attachments = [];
        $body = $message['text'] ?? $message['caption'] ?? null;

        if (!empty($message['photo'])) {
            // Telegram sends multiple resolutions - the last entry is the
            // largest.
            $fileId = end($message['photo'])['file_id'];
            $attachments[] = $this->resolveFile($channel, $fileId, 'image');
        } elseif (!empty($message['video']['file_id'])) {
            $attachments[] = $this->resolveFile($channel, $message['video']['file_id'], 'video');
        } elseif (!empty($message['voice']['file_id'])) {
            $attachments[] = $this->resolveFile($channel, $message['voice']['file_id'], 'audio');
        } elseif (!empty($message['document']['file_id'])) {
            $attachments[] = $this->resolveFile($channel, $message['document']['file_id'], 'file', $message['document']['file_name'] ?? null);
        }

        $attachments = array_filter($attachments);

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: (string) $message['chat']['id'],
            customerName: $customerName,
            externalMessageId: (string) ($message['message_id'] ?? ''),
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $body,
            attachments: $attachments,
        );
    }

    /**
     * Telegram files also arrive as an opaque file_id rather than a
     * directly usable URL - getFile resolves it to a temporary file_path,
     * which combines with the bot token into a downloadable URL. Passed
     * straight through as the stored attachment URL (unlike WhatsApp's
     * re-hosting, Telegram's file URLs don't expire quickly and are
     * already access-controlled by requiring the bot token in the path).
     */
    private function resolveFile(MessageChannel $channel, string $fileId, string $type, ?string $fileName = null): ?array
    {
        $response = $this->apiService->get($this->apiUrl($channel->access_token, 'getFile'), [], ['file_id' => $fileId]);

        if (!$response['success'] || empty($response['data']['result']['file_path'])) {
            return null;
        }

        $filePath = $response['data']['result']['file_path'];
        $base = rtrim(adminSetting('messaging.telegram.api_base'), '/');

        return [
            'type'      => $type,
            'url'       => "{$base}/file/bot{$channel->access_token}/{$filePath}",
            'file_name' => $fileName ?? basename($filePath),
        ];
    }
}
