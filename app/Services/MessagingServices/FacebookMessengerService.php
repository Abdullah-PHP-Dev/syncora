<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\Concerns\MetaMessagingTrait;
use Illuminate\Http\Request;
/**
 * Facebook Messenger - Meta Messenger Platform Send API + webhooks.
 * Endpoint/payload shapes verified against developers.facebook.com/docs/
 * messenger-platform this session.
 */
class FacebookMessengerService
{
    use MetaMessagingTrait;

    public function __construct(protected ApiService $apiService)
    {
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        $message = !empty($data['media_url'])
            ? ['attachment' => ['type' => $data['media_type'] ?? 'image', 'payload' => ['url' => $data['media_url'], 'is_reusable' => true]]]
            : ['text' => $data['body']];

        $result = $this->graphApiCall('POST', $channel->external_id . '/messages', [
            'messaging_type' => 'RESPONSE',
            'recipient'      => ['id' => $conversation->customer_external_id],
            'message'        => $message,
        ], $channel->access_token);

        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'external_message_id' => $result['data']['message_id'] ?? null];
    }

    /**
     * Meta's webhook verification happens once, at the App level (one URL
     * + one verify token covering every connected Page/IG account/WABA
     * under that app) - there's no specific channel/Page context yet at
     * verification time, so this checks against the shared app-level
     * token rather than any one channel's.
     */
    public function verifyWebhook(Request $request): ?string
    {
        return $this->verifyMetaWebhook($request, adminSetting('messaging.meta.webhook_verify_token'));
    }

    public function verifySignature(Request $request): bool
    {
        return $this->verifyMetaSignature($request, adminSetting('messaging.meta.app_secret'));
    }

    /**
     * A single webhook URL/verify token serves every connected Page (Meta
     * subscribes each Page under one App-level webhook), so the entry's
     * own `id` (the Page ID) is what resolves which local MessageChannel
     * an event belongs to - the caller doesn't already know which channel
     * a given payload is for.
     */
    public function handleWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {

            $pageId = $entry['id'] ?? null;
            $channel = $pageId ? MessageChannel::where('platform', 'facebook')->where('external_id', $pageId)->first() : null;

            if (!$channel) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                // Delivery/read receipts and echoes of our own outbound
                // sends also arrive here - only genuine inbound customer
                // messages (with actual content) should create a Message.
                if (empty($event['message']) || !empty($event['message']['is_echo'])) {
                    continue;
                }

                $attachments = collect($event['message']['attachments'] ?? [])->map(fn($a) => [
                    'type' => $a['type'] ?? 'file',
                    'url'  => $a['payload']['url'] ?? null,
                ])->filter(fn($a) => $a['url'])->values()->all();

                ProcessInboundMessage::dispatch(
                    messageChannelId: $channel->id,
                    customerExternalId: $event['sender']['id'],
                    externalMessageId: $event['message']['mid'] ?? null,
                    type: !empty($attachments) ? $attachments[0]['type'] : 'text',
                    body: $event['message']['text'] ?? null,
                    attachments: $attachments,
                );
            }
        }
    }
}
