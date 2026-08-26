<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;

/**
 * Slack Web API (slack.com/api/) - unlike Discord, Slack DOES support
 * webhook-delivered events (the Events API), so this platform needs no
 * daemon process: a Slack app with a Request URL configured in "Event
 * Subscriptions" gets an HTTP POST for every subscribed event, including
 * `message.im` for direct messages sent to the bot.
 *
 * Two things make Slack's auth model different from every other platform
 * in this module:
 *
 * 1. The signing secret used to verify webhook deliveries (X-Slack-
 *    Signature) is a property of the Slack *app* itself, not of any one
 *    installed workspace - every team that installs this app shares the
 *    same signing secret. That's the opposite of Zalo's per-OA secret key,
 *    and it means signature verification here doesn't need a channel
 *    looked up first (see verifySignature()) - unlike ZaloMessagingService,
 *    which needs the channel row to get its verify_token before it can
 *    check anything.
 *
 * 2. OAuth v2 install (oauth.v2.access) hands back a bot token (xoxb-...)
 *    that, unlike Meta/X/Zalo/Threads, does not expire under Slack's
 *    default (non-rotating) token model - so there's no refresh_token/
 *    expires_at to track here, closer to Telegram/Discord's static-token
 *    shape than to this module's other OAuth platforms.
 *
 * Endpoints/shapes verified this session against api.slack.com/
 * docs.slack.dev: oauth.v2.access token exchange, team.info, the
 * conversations.open + chat.postMessage send pattern, the message.im
 * event envelope (including url_verification's one-time challenge
 * handshake), and the v0:{timestamp}:{raw_body} HMAC-SHA256 signing
 * scheme.
 */
class SlackMessagingService
{
    private string $baseUrl;

    public function __construct(protected ApiService $apiService)
    {
        $this->baseUrl = adminSetting('messaging.slack.base_url') ?: 'https://slack.com/api/';
    }

    public function redirect($state)
    {
        $url = (adminSetting('messaging.slack.authorize_url') ?: 'https://slack.com/oauth/v2/authorize') . '?' . http_build_query([
            'client_id'    => adminSetting('chats.slack.client_id'),
            // Bot scopes only - this app never acts as an installing human
            // user, just as the bot itself, so there's no separate
            // user_scope needed. team:read is only for the team.info call
            // in handleCallback() (workspace name/icon), not for messaging.
            'scope'        => 'chat:write,im:history,im:read,im:write,users:read,files:read,team:read',
            'redirect_uri' => $this->callbackUrl(),
            'state'        => $state,
        ]);

        return Redirect::away($url);
    }

    private function callbackUrl(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/slack/callback';
    }

    /**
     * Exchanges the code for a bot token, then resolves the installing
     * workspace's own name/icon via team.info - like Zalo's getoa call,
     * the OAuth response carries the token but only a bare team id/name,
     * not the icon shown in the UI.
     */
    public function handleCallback(string $code): array
    {
        $tokenResponse = $this->apiService->post(adminSetting('messaging.slack.token_url') ?: 'https://slack.com/api/oauth.v2.access', [], [
            'client_id'     => adminSetting('chats.slack.client_id'),
            'client_secret' => adminSetting('chats.slack.client_secret'),
            'code'          => $code,
            'redirect_uri'  => $this->callbackUrl(),
        ], 'form');

        // Slack's Web API always answers HTTP 200, even on failure - the
        // real success/failure signal is the `ok` field in the body, not
        // the status code, so ApiService's successful()-based `success`
        // flag can't be trusted alone here (same reason storeTelegram()
        // checks `$check['data']['ok']` rather than just `$check['success']`).
        if (!$tokenResponse['success'] || empty($tokenResponse['data']['ok'])) {
            return ['success' => false, 'error' => $tokenResponse['data']['error'] ?? 'Failed to exchange code for a Slack access token.'];
        }

        $token = $tokenResponse['data'];
        $team = $token['team'] ?? [];

        $icon = null;
        $infoResponse = $this->apiService->get($this->baseUrl . 'team.info', ['Authorization' => 'Bearer ' . $token['access_token']]);

        if ($infoResponse['success'] && !empty($infoResponse['data']['ok'])) {
            $icon = $infoResponse['data']['team']['icon']['image_132'] ?? null;
        }

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'slack', 'platform_account_id' => $team['id'], 'user_id' => Auth::id()],
            [
                'name'                     => $team['name'] ?? 'Slack Workspace',
                'username'                 => $team['name'] ?? null,
                'avatar_url'               => $icon,
                'access_token'             => $token['access_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'slack', 'external_id' => $team['id']],
            [
                'social_account_id' => $account->id,
                'meta'              => ['bot_user_id' => $token['bot_user_id'] ?? null],
            ]
        );

        return ['success' => true, 'data' => $channel];
    }

