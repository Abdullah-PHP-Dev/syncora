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
/**
 * Instagram Direct - shares Meta's Messenger-platform webhook/Send API
 * shape (entry[].messaging[], same sender/recipient/message structure) but
 * under `object: "instagram"` instead of `"page"`, and sent against the
 * connected Instagram professional account's own ID rather than a
 * Facebook Page ID.
 */
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
        return $this->verifyInstagramWebhook($request, adminSetting('posts.facebook.webhook_verify_token', ''));
    }

    public function verifySignature(Request $request): bool
    {
        return $this->verifyInstagramSignature($request, adminSetting('posts.instagram.client_secret', ''));
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
     * Instagram's User Profile API - the webhook payload only ever
     * includes the sender's IGSID, never a display name, so this is the
     * only way to resolve one. Best-effort: a failure here just means the
     * conversation falls back to "Unknown" rather than losing the message.
     *
     * graph.facebook.com, not graph.instagram.com - confirmed via a direct
     * curl comparison using the same token: graph.instagram.com silently
     * returns an empty {} for this specific lookup (no error, just
     * nothing), while graph.facebook.com returns the real profile - even
     * for an Instagram Login token that otherwise works fine against
     * graph.instagram.com for sending messages and the /me self-lookup in
     * handleInstagramCallback(). Meta just doesn't serve this particular
     * endpoint on the .instagram.com domain.
     */
    protected function fetchUserProfile(string $igsid, string $accessToken): array
    {
        $version = adminSetting('messaging.instagram.graph_version') ?: (adminSetting('messaging.meta.graph_version') ?: 'v26.0');

        $result = $this->apiService->get(
            "https://graph.facebook.com/{$version}/{$igsid}",
            ['Authorization' => "Bearer {$accessToken}"],
            ['fields' => 'name,profile_pic']
        );

        if (!$result['success']) {
            return [];
        }

        return [
            'name'        => $result['data']['name'] ?? null,
            'profile_pic' => $result['data']['profile_pic'] ?? null,
        ];
    }

    /**
     * Best-effort account profile fetch, called once right after a
     * channel is connected. Merged into `meta` rather than replacing it
     * outright - Instagram channels already use `meta` for the
     * instagram_login auth-type tag, this must not clobber that.
     */
    public function syncChannelDetails(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('GET', $channel->external_id, ['fields' => 'biography,website'], $channel->access_token);

        if (!$result['success']) {
            Log::warning('Instagram channel details sync failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
            return;
        }

        $channel->update(['meta' => array_merge($channel->meta ?? [], ['profile' => $result['data']])]);
    }

    /**
     * Registers this Instagram account with the app's webhook
     * subscription so Meta actually starts delivering message events for
     * it - the App-Dashboard-level webhook config alone is not
     * sufficient, Meta requires this per-account opt-in too (POST
     * .../subscribed_apps).
     */
    public function subscribeToWebhooks(MessageChannel $channel): void
    {
        $result = $this->graphApiCall('POST', $channel->external_id . '/subscribed_apps', ['subscribed_fields' => 'messages'], $channel->access_token);

        if ($result['success'] && ($result['data']['success'] ?? false)) {
            $channel->update(['webhook_subscribed' => true]);
        } else {
            Log::warning('Instagram channel webhook subscribe failed.', ['channel_id' => $channel->id, 'error' => $result['error'] ?? null]);
        }
    }

    /**
     * On connect, backfills the account's most recent conversations
     * (default 5) along with each one's most recent messages (default
     * 5), so a newly connected channel isn't empty until the customer
     * sends something new through this app. One call using Graph API's
     * nested field expansion (messages.limit(...)) avoids an N+1 request
     * per conversation. Each conversation is independently
     * failure-tolerant - one bad conversation must not abort the rest of
     * the batch.
     *
     * Uses graphApiUrl() (graph.instagram.com) for consistency with the
     * rest of this class - unverified against this specific endpoint as
     * of writing (Meta's Conversations API has never been called from
     * this codebase before). If this silently returns zero conversations
     * despite the account having real DM history, that's the same
     * domain quirk fetchUserProfile() already had to work around above -
     * apply the same fix (route this one call through graph.facebook.com
     * instead).
     */
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

        foreach ($result['data']['data'] ?? [] as $conv) {
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
