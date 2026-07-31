<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\Conversation;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Zalo Official Account API (openapi.zalo.me) - Vietnam's dominant
 * messaging app (~75M+ users, near-universal penetration domestically).
 * Picked over the other regionally-dominant options researched alongside
 * it (WeChat requires a China-registered legal business entity for API
 * access; Viber bots are commercial-terms-only as of a 2024 policy
 * change) because the OA API is genuinely self-service via
 * developers.zalo.me, with confirmed direct bidirectional chat (webhook
 * events like `user_send_text` plus a real send-message endpoint) rather
 * than KakaoTalk's true bidirectional "ConsultationTalk" messaging, which
 * typically requires going through a third-party BSP rather than Kakao's
 * own console.
 *
 * Two architectural differences from every other platform in this module:
 *
 * 1. Auth is OAuth 2.0 (oauth.zaloapp.com/v4/oa/), but unlike Meta/X/
 *    Threads the resulting token/refresh-token pair alone isn't enough to
 *    verify inbound webhooks - that needs a separate "OA Secret Key"
 *    (distinct from the Zalo App's own secret) that's only available by
 *    copying it out of the Zalo Developers Console when linking the OA to
 *    the app, not returned by the OAuth flow itself. See
 *    MessageChannelController::redirectZalo() for how this app collects
 *    it up front, before starting the OAuth redirect.
 *
 * 2. Webhook signature verification uses a Zalo-specific scheme, not the
 *    HMAC pattern every other platform here uses: the X-ZEvent-Signature
 *    header is `mac=<hex>` where the hex is a *plain* SHA256 digest (not
 *    HMAC-SHA256) of `app_id + raw_body + timestamp + OA_secret_key`
 *    concatenated as a string, with `timestamp` itself taken from the
 *    webhook payload.
 *
 * Endpoints/shapes verified this session via developers.zalo.me,
 * community threads, and Zalo OA API wrapper source on GitHub.
 */
class ZaloMessagingService
{
    private string $baseUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('messaging.zalo.base_url') ?: 'https://openapi.zalo.me/v2.0/oa/';
    }

    public function redirect($state)
    {
        $url = adminSetting('messaging.zalo.auth_url') . '?' . http_build_query([
            'app_id'       => adminSetting('messaging.zalo.app_id'),
            'redirect_uri' => $this->callbackUrl(),
            'state'        => $state,
        ]);

        return Redirect::away($url);
    }

    private function callbackUrl(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/zalo/callback';
    }

    /**
     * Exchanges the code for an access/refresh token pair, then resolves
     * the linked Official Account's own ID/name/avatar via getoa - the
     * OAuth response itself only carries the token, not which OA it's
     * scoped to.
     */
    public function handleCallback(string $code): array
    {
        $tokenResponse = $this->apiService->post(adminSetting('messaging.zalo.token_url'), [
            'secret_key' => adminSetting('messaging.zalo.app_secret'),
        ], [
            'code'       => $code,
            'app_id'     => adminSetting('messaging.zalo.app_id'),
            'grant_type' => 'authorization_code',
        ], 'form');

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a Zalo access token.'];
        }

        $token = $tokenResponse['data'];

        $oaResponse = $this->apiService->get($this->baseUrl . 'getoa', ['access_token' => $token['access_token']]);

        if (!$oaResponse['success'] || empty($oaResponse['data']['data'])) {
            return ['success' => false, 'error' => 'Connected, but could not fetch the Official Account profile.'];
        }

        return ['success' => true, 'token' => $token, 'oa' => $oaResponse['data']['data']];
    }

    public function refreshToken(MessageChannel $channel): bool
    {
        if (empty($channel->refresh_token)) {
            return false;
        }

        $response = $this->apiService->post(adminSetting('messaging.zalo.token_url'), [
            'secret_key' => adminSetting('messaging.zalo.app_secret'),
        ], [
            'refresh_token' => $channel->refresh_token,
            'app_id'        => adminSetting('messaging.zalo.app_id'),
            'grant_type'    => 'refresh_token',
        ], 'form');

        if (!$response['success']) {
            return false;
        }

        $token = $response['data'];

        $channel->update([
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $channel->refresh_token,
            'expires_at'    => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ]);

        return true;
    }

    private function ensureFreshToken(MessageChannel $channel): string
    {
        if ($channel->expires_at && now()->lt($channel->expires_at)) {
            return $channel->access_token;
        }

        $this->refreshToken($channel);

        return $channel->fresh()->access_token;
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $accessToken = $this->ensureFreshToken($channel);

        $message = !empty($data['media_url'])
            ? ['attachment' => ['type' => 'template', 'payload' => ['template_type' => 'media', 'elements' => [['media_type' => $data['media_type'] === 'video' ? 'video' : 'image', 'url' => $data['media_url']]]]]]
            : ['text' => $data['body']];

        $response = $this->apiService->post($this->baseUrl . 'message', ['access_token' => $accessToken], [
            'recipient' => ['user_id' => $conversation->customer_external_id],
            'message'   => $message,
        ]);

        if (!$response['success'] || !empty($response['data']['error'])) {
            return ['success' => false, 'error' => $response['data']['message'] ?? 'Zalo API request failed.'];
        }

        return ['success' => true, 'external_message_id' => $response['data']['data']['message_id'] ?? null];
    }

    /**
     * SHA256 (not HMAC) of app_id + raw body + the payload's own
     * timestamp field + the per-OA secret key - see class docblock.
     */
    public function verifySignature(Request $request, MessageChannel $channel, array $payload): bool
    {
        $header = (string) $request->header('X-ZEvent-Signature');

        if (!str_starts_with($header, 'mac=')) {
            return false;
        }

        $timestamp = (string) ($payload['timestamp'] ?? '');
        $baseString = adminSetting('messaging.zalo.app_id') . $request->getContent() . $timestamp . $channel->verify_token;
        $expected = hash('sha256', $baseString);

        return hash_equals($expected, substr($header, 4));
    }

    /**
     * One event per webhook call (unlike LINE/Meta's events[] arrays) -
     * app_id/sender/recipient/event_name/message/timestamp are all
     * top-level fields, with `recipient.id` being the OA's own ID, used
     * to resolve which local channel this event belongs to (see
     * ZaloWebhookController - Zalo's webhook is configured once per App,
     * potentially covering several linked OAs, the same "shared endpoint,
     * disambiguate from the payload" shape as Meta's platforms).
     */
    public function handleWebhook(array $payload, MessageChannel $channel): void
    {
        $eventName = $payload['event_name'] ?? null;

        $mediaEvents = ['user_send_image', 'user_send_video', 'user_send_audio', 'user_send_file', 'user_send_sticker', 'user_send_gif'];

        if ($eventName !== 'user_send_text' && !in_array($eventName, $mediaEvents, true)) {
            return;
        }

        $senderId = $payload['sender']['id'] ?? null;

        if (!$senderId) {
            return;
        }

        $message = $payload['message'] ?? [];
        $attachments = [];
        $body = $message['text'] ?? null;

        if (in_array($eventName, $mediaEvents, true)) {
            $url = $message['attachments'][0]['payload']['url'] ?? $message['url'] ?? null;

            if ($url) {
                $attachments[] = $this->rehostMedia($url, $eventName);
            }
        }

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: $senderId,
            externalMessageId: $message['msg_id'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $body,
            attachments: array_filter($attachments),
        );
    }

    private function rehostMedia(string $url, string $eventName): ?array
    {
        $response = Http::get($url);

        if (!$response->successful()) {
            return null;
        }

        $type = match ($eventName) {
            'user_send_image', 'user_send_gif' => 'image',
            'user_send_video' => 'video',
            'user_send_audio' => 'audio',
            default => 'file',
        };

        $fileName = uniqid() . '_' . basename(parse_url($url, PHP_URL_PATH) ?: 'file');
        $s3Path = "uploads/zalo/media/{$fileName}";

        Storage::disk('s3')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return ['type' => $type, 'url' => Storage::disk('s3')->url($s3Path), 'file_name' => $fileName];
    }
}