    /**
     * Bots can't message a Slack user until a DM channel is opened -
     * idempotent and cheap, always returning the existing channel if one's
     * already open, so (like DiscordMessagingService::resolveDmChannel())
     * it's simplest to just resolve it fresh on every send rather than
     * caching the channel id anywhere.
     */
    private function resolveDmChannel(MessageChannel $channel, string $userId): ?string
    {
        $response = $this->apiService->post($this->baseUrl . 'conversations.open', [
            'Authorization' => 'Bearer ' . $channel->socialAccount->access_token,
        ], ['users' => $userId], 'form');

        return ($response['success'] && !empty($response['data']['ok']))
            ? ($response['data']['channel']['id'] ?? null)
            : null;
    }

    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;
        $dmChannelId = $this->resolveDmChannel($channel, $conversation->customer_external_id);

        if (!$dmChannelId) {
            return ['success' => false, 'error' => 'Could not open a Slack DM channel with this user.'];
        }

        $text = $data['body'] ?? '';

        // Slack auto-unfurls a plain URL into a preview, same
        // simplification already used for Discord/X DMs in this module
        // rather than a separate files.upload round trip.
        if (!empty($data['media_url'])) {
            $text = trim($text . ' ' . $data['media_url']);
        }

        $response = $this->apiService->post($this->baseUrl . 'chat.postMessage', [
            'Authorization' => 'Bearer ' . $channel->socialAccount->access_token,
        ], [
            'channel' => $dmChannelId,
            'text'    => $text,
        ], 'form');

