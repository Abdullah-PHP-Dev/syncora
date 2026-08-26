<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\Concerns\MetaMessagingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        ], $channel->socialAccount->access_token);

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
        return $this->verifyMetaSignature($request, adminSetting('posts.facebook.client_secret'));
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

                $profile = $this->fetchUserProfile($event['sender']['id'], $channel->socialAccount->access_token);

                ProcessInboundMessage::dispatch(
                    socialAccountId: $channel->social_account_id,
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
     * Messenger's User Profile API - the webhook payload only ever
     * includes the sender's PSID, never a display name, so this is the
     * only way to resolve one. Best-effort: a failure here (eg. the
     * Advanced Access review Meta gates this field behind not being
     * approved yet) just means the conversation falls back to "Unknown"
     * rather than losing the message.
     */
    protected function fetchUserProfile(string $psid, string $accessToken): array
    {

        $result = $this->graphApiCall('GET', $psid, ['fields' => 'first_name,last_name,profile_pic'], $accessToken);
                

        if (!$result['success']) {
            return [];
        }

        $name = trim(($result['data']['first_name'] ?? '') . ' ' . ($result['data']['last_name'] ?? ''));

        return [
            'name'        => $name !== '' ? $name : null,
            'profile_pic' => $result['data']['profile_pic'] ?? null,
        ];
    }

    /**
     * Best-effort Page profile fetch, called once right after a channel is
     * connected. Merged into `meta` rather than replacing it outright,
     * since other platforms/flows may already have written other keys
     * there.
     */
    public function syncChannelDetails(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('GET', $channel->external_id, ['fields' => 'about,category,phone,website,fan_count'], $channel->socialAccount->access_token);

        if (!$result['success']) {
            Log::warning('Facebook channel details sync failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $channel->update(['meta' => array_merge($channel->meta ?? [], ['profile' => $result['data']])]);

        if (isset($result['data']['fan_count'])) {
            $channel->socialAccount->update(['likes_count' => $result['data']['fan_count']]);
        }
    }

    /**
     * Registers this Page with the app's webhook subscription so Meta
     * actually starts delivering message events for it - the
     * App-Dashboard-level webhook config alone is not sufficient, Meta
     * requires this per-Page opt-in too (POST .../subscribed_apps).
     */
    public function subscribeToWebhooks(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('POST', $channel->external_id . '/subscribed_apps', ['subscribed_fields' => 'messages,messaging_postbacks,message_deliveries'], $channel->socialAccount->access_token);

        if ($result['success'] && ($result['data']['success'] ?? false)) {
            $channel->update(['webhook_subscribed' => true]);
        } else {
            Log::warning('Facebook channel webhook subscribe failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
        }
    }

    /**
     * On connect, backfills the Page's most recent conversations (default
     * 5) along with each one's most recent messages (default 5), so a
     * newly connected channel isn't empty until the customer sends
     * something new through this app. One call using Graph API's nested
     * field expansion (messages.limit(...)) avoids an N+1 request per
     * conversation. Each conversation is independently failure-tolerant -
     * one bad conversation must not abort the rest of the batch. Does not
     * backfill attachments - the Conversations API's nested message
     * fields don't carry attachment data without further per-message
     * expansion, and only text history was asked for.
     */
    public function backfillRecentConversations(MessageChannel $channel, int $conversationLimit = 5, int $messageLimit = 5): void
    {
        $result = $this->graphApiCall('GET', $channel->external_id . '/conversations', [
            'fields' => "participants,updated_time,messages.limit({$messageLimit}){id,message,from,created_time}",
            'limit'  => $conversationLimit,
        ], $channel->socialAccount->access_token);

        if (!$result['success']) {
            Log::warning('Facebook conversation backfill failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $conversations = $result['data']['data'] ?? [];

        // A successful call with zero conversations is silent otherwise -
        // keep it visible in the logs rather than indistinguishable from
        // "nothing went wrong."
        if (empty($conversations)) {
            Log::info('Facebook conversation backfill returned zero conversations.', ['channel_id' => $channel->id, 'raw_response' => $result['data']]);
        }

        foreach ($conversations as $conv) {
            try {
                $participants = collect($conv['participants']['data'] ?? []);
                $customer = $participants->first(fn($p) => ($p['id'] ?? null) !== $channel->external_id);

                if (!$customer) {
                    continue;
                }

                $profile = $this->fetchUserProfile($customer['id'], $channel->socialAccount->access_token);

                $conversation = Conversation::updateOrCreate(
                    ['social_account_id' => $channel->social_account_id, 'customer_external_id' => $customer['id']],
                    [
                        'platform'            => 'facebook',
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
                Log::warning('Failed to backfill a Facebook conversation.', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
