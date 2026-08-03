<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\Conversation;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * LINE Messaging API (api.line.me) - dominant as a primary messaging app
 * across Japan, Thailand, Taiwan and Indonesia (hundreds of millions of
 * users). Picked as the platform to add here for the same reason Threads
 * was picked for the Posts module: real, well-documented, and - unlike
 * WhatsApp Embedded Signup or Threads' Tech Provider Verification -
 * genuinely self-service. A "Messaging API channel" in the LINE
 * Developers Console hands you a Channel Access Token and Channel Secret
 * immediately, no app review wait, closer to how simple Telegram's bot
 * token setup is than to Meta's OAuth products.
 *
 * Endpoints/shapes verified against developers.line.biz this session:
 * webhook payload shape, x-line-signature verification (HMAC-SHA256 over
 * the raw body using the channel secret), and the Push Message API (used
 * here instead of the Reply Message API/replyToken, which only works
 * within a short window right after an inbound message and doesn't fit
 * an async admin-inbox reply flow the way Push does).
 */
class LineMessagingService
{
    private string $baseUrl;
    private string $dataBaseUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('messaging.line.base_url') ?: 'https://api.line.me/v2/bot/';
        $this->dataBaseUrl = adminSetting('messaging.line.data_base_url') ?: 'https://api-data.line.me/v2/bot/';
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        $message = !empty($data['media_url'])
            ? ['type' => $data['media_type'] === 'video' ? 'video' : 'image', 'originalContentUrl' => $data['media_url'], 'previewImageUrl' => $data['media_url']]
            : ['type' => 'text', 'text' => $data['body']];

        $response = $this->apiService->post($this->baseUrl . 'message/push', [
            'Authorization' => 'Bearer ' . $channel->access_token,
        ], [
            'to'       => $conversation->customer_external_id,
            'messages' => [$message],
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'LINE API request failed.'];
        }

        // The Push Message API doesn't return a message ID in its
        // response (LINE's webhook delivery-completion event is the only
        // place a sent message's ID later surfaces), so there's nothing
        // to store as external_message_id here.
        return ['success' => true, 'external_message_id' => null];
    }

    /**
     * The channel secret is per-channel (per bot), unlike Meta's shared
     * app-level webhook secret - stored in message_channels.verify_token,
     * the same column Telegram's per-bot secret already uses.
     */
    public function verifySignature(Request $request, MessageChannel $channel): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $channel->verify_token, true));

        return hash_equals($expected, (string) $request->header('x-line-signature'));
    }

    /**
     * Unlike Telegram's setWebhook API call, LINE's webhook URL has no
     * registration endpoint - it's set once, manually, in the LINE
     * Developers Console (Messaging API channel > Webhook settings),
     * same as Meta's app-level webhook config.
     */
    public function handleWebhook(array $payload, MessageChannel $channel): void
    {
        foreach ($payload['events'] ?? [] as $event) {
            if (($event['type'] ?? null) !== 'message' || ($event['source']['type'] ?? null) !== 'user') {
                continue;
            }

            $userId = $event['source']['userId'];
            $message = $event['message'] ?? [];
            $type = $message['type'] ?? 'text';

            $attachments = [];
            $body = $message['text'] ?? null;

            if (in_array($type, ['image', 'video', 'audio', 'file'], true)) {
                $attachment = $this->fetchMessageContent($channel, $message['id'], $type);

                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }

            $profile = $this->fetchProfile($channel, $userId);

            ProcessInboundMessage::dispatch(
                messageChannelId: $channel->id,
                customerExternalId: $userId,
                customerName: $profile['displayName'] ?? null,
                customerAvatarUrl: $profile['pictureUrl'] ?? null,
                externalMessageId: $message['id'] ?? null,
                type: !empty($attachments) ? $attachments[0]['type'] : 'text',
                body: $body,
                attachments: $attachments,
            );
        }
    }

    private function fetchProfile(MessageChannel $channel, string $userId): array
    {
        $response = $this->apiService->get($this->baseUrl . "profile/{$userId}", [
            'Authorization' => 'Bearer ' . $channel->access_token,
        ]);

        return $response['success'] ? $response['data'] : [];
    }

    /**
     * LINE serves message binaries (image/video/audio/file content) from
     * a *separate* host (api-data.line.me, not api.line.me) keyed by the
     * message ID - downloaded and re-hosted to S3 here, same reasoning as
     * WhatsApp's media handling: the LINE-hosted URL requires the bot's
     * own Bearer token to fetch, so it isn't directly usable as a stored
     * attachment URL for display in the inbox later.
     */
    private function fetchMessageContent(MessageChannel $channel, string $messageId, string $type): ?array
    {
        // Raw Http facade throws a ConnectionException on a DNS/network
        // failure rather than returning an unsuccessful Response - a
        // dead/unreachable content host must not crash the whole webhook
        // request.
        try {
            $response = Http::withToken($channel->access_token)->get($this->dataBaseUrl . "message/{$messageId}/content");
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $extension = explode('/', explode(';', $mimeType)[0])[1] ?? 'bin';
        $fileName = $messageId . '.' . $extension;
        $s3Path = "uploads/line/media/{$fileName}";

        Storage::disk('r2')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return [
            'type'      => $type,
            'url'       => Storage::disk('r2')->url($s3Path),
            'mime_type' => $mimeType,
            'file_name' => $fileName,
        ];
    }
}
