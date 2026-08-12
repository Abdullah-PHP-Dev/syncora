<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\Concerns\InstagramMessagingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $version = adminSetting('messaging.instagram.graph_version') ?: (adminSetting('messaging.meta.graph_version') ?: 'v21.0');

$response = Http::withHeaders([
    'Authorization' => 'Bearer IGAAqPVIo94cFBZAFpMVXo3eEtOSkVkczFkeC1ERU4wMjJxTXgzejJsQng2THJ5VTN5UUw1Vi14SjFCcnFzandvUVBWeE9xemwzQWlrY3FnNFVPYVMzX1JidURfVThuTWt3SjRPNGZAYSE1rT2xlNE9YUHVR',
])->get("https://graph.facebook.com/v26.0/1098590715835617");

        // if (!$result['success']) {
        //     return [];
        // }
$conversation = Conversation::firstOrCreate(
                [
                    'message_channel_id'   => 9,
                    'customer_external_id' => "https://graph.facebook.com/{$version}/{$igsid}",
                ],
                [
                    'platform'                 => 'instagram',
                    'external_conversation_id' => "https://graph.facebook.com/{$version}/{$igsid}",
                    'customer_name'            => 'tst',
                    'customer_avatar_url'      => (string) $response->successful(),
                    'meta'                     => $response->json(),
                    'status'                   => 'open',
                    'assigned_user_id'         => 1,
                ]
            );
        return [
            'name'        => $result['data']['name'] ?? "https://graph.facebook.com/{$version}/{$igsid}",
            'profile_pic' => $result['data']['profile_pic'] ?? (string) $result['status'],
        ];
    }
}