        if (!$response['success'] || empty($response['data']['ok'])) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Slack API request failed.'];
        }

        return [
            'success'                  => true,
            'external_message_id'      => $response['data']['ts'] ?? null,
            'external_conversation_id' => $dmChannelId,
        ];
    }

    /**
     * chat.update/chat.delete identify the target message by its channel
     * id + `ts` (the timestamp Slack returned when the message was sent,
     * stored as external_message_id) - external_conversation_id is the
     * DM channel id persisted onto the conversation the first time
     * sendMessage() succeeded.
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $channel = $message->conversation->channel;

        $response = $this->apiService->post($this->baseUrl . 'chat.update', [
            'Authorization' => 'Bearer ' . $channel->socialAccount->access_token,
        ], [
            'channel' => $message->conversation->external_conversation_id,
            'ts'      => $message->external_message_id,
            'text'    => $newBody,
        ], 'form');

        if (!$response['success'] || empty($response['data']['ok'])) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Slack API request failed.'];
        }

        return ['success' => true];
    }

    public function deleteMessage(Message $message): array
    {
        $channel = $message->conversation->channel;

        $response = $this->apiService->post($this->baseUrl . 'chat.delete', [
            'Authorization' => 'Bearer ' . $channel->socialAccount->access_token,
        ], [
            'channel' => $message->conversation->external_conversation_id,
            'ts'      => $message->external_message_id,
        ], 'form');

        if (!$response['success'] || empty($response['data']['ok'])) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Slack API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Every Events API delivery is signed with the same app-level signing
     * secret regardless of which installed workspace it came from, so -
     * unlike ZaloMessagingService::verifySignature() - this doesn't take a
     * MessageChannel at all. Also enforces the 5-minute replay window
     * Slack's own docs recommend, which none of this module's other
     * signature checks needed (their platforms don't document a replay
     * window the same way).
     */
    public function verifySignature(Request $request): bool
    {
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if (!$timestamp || !str_starts_with($signature, 'v0=') || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $baseString = "v0:{$timestamp}:{$request->getContent()}";
        $expected = 'v0=' . hash_hmac('sha256', $baseString, adminSetting('chats.slack.signing_secret'));

        return hash_equals($expected, $signature);
    }

    /**
     * Only `event_callback` deliveries reach here (the webhook
     * controller answers `url_verification`'s one-time challenge itself,
     * before any channel lookup is even possible). Filters out anything
     * that isn't a fresh human DM: edits/deletions and join/leave notices
     * (which arrive as subtyped messages) and the bot's own messages
     * echoing back, identified by either the `bot_id` field appearing at
     * all or the sender matching this channel's own bot_user_id.
     */
    public function handleWebhook(array $payload, MessageChannel $channel): void
    {
        $event = $payload['event'] ?? [];

        if (($event['type'] ?? null) !== 'message' || ($event['channel_type'] ?? null) !== 'im') {
            return;
        }

        // An allowlist, not `!empty($event['subtype'])`. A DM carrying an
        // attachment is delivered as a message with subtype `file_share`
        // and a `files` array - so rejecting every subtyped message
        // discarded *only* the messages with images in them, which is
        // exactly why text DMs arrived and image DMs never did. The
        // file_share subtype stopped going to RTM connections years ago
        // but is still sent over the Events API, which is what this uses.
        $subtype = $event['subtype'] ?? null;

        if ($subtype !== null && $subtype !== 'file_share') {
            return;
        }

        $userId = $event['user'] ?? null;
        $botUserId = $channel->meta['bot_user_id'] ?? null;

        if (!$userId || !empty($event['bot_id']) || $userId === $botUserId) {
            return;
        }

        $attachments = [];

        foreach ($event['files'] ?? [] as $file) {
            $attachment = $this->rehostFile($channel, $file);

            if ($attachment) {
                $attachments[] = $attachment;
            }
        }

        ProcessInboundMessage::dispatch(
            socialAccountId: $channel->social_account_id,
            customerExternalId: $userId,
            externalConversationId: $event['channel'] ?? null,
            externalMessageId: $event['ts'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $event['text'] ?? null,
            attachments: $attachments,
        );
    }

    /**
     * Slack's file URLs (url_private) are auth-gated the same way LINE's
     * api-data.line.me and WhatsApp's media host are - the bot's own
     * Bearer token is required to fetch them, so they're re-hosted to S3
     * here rather than stored as-is.
     */
    private function rehostFile(MessageChannel $channel, array $file): ?array
    {
        $url = $file['url_private'] ?? null;

        if (!$url) {
            return null;
        }

        // Raw Http facade throws a ConnectionException on a DNS/network
        // failure rather than returning an unsuccessful Response - a
        // dead/unreachable file URL must not crash the whole webhook
        // request.
        try {
            $response = Http::withToken($channel->socialAccount->access_token)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $mimeType = $file['mimetype'] ?? 'application/octet-stream';

        // files.slack.com answers a request it won't authorize with an
        // HTML sign-in page under a 200, not a 4xx - so successful() alone
        // would happily store that page to R2 under the image's own name
        // and produce a broken attachment with nothing logged. Comparing
        // what came back against the mimetype Slack already told us to
        // expect turns that into a visible failure instead. The bot token
        // needs the `files:read` scope for this fetch to return real bytes.
        $returnedType = strtok((string) $response->header('Content-Type'), ';');

        // A sign-in page is text/html, and so is a genuinely shared .html
        // file - but an HTML body where Slack promised anything else is
        // always the error page, and family comparison alone misses it
        // (text/html and application/pdf both collapse to 'file').
        $isUnexpectedHtml = $returnedType === 'text/html' && $mimeType !== 'text/html';

        if ($returnedType && ($isUnexpectedHtml || $this->mediaFamily($returnedType) !== $this->mediaFamily($mimeType))) {
            Log::warning('Slack file download did not return the expected media type', [
                'channel_id' => $channel->id,
                'file_id'    => $file['id'] ?? null,
                'expected'   => $mimeType,
                'received'   => $returnedType,
                'hint'       => 'the bot token most likely lacks the files:read scope',
            ]);

            return null;
        }

        $type = $this->mediaFamily($mimeType);

        $fileName = ($file['id'] ?? uniqid()) . '_' . ($file['name'] ?? 'file');
        $s3Path = "uploads/slack/media/{$fileName}";

        Storage::disk('r2')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return [
            'type'      => $type,
            'url'       => Storage::disk('r2')->url($s3Path),
            'mime_type' => $mimeType,
            'file_name' => $file['name'] ?? null,
            'file_size' => $file['size'] ?? null,
        ];
    }

    /**
     * Collapses a mimetype to the coarse attachment kind this module
     * stores. Also what the Content-Type check above compares on, so a
     * `image/jpeg` file served back as `image/jpg` isn't treated as a
     * mismatch - only an actual family change (an HTML error page where an
     * image was promised) is.
     */
    private function mediaFamily(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'file',
        };
    }
}
