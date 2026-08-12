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
     */
    protected function fetchUserProfile(string $igsid, string $accessToken): array
    {
        $result = $this->graphApiCall('GET', $igsid, ['fields' => 'name,profile_pic'], $accessToken);
  
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $accessToken,
    'Accept' => 'application/json',
])->get(
    "https://graph.facebook.com/v26.0/{$igsid}",
    [
        'fields' => 'name,profile_pic,username',
    ]
);

// 2. Safely parse response data with fallbacks
$profileData = $response->successful() ? $response->json() : [];

$customerName = $profileData['name'] 
    ?? $profileData['username'] 
    ?? "Instagram User ({$igsid})";

$customerAvatar = $profileData['profile_pic'] ?? null;

// 3. Store or retrieve conversation
$conversation = Conversation::firstOrCreate(
    [
        'message_channel_id'   => 9,
        'customer_external_id' => $igsid, // Use actual IGSID instead of hardcoded '08080'
    ],
    [
        'platform'                 => 'instagram',
        'external_conversation_id' => $response->successful(),
        'customer_name'            => $customerName,
        'customer_avatar_url'      => $customerAvatar,
        'meta'                     => $profileData, // Eloquent automatically encodes array to JSON if cast, or use json_encode($profileData) if uncast
        'status'                   => 'open',
        'assigned_user_id'         => 1,
    ]
);
        if (!$response->successful()) {
            return [];
        }

        $profile = $response->json();
         // $fields = 'name,profile_pic';

        // $response = Http::get("https://graph.instagram.com/v26.0/{$igsid}", [
        //     'fields' => $fields,
        //     'access_token' => $accessToken,
        // ]);

        // // if ($response->successful()) {
        // //     return $response->json();
        // // }

        return [
            'name'        => $profile['name'] ?? null,
            'profile_pic' => $profile['profile_pic'] ?? null,
        ];
    }
}
