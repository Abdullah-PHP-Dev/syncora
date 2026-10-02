<?php

namespace Tests\Feature\XChat;

use App\Http\Controllers\Api\Messaging\XActivityWebhookController;
use App\Jobs\Messaging\ProcessAiCopilotReply;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\XChatCredential;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\MessagingServices\XChat\XChatMessageMapper;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Laravel side of encrypted X Chat webhooks: routing, idempotency, storage,
 * pending/retry, verification failures and outgoing encryption. The worker
 * (X's Chat XDK) is faked at its HTTP boundary here; the real cryptography
 * is covered by xchat-worker's own tests (npm test), which run X's
 * published test vectors through the real XDK.
 */
class XChatWebhookTest extends TestCase
{
    private const SECRET = 'test-consumer-secret';

    private SocialAccount $account;

    /** What the fake worker returns for /v1/decrypt, by encoded_event. */
    private array $workerDecrypt = [];

    private array $sentToX = [];

    private array $mediaRequests = [];

    private array $encryptAttachments = [];

    private int $initFailures = 0;

    /** Number of upcoming /v1/decrypt calls that answer 409 session_locked. */
    private int $lockedResponses = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        config(['broadcasting.default' => 'log', 'services.xchat_worker.token' => 'worker-token', 'services.xchat_worker.url' => 'http://xchat-worker.test']);
        Queue::fake([ProcessAiCopilotReply::class]);

        $user = User::create(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x')]);
        $this->account = SocialAccount::create(['user_id' => $user->id, 'platform' => 'x', 'name' => 'Seller X', 'platform_account_id' => '2222', 'access_token' => 'tok', 'has_messaging_permission' => true, 'is_token_valid' => true]);
        MessageChannel::create(['social_account_id' => $this->account->id, 'platform' => 'x', 'external_id' => '2222']);

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_starts_with($url, 'http://xchat-worker.test/v1/decrypt')) {
                if ($this->lockedResponses > 0) {
                    $this->lockedResponses--;

                    return Http::response(['error' => 'session_locked', 'message' => 'locked'], 409);
                }

                $outcome = $this->workerDecrypt[$request['event']] ?? ['error' => 'malformed_event', 'status' => 400];

                return isset($outcome['error'])
                    ? Http::response(['error' => $outcome['error'], 'message' => $outcome['error']], $outcome['status'])
                    : Http::response(['status' => 'ok', 'event' => $outcome, 'keyVersions' => ['1001'], 'keyChangeErrors' => 0]);
            }
            if (str_starts_with($url, 'http://xchat-worker.test/v1/encrypt')) {
                $this->encryptAttachments[] = $request['attachments'] ?? [];

                return Http::response(['status' => 'ok', 'payload' => ['message_id' => 'out-1', 'encoded_message_create_event' => 'CIPHERTEXT', 'encoded_message_event_signature' => 'SIG', 'conversation_key_version' => '1001']]);
            }
            if (str_starts_with($url, 'http://xchat-worker.test/v1/media/decrypt')) {
                $this->mediaRequests[] = ['decrypt', $request['key_version'], base64_decode($request['ciphertext_b64'])];

                return Http::response(['status' => 'ok', 'plaintext_b64' => base64_encode('%PDF-1.4 invoice'), 'mime_type' => 'application/pdf', 'width' => null, 'height' => null, 'size' => 16]);
            }
            if (str_starts_with($url, 'http://xchat-worker.test/v1/media/encrypt')) {
                $this->mediaRequests[] = ['encrypt', base64_decode($request['plaintext_b64'])];

                return Http::response(['status' => 'ok', 'ciphertext_b64' => base64_encode('ENCRYPTED-BYTES'), 'key_version' => '1001', 'mime_type' => 'image/png', 'width' => 4, 'height' => 3, 'plaintext_size' => 9, 'ciphertext_size' => 15]);
            }
            if (str_contains($url, '/2/chat/media/1111-2222/hash-in-1')) {
                return Http::response('CIPHER-BYTES', 200, ['Content-Type' => 'application/octet-stream']);
            }
            if (str_contains($url, '/2/chat/media/upload/initialize')) {
                $this->mediaRequests[] = ['initialize', $request->data()];
                if ($this->initFailures > 0) {
                    $this->initFailures--;

                    return Http::response(['title' => 'Service Unavailable'], 503);
                }

                return Http::response(['data' => ['session_id' => 'sess-9', 'media_hash_key' => 'hash-out-1', 'conversation_id' => '1111-2222']]);
            }
            if (preg_match('#/2/chat/media/upload/sess-9/(append|finalize)#', $url, $m)) {
                $this->mediaRequests[] = [$m[1], $request->data()];

                return Http::response(['data' => []]);
            }
            // Regular (non-encrypted) media upload + legacy DM send - the
            // fallback when X Chat media answers 503.
            if (preg_match('#api\.x\.com/2/media/upload/(initialize|dm-1/append|dm-1/finalize)#', $url, $m)) {
                $this->mediaRequests[] = ['dm:' . basename($m[1]), $request->isMultipart() ? 'multipart' : $request->data()];

                return Http::response(['data' => ['id' => 'dm-1']]);
            }
            if (str_contains($url, '/2/dm_conversations/with/1111/messages')) {
                $this->sentToX[] = ['legacy_dm' => $request->data()];

                return Http::response(['data' => ['dm_event_id' => 'dm-event-1', 'dm_conversation_id' => '1111-2222']], 201);
            }
            // A regular DM's media inside X Chat: t.co -> x.com/messages/media/{dm_event_id}.
            if ($url === 'https://t.co/Media123') {
                return Http::response('', 301, ['Location' => 'https://x.com/messages/media/1880000000000000001']);
            }
            if ($url === 'https://t.co/Other456') {
                return Http::response('', 301, ['Location' => 'https://example.com/pricing']);
            }
            if (str_contains($url, '/2/dm_events/1880000000000000001')) {
                $this->mediaRequests[] = ['dm_event', $request->data()];

                return Http::response(['data' => ['id' => '1880000000000000001', 'attachments' => ['media_keys' => ['3_1']]], 'includes' => ['media' => [['media_key' => '3_1', 'type' => 'photo', 'url' => 'https://ton.twitter.com/1.1/ton/data/dm/1/2/abc.png']]]]);
            }
            if ($url === 'https://ton.twitter.com/1.1/ton/data/dm/1/2/abc.png') {
                $this->mediaRequests[] = ['dm_download', $request->header('Authorization')[0] ?? null];

                return Http::response("\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89", 200, ['Content-Type' => 'image/png']);
            }
            if ($url === 'https://cdn.test/uploads/messaging/x/photo.png') {
                return Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']);
            }
            if (str_starts_with($url, 'http://xchat-worker.test/v1/sessions/unlock')) {
                return Http::response(['status' => 'unlocked']);
            }
            if (preg_match('#api\.x\.com/2/users/\d+/public_keys#', $url)) {
                return Http::response(['data' => [['public_key' => 'ID', 'signing_public_key' => 'SIGN', 'identity_public_key_signature' => 'BIND', 'public_key_version' => '1', 'juicebox_config' => ['token_map' => []]]]]);
            }
            if (preg_match('#api\.x\.com/2/users/\d+#', $url)) {
                return Http::response(['data' => ['id' => '1111', 'name' => 'Customer', 'username' => 'customer']]);
            }
            if (str_contains($url, '/2/chat/conversations/1111-2222/messages')) {
                $this->sentToX[] = $request->data();

                return Http::response(['data' => []], 201);
            }
            if (str_contains($url, '/2/chat/conversations/1111-2222/events')) {
                return Http::response(['data' => [], 'meta' => ['conversation_key_events' => []]]);
            }

            return Http::response([], 404);
        });
    }

    private function enableXChat(): void
    {
        XChatCredential::create(['social_account_id' => $this->account->id, 'x_user_id' => '2222', 'pin' => '4826', 'public_key_version' => '1', 'status' => 'active', 'verified_at' => now()]);
    }

    private function decryptedText(string $text, string $id = 'msg-1'): array
    {
        return ['type' => 'message', 'message_id' => $id, 'sender_id' => '1111', 'conversation_id' => '1111:2222', 'key_version' => '1001', 'verified' => true, 'content_type' => 'text', 'text' => $text, 'attachment_count' => 0];
    }

    private function deliver(string $encodedEvent, string $uuid, array $overrides = [], ?string $signature = null)
    {
        $payload = ['data' => ['event_uuid' => $uuid, 'filter' => ['user_id' => '2222'], 'event_type' => 'chat.received', 'payload' => array_merge([
            'id' => $uuid . '-id', 'sender_id' => '1111', 'conversation_id' => '1111:2222', 'conversation_key_version' => '1001',
            'encoded_event' => $encodedEvent, 'conversation_key_change_event' => 'KEYCHANGE',
            'message_event_signature' => ['public_key_version' => '1'],
        ], $overrides)]];
        $raw = json_encode($payload);
        $signature ??= 'sha256=' . base64_encode(hash_hmac('sha256', $raw, self::SECRET, true));

        return app(XActivityWebhookController::class)->receive(
            Request::create('/api/messaging/x-activity', 'POST', $payload, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWITTER_WEBHOOKS_SIGNATURE' => $signature], $raw)
        );
    }

    public function test_text_message_is_decrypted_and_stored_as_real_text(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-1'] = $this->decryptedText('Hello, I need help with my order.');

        $this->assertSame(200, $this->deliver('ENC-1', 'uuid-1')->getStatusCode());

        $message = Message::where('external_message_id', 'uuid-1-id')->firstOrFail();
        $this->assertSame('Hello, I need help with my order.', $message->body);
        $this->assertSame('text', $message->type);
        $this->assertSame('decrypted', $message->meta['x_chat']['status']);
        $this->assertTrue($message->meta['x_chat']['verified']);
        $this->assertSame('1111:2222', $message->conversation->meta['x_chat_conversation_id']);
    }

    public function test_key_change_event_is_recorded_and_passed_to_the_worker_before_decrypting(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-2'] = $this->decryptedText('after rotation');

        $this->deliver('ENC-2', 'uuid-2', ['conversation_key_change_event' => 'NEW-KEY-CHANGE', 'conversation_key_version' => '2002']);

        $this->assertDatabaseHas('x_chat_conversation_key_events', ['conversation_id' => '1111:2222', 'key_version' => '2002', 'encoded_event' => 'NEW-KEY-CHANGE']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/decrypt') && in_array('NEW-KEY-CHANGE', $r['key_change_events'], true));
        $this->assertSame('after rotation', Message::where('external_message_id', 'uuid-2-id')->value('body'));
    }

    public function test_duplicate_webhook_creates_one_message(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-3'] = $this->decryptedText('once');

        $this->deliver('ENC-3', 'uuid-3');
        $this->deliver('ENC-3', 'uuid-3');
        $this->deliver('ENC-3', 'uuid-3-redelivered', ['id' => 'uuid-3-id']);

        $this->assertSame(1, Message::where('external_message_id', 'uuid-3-id')->count());
        $this->assertSame(1, DB::table('webhook_event_receipts')->where('event_uuid', 'uuid-3')->count());
    }

    public function test_forged_webhook_signature_is_rejected(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-4'] = $this->decryptedText('should never be stored');

        $this->assertSame(403, $this->deliver('ENC-4', 'uuid-4', [], 'sha256=forged')->getStatusCode());
        $this->assertSame(0, Message::count());
    }

    public function test_failed_chat_signature_verification_never_shows_content(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-5'] = ['error' => 'signature_invalid', 'status' => 422];

        $this->assertSame(200, $this->deliver('ENC-5', 'uuid-5')->getStatusCode());

        $message = Message::where('external_message_id', 'uuid-5-id')->firstOrFail();
        $this->assertSame('unsupported', $message->type);
        $this->assertSame(XChatMessageMapper::UNVERIFIED_BODY, $message->body);
        $this->assertSame('failed', $message->meta['x_chat']['status']);
    }

    public function test_missing_key_is_a_safe_failure_and_is_retried_later(): void
    {
        // No PIN yet -> not_configured (retryable).
        $this->workerDecrypt['ENC-6'] = $this->decryptedText('decrypted on retry');

        $this->assertSame(200, $this->deliver('ENC-6', 'uuid-6')->getStatusCode());
        $message = Message::where('external_message_id', 'uuid-6-id')->firstOrFail();
        $this->assertSame(XChatMessageMapper::PENDING_BODY, $message->body);
        $this->assertSame('pending', $message->meta['x_chat']['status']);
        $this->assertSame('ENC-6', $message->meta['x_chat']['payload']['encoded_event']);

        $this->enableXChat();
        Artisan::call('messaging:x-chat-decrypt-pending');

        $message->refresh();
        $this->assertSame('decrypted on retry', $message->body);
        $this->assertSame('decrypted', $message->meta['x_chat']['status']);
        $this->assertArrayNotHasKey('payload', $message->meta['x_chat']);
    }

    public function test_locked_worker_session_is_unlocked_from_the_stored_pin_and_retried(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-7'] = $this->decryptedText('after unlock');
        $this->lockedResponses = 1; // eg. the worker restarted and lost its in-memory keys

        $this->deliver('ENC-7', 'uuid-7');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/sessions/unlock') && $r['pin'] === '4826');
        $this->assertSame('after unlock', Message::where('external_message_id', 'uuid-7-id')->value('body'));
    }

    public function test_unsupported_content_and_protocol_events_do_not_crash(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-8'] = ['type' => 'message', 'message_id' => 'm8', 'content_type' => 'poll', 'text' => null, 'verified' => true, 'attachment_count' => 0];
        $this->workerDecrypt['ENC-9'] = ['type' => 'readReceipt', 'verified' => true];

        $this->assertSame(200, $this->deliver('ENC-8', 'uuid-8')->getStatusCode());
        $this->assertSame(200, $this->deliver('ENC-9', 'uuid-9')->getStatusCode());
        $this->assertSame(200, $this->deliver('GARBAGE', 'uuid-10')->getStatusCode());

        $this->assertSame('unsupported', Message::where('external_message_id', 'uuid-8-id')->value('type'));
        $this->assertNull(Message::where('external_message_id', 'uuid-9-id')->first());
        $this->assertSame('unsupported', Message::where('external_message_id', 'uuid-10-id')->value('type'));
    }

    public function test_reply_to_x_chat_conversation_is_encrypted_not_plaintext(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-11'] = $this->decryptedText('hi');
        $this->deliver('ENC-11', 'uuid-11');
        $conversation = Message::where('external_message_id', 'uuid-11-id')->firstOrFail()->conversation;

        $result = app(XMessagingService::class)->sendMessage($conversation, ['body' => 'Thank you!']);

        $this->assertTrue($result['success']);
        $this->assertSame(['message_id' => 'out-1', 'encoded_message_create_event' => 'CIPHERTEXT', 'encoded_message_event_signature' => 'SIG'], $this->sentToX[0]);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'dm_conversations'));
    }

    public function test_inbound_attachment_is_downloaded_decrypted_and_stored_as_a_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('r2');
        $this->enableXChat();
        $this->workerDecrypt['ENC-M1'] = $this->decryptedText('') + [
            'attachment_count' => 1,
            'attachments'      => [['attachment_type' => 'media', 'media_hash_key' => 'hash-in-1', 'file_name' => 'invoice.pdf']],
        ];

        $this->assertSame(200, $this->deliver('ENC-M1', 'uuid-m1')->getStatusCode());

        $message = Message::where('external_message_id', 'uuid-m1-id')->firstOrFail();
        $this->assertSame('file', $message->type);
        $this->assertNull($message->body);
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('invoice.pdf', $attachment->file_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame(16, $attachment->file_size);
        // Decrypted with the MESSAGE's key version, from the downloaded ciphertext.
        $this->assertSame(['decrypt', '1001', 'CIPHER-BYTES'], $this->mediaRequests[0]);
        $path = ltrim(parse_url($attachment->url, PHP_URL_PATH), '/');
        \Illuminate\Support\Facades\Storage::disk('r2')->assertExists(preg_replace('#^.*?(uploads/)#', '$1', $path));
    }

    public function test_regular_dm_media_link_becomes_the_real_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('r2');
        $this->enableXChat();
        $this->workerDecrypt['ENC-T1'] = $this->decryptedText('Your invoice https://t.co/Media123');

        $this->deliver('ENC-T1', 'uuid-t1');

        $message = Message::where('external_message_id', 'uuid-t1-id')->firstOrFail();
        $this->assertSame('Your invoice', $message->body);
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('image', $attachment->type);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame('abc.png', $attachment->file_name);
        $this->assertSame(['dm_event', ['dm_event.fields' => 'attachments', 'expansions' => 'attachments.media_keys', 'media.fields' => 'type,url,preview_image_url,variants']], $this->mediaRequests[0]);
        // DM media is private - downloaded with the account's token.
        $this->assertSame(['dm_download', 'Bearer tok'], $this->mediaRequests[1]);
    }

    public function test_media_only_regular_dm_becomes_an_image_message(): void
    {
        \Illuminate\Support\Facades\Storage::fake('r2');
        $this->enableXChat();
        $this->workerDecrypt['ENC-T2'] = $this->decryptedText('https://t.co/Media123');

        $this->deliver('ENC-T2', 'uuid-t2');

        $message = Message::where('external_message_id', 'uuid-t2-id')->firstOrFail();
        $this->assertSame('image', $message->type);
        $this->assertNull($message->body);
        $this->assertSame(1, $message->attachments()->count());
    }

    public function test_ordinary_links_stay_in_the_text(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-T3'] = $this->decryptedText('See https://t.co/Other456');

        $this->deliver('ENC-T3', 'uuid-t3');

        $message = Message::where('external_message_id', 'uuid-t3-id')->firstOrFail();
        $this->assertSame('See https://t.co/Other456', $message->body);
        $this->assertSame('text', $message->type);
        $this->assertSame(0, $message->attachments()->count());
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/2/dm_events/'));
    }

    public function test_media_upload_retries_a_transient_5xx(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-M4'] = $this->decryptedText('hi');
        $this->deliver('ENC-M4', 'uuid-m4');
        $conversation = Message::where('external_message_id', 'uuid-m4-id')->firstOrFail()->conversation;
        $this->initFailures = 1; // first initialize answers 503

        $result = app(XMessagingService::class)->sendMessage($conversation, ['body' => 'pic', 'media_url' => 'https://cdn.test/uploads/messaging/x/photo.png', 'file_name' => 'photo.png']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame(2, collect($this->mediaRequests)->where(0, 'initialize')->count());
    }

    public function test_link_attachment_becomes_a_link_and_failed_media_is_noted(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-M2'] = $this->decryptedText('look') + [
            'attachment_count' => 2,
            'attachments'      => [
                ['attachment_type' => 'url', 'url' => 'https://example.com/product'],
                ['attachment_type' => 'media', 'media_hash_key' => 'missing-hash'],
            ],
        ];

        $this->deliver('ENC-M2', 'uuid-m2');

        $body = Message::where('external_message_id', 'uuid-m2-id')->value('body');
        $this->assertStringContainsString('look', $body);
        $this->assertStringContainsString('https://example.com/product', $body);
        $this->assertStringContainsString('1 attachment(s) could not be loaded', $body);
    }

    public function test_outbound_file_is_encrypted_uploaded_and_attached(): void
    {
        $this->enableXChat();
        $this->workerDecrypt['ENC-M3'] = $this->decryptedText('hi');
        $this->deliver('ENC-M3', 'uuid-m3');
        $conversation = Message::where('external_message_id', 'uuid-m3-id')->firstOrFail()->conversation;

        $result = app(XMessagingService::class)->sendMessage($conversation, ['body' => '', 'media_url' => 'https://cdn.test/uploads/messaging/x/photo.png', 'file_name' => 'photo.png']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame(['encrypt', 'PNG-BYTES'], $this->mediaRequests[0]);
        $this->assertSame('initialize', $this->mediaRequests[1][0]);
        // Media endpoints take the colon conversation id in the body (X reference client).
        $this->assertSame(['conversation_id' => '1111:2222', 'total_bytes' => 15], $this->mediaRequests[1][1]);
        $this->assertSame(['append', ['conversation_id' => '1111:2222', 'media_hash_key' => 'hash-out-1', 'segment_index' => '0', 'media' => base64_encode('ENCRYPTED-BYTES')]], $this->mediaRequests[2]);
        $this->assertSame(['finalize', ['conversation_id' => '1111:2222', 'media_hash_key' => 'hash-out-1', 'num_parts' => '1']], $this->mediaRequests[3]);
        $this->assertSame([['attachment_type' => 'media', 'media_hash_key' => 'hash-out-1', 'width' => 4, 'height' => 3, 'filesize_bytes' => 9, 'filename' => 'photo.png']], end($this->encryptAttachments));
        $this->assertSame('CIPHERTEXT', end($this->sentToX)['encoded_message_create_event']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->body(), 'PNG-BYTES') && str_contains($r->url(), 'api.x.com'));
    }

    public function test_file_falls_back_to_regular_dm_when_x_chat_media_is_unavailable(): void
    {
        \Illuminate\Support\Sleep::fake();
        $this->enableXChat();
        $this->workerDecrypt['ENC-M4'] = $this->decryptedText('hi');
        $this->deliver('ENC-M4', 'uuid-m4');
        $conversation = Message::where('external_message_id', 'uuid-m4-id')->firstOrFail()->conversation;
        $this->initFailures = 3; // X Chat media initialize: 503 on every retry

        $result = app(XMessagingService::class)->sendMessage($conversation, ['body' => 'Your invoice', 'media_url' => 'https://cdn.test/uploads/messaging/x/photo.png', 'file_name' => 'photo.png']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('dm-event-1', $result['external_message_id']);
        // The conversation must stay on X Chat routing.
        $this->assertArrayNotHasKey('external_conversation_id', $result);
        $this->assertSame(['media_type' => 'image/png', 'total_bytes' => 9, 'media_category' => 'dm_image'], collect($this->mediaRequests)->firstWhere(0, 'dm:initialize')[1]);
        $this->assertSame(['text' => 'Your invoice', 'attachments' => [['media_id' => 'dm-1']]], end($this->sentToX)['legacy_dm']);
        // Nothing was sent as an encrypted X Chat message.
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/2/chat/conversations/'));
    }

    public function test_text_only_x_chat_failure_does_not_fall_back(): void
    {
        $conversation = $this->xChatConversationWithoutPin();

        $result = app(XMessagingService::class)->sendMessage($conversation, ['body' => 'hello']);

        $this->assertFalse($result['success']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'dm_conversations'));
    }

    private function xChatConversationWithoutPin(): \App\Models\Messaging\Conversation
    {
        return \App\Models\Messaging\Conversation::create([
            'social_account_id' => $this->account->id, 'platform' => 'x', 'customer_external_id' => '1111',
            'meta' => ['x_chat_conversation_id' => '1111:2222'], 'status' => 'open',
        ]);
    }

    /**
     * Some older messaging migrations use MySQL-only SQL (DELETE ... JOIN),
     * so - like Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables -
     * the messaging tables are built here with their current schema; the
     * new X Chat migration itself runs as-is.
     */
    private function createTables(): void
    {
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        Schema::create('message_channels', function ($t) {
            $t->id();
            $t->foreignId('social_account_id')->nullable();
            $t->string('platform');
            $t->string('external_id')->nullable();
            $t->string('verify_token')->nullable();
            $t->longText('meta')->nullable();
            $t->boolean('webhook_subscribed')->default(false);
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('conversations', function ($t) {
            $t->id();
            $t->foreignId('social_account_id');
            $t->string('platform');
            $t->string('external_conversation_id')->nullable();
            $t->longText('meta')->nullable();
            $t->string('customer_external_id');
            $t->string('customer_name')->nullable();
            $t->text('customer_avatar_url')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->string('last_message_preview')->nullable();
            $t->unsignedInteger('unread_count')->default(0);
            $t->string('status')->default('open');
            $t->timestamp('ai_paused_at')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('messages', function ($t) {
            $t->id();
            $t->foreignId('conversation_id');
            $t->string('external_message_id')->nullable();
            $t->string('direction');
            $t->string('sender_type');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type')->default('text');
            $t->text('body')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
            $t->unique(['conversation_id', 'external_message_id']);
        });
        Schema::create('message_attachments', function ($t) {
            $t->id();
            $t->foreignId('message_id');
            $t->string('type');
            $t->text('url');
            $t->string('mime_type')->nullable();
            $t->string('file_name')->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_09_05_223054_create_webhook_logs_table.php'))->up();
        (require database_path('migrations/2026_10_01_120000_create_x_chat_tables.php'))->up();

        Schema::create('admin_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        // Through Settings::set() (not a raw insert): it also clears the
        // settings memo/cache an earlier test in this process may have
        // filled without this key.
        \App\Support\Settings::set('posts.x.consumer_secret', self::SECRET);
    }
}
