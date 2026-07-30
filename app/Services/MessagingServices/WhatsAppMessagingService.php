<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\Concerns\MetaMessagingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * WhatsApp Business Platform (Cloud API) - a Meta Graph API product like
 * Messenger/Instagram, but with a different webhook payload shape
 * (entry[].changes[].value.messages[]/.contacts[]/.metadata rather than
 * entry[].messaging[]) and media that arrives as an opaque, short-lived
 * media ID rather than a directly usable URL - see resolveMedia().
 */
class WhatsAppMessagingService
{
    use MetaMessagingTrait;

    public function __construct(protected ApiService $apiService)
    {
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $conversation->customer_external_id,
        ];

        if (!empty($data['media_url'])) {
            $type = $data['media_type'] ?? 'image';
            $payload['type'] = $type;
            $payload[$type] = ['link' => $data['media_url']];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['body' => $data['body']];
        }

        $result = $this->graphApiCall('POST', $channel->external_id . '/messages', $payload, $channel->access_token);

        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'external_message_id' => $result['data']['messages'][0]['id'] ?? null];
    }

    public function verifyWebhook(Request $request): ?string
    {
        return $this->verifyMetaWebhook($request, adminSetting('messaging.meta.webhook_verify_token'));
    }

    public function verifySignature(Request $request): bool
    {
        return $this->verifyMetaSignature($request, adminSetting('messaging.meta.app_secret'));
    }

    public function handleWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                $channel = $phoneNumberId ? MessageChannel::where('platform', 'whatsapp')->where('external_id', $phoneNumberId)->first() : null;

                if (!$channel || empty($value['messages'])) {
                    continue;
                }

                $contactsByWaId = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] as $message) {
                    $waId = $message['from'];
                    $contactName = $contactsByWaId->get($waId)['profile']['name'] ?? null;

                    $attachments = [];
                    $type = $message['type'] ?? 'text';
                    $body = $message['text']['body'] ?? null;

                    if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) && !empty($message[$type]['id'])) {
                        $media = $this->resolveMedia($message[$type]['id'], $channel->access_token, $channel->platform);

                        if ($media) {
                            $attachments[] = $media;
                            $body = $message[$type]['caption'] ?? null;
                        }
                    }

                    ProcessInboundMessage::dispatch(
                        messageChannelId: $channel->id,
                        customerExternalId: $waId,
                        customerName: $contactName,
                        externalMessageId: $message['id'] ?? null,
                        type: !empty($attachments) ? $attachments[0]['type'] : 'text',
                        body: $body,
                        attachments: $attachments,
                    );
                }
            }
        }
    }

    /**
     * WhatsApp media never arrives as a directly usable URL - the webhook
     * only gives an opaque media ID that must be exchanged for a
     * short-lived, access-token-gated download URL (GET /{media-id}),
     * which is then downloaded and re-hosted to S3 so the inbox UI can
     * display it without needing a fresh, authenticated fetch every time.
     */
    private function resolveMedia(string $mediaId, string $accessToken, string $platform): ?array
    {
        $lookup = $this->graphApiCall('GET', $mediaId, [], $accessToken);

        if (!$lookup['success'] || empty($lookup['data']['url'])) {
            return null;
        }

        $binary = Http::withToken($accessToken)->get($lookup['data']['url'])->body();
        $mimeType = $lookup['data']['mime_type'] ?? 'application/octet-stream';
        $extension = explode('/', explode(';', $mimeType)[0])[1] ?? 'bin';
        $fileName = $mediaId . '.' . $extension;
        $s3Path = "uploads/{$platform}/media/{$fileName}";

        Storage::disk('s3')->put($s3Path, $binary, ['visibility' => 'public']);

        return [
            'type'      => str_starts_with($mimeType, 'image/') ? 'image' : (str_starts_with($mimeType, 'video/') ? 'video' : (str_starts_with($mimeType, 'audio/') ? 'audio' : 'file')),
            'url'       => Storage::disk('s3')->url($s3Path),
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'file_size' => $lookup['data']['file_size'] ?? null,
        ];
    }
}
