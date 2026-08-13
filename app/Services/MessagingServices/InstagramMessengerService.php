<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\Concerns\InstagramMessagingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstagramMessengerService
{
    use InstagramMessagingTrait;

    public function __construct(protected ApiService $apiService)
    {
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        $message = !empty($data['media_url'])
            ? ['attachment' => ['type' => $data['media_type'] ?? 'image', 'payload' => ['url' => $data['media_url']]]]
            : ['text' => $data['body']];

        $result = $this->graphApiCall('POST', $channel->external_id . '/messages', [
            'recipient' => ['id' => $conversation->customer_external_id],
            'message'   => $message,
        ], $channel->access_token);

        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'external_message_id' => $result['data']['message_id'] ?? null];
    }

    public function verifyWebhook(Request $request): ?string
    {
        return $this->verifyInstagramWebhook($request, adminSetting('posts.instagram.webhook_verify_token', ''));
    }

    public function verifySignature(Request $request): bool
    {
        return $this->verifyInstagramSignature($request, adminSetting('posts.facebook.client_secret', ''));
    }

    public function handleWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            $igUserId = $entry['id'] ?? null;
            $channel = $igUserId ? MessageChannel::where('platform', 'instagram')->where('external_id', $igUserId)->first() : null;

            if (!$channel) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                if (empty($event['message']) || !empty($event['message']['is_echo'])) {
                    continue;
                }

                $attachments = collect($event['message']['attachments'] ?? [])->map(fn($a) => [
                    'type' => $a['type'] ?? 'file',
                    'url'  => $a['payload']['url'] ?? null,
                ])->filter(fn($a) => $a['url'])->values()->all();

                $profile = $this->fetchUserProfile($event['sender']['id'], $channel->access_token);

                ProcessInboundMessage::dispatch(
                    messageChannelId: $channel->id,
                    customerExternalId: $event['sender']['id'],
                    customerName: $profile['name'] ?? null,
                    customerAvatarUrl: $profile['profile_pic'] ?? null,
                    externalMessageId: $event['message']['mid'] ?? null,
                    type: !empty($attachments) ? $attachments[0]['type'] : 'text',
                    body: $event['message']['text'] ?? null,
                    attachments: $attachments,
                );
            }
        }
    }

    /**
     * Updated: Hits graph.facebook.com with Page Access Token to retrieve sender profile details.
     */
    protected function fetchUserProfile(string $igsid, string $accessToken): array
    {
        $version = adminSetting('messaging.instagram.graph_version') ?: (adminSetting('messaging.meta.graph_version') ?: 'v21.0');

        $result = $this->apiService->get(
            "https://graph.facebook.com/{$version}/{$igsid}",
            ['Authorization' => 'Bearer ' . $accessToken],
            [
                'fields'       => 'name,username,profile_pic'
            ]
        );

        if (!$result['success'] || empty($result['data'])) {
            Log::warning('Instagram sender profile fetch failed.', [
                'igsid' => $igsid,
                'error' => $result['error'] ?? ($result['data']['error']['message'] ?? 'Unknown error'),
            ]);

            return [
                'name'        => null,
                'profile_pic' => null,
            ];
        }

        $data = $result['data'];

        return [
            'name'        => $data['name'] ?? $data['username'] ?? null,
            'profile_pic' => $data['profile_pic'] ?? null,
        ];
    }

    public function syncChannelDetails(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('GET', $channel->external_id, ['fields' => 'biography,website'], $channel->access_token);

        if (!$result['success']) {
            Log::warning('Instagram channel details sync failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $channel->update(['meta' => array_merge($channel->meta ?? [], ['profile' => $result['data']])]);
    }

    public function subscribeToWebhooks(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('POST', $channel->external_id . '/subscribed_apps', ['subscribed_fields' => 'messages'], $channel->access_token);

        if ($result['success'] && ($result['data']['success'] ?? false)) {
            $channel->update(['webhook_subscribed' => true]);
        } else {
            Log::warning('Instagram channel webhook subscribe failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
        }
    }

    public function backfillRecentConversations(MessageChannel $channel, int $conversationLimit = 5, int $messageLimit = 5): void
    {
        $result = $this->graphApiCall('GET', $channel->external_id . '/conversations', [
            'fields' => "participants,updated_time,messages.limit({$messageLimit}){id,message,from,created_time}",
            'limit'  => $conversationLimit,
        ], $channel->access_token);

        if (!$result['success']) {
            Log::warning('Instagram conversation backfill failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $conversations = $result['data']['data'] ?? [];

        if (empty($conversations)) {
            Log::info('Instagram conversation backfill returned zero conversations.', ['channel_id' => $channel->id, 'raw_response' => $result['data']]);
        }

        foreach ($conversations as $conv) {
            try {
                $participants = collect($conv['participants']['data'] ?? []);
                $customer = $participants->first(fn($p) => ($p['id'] ?? null) !== $channel->external_id);

                if (!$customer) {
                    continue;
                }

                $profile = $this->fetchUserProfile($customer['id'], $channel->access_token);

                $conversation = Conversation::updateOrCreate(
                    ['message_channel_id' => $channel->id, 'customer_external_id' => $customer['id']],
                    [
                        'platform'            => 'instagram',
                        'customer_name'       => $profile['name'] ?? null,
                        'customer_avatar_url' => $profile['profile_pic'] ?? null,
                        'last_message_at'     => $conv['updated_time'] ?? now(),
                        'status'              => 'open',
                    ]
                );

                foreach ($conv['messages']['data'] ?? [] as $msg) {
                    $isOutbound = ($msg['from']['id'] ?? null) === $channel->external_id;

                    Message::updateOrCreate(
                        ['conversation_id' => $conversation->id, 'external_message_id' => $msg['id']],
                        [
                            'direction'    => $isOutbound ? 'outbound' : 'inbound',
                            'sender_type'  => $isOutbound ? 'agent' : 'customer',
                            'type'         => 'text',
                            'body'         => $msg['message'] ?? null,
                            'status'       => 'delivered',
                            'sent_at'      => $msg['created_time'] ?? now(),
                            'delivered_at' => $msg['created_time'] ?? now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to backfill an Instagram conversation.', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            }
        }
    }
}