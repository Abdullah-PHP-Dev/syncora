<?php

namespace Tests\Feature;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\SlackMessagingService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAdminSettingsTable;
use Tests\TestCase;

/**
 * A Slack DM carrying an attachment arrives as a `message.im` with
 * subtype `file_share` and a `files` array. Rejecting every subtyped
 * message discarded exactly those - text DMs arrived, image DMs never
 * did - which is what these cover.
 */
class SlackHandleWebhookTest extends TestCase
{
    use CreatesAdminSettingsTable;

    private const FILE_URL = 'https://files.slack.com/files-pri/T123-F456/photo.png';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdminSettingsTable();
        Storage::fake('r2');
        Queue::fake();
    }

    private function channel(): MessageChannel
    {
        $channel = new MessageChannel();
        $channel->id = 26;
        $channel->platform = 'slack';
        $channel->access_token = 'xoxb-test-token';
        $channel->meta = ['bot_user_id' => 'U0BOT'];

        return $channel;
    }

    private function fileShareEvent(array $overrides = []): array
    {
        return ['event' => array_merge([
            'type'         => 'message',
            'channel_type' => 'im',
            'subtype'      => 'file_share',
            'user'         => 'U0HUMAN',
            'channel'      => 'D0DM',
            'ts'           => '1529342081.000200',
            'text'         => 'We got one!',
            'files'        => [[
                'id'          => 'F0RDC39U1',
                'name'        => 'photo.png',
                'mimetype'    => 'image/png',
                'size'        => 196920,
                'url_private' => self::FILE_URL,
            ]],
        ], $overrides)];
    }

    private function handle(array $payload): void
    {
        app(SlackMessagingService::class)->handleWebhook($payload, $this->channel());
    }

    public function test_it_ingests_an_image_sent_as_a_file_share_subtype(): void
    {
        Http::fake([
            self::FILE_URL => Http::response('binary-png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->handle($this->fileShareEvent());

        Queue::assertPushed(ProcessInboundMessage::class);
        Storage::disk('r2')->assertExists('uploads/slack/media/F0RDC39U1_photo.png');
    }

    public function test_it_sends_the_bot_token_when_downloading_the_file(): void
    {
        Http::fake([
            self::FILE_URL => Http::response('binary-png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->handle($this->fileShareEvent());

        Http::assertSent(fn (ClientRequest $request) => $request->url() === self::FILE_URL
            && $request->hasHeader('Authorization', 'Bearer xoxb-test-token'));
    }

    public function test_it_still_ingests_a_plain_text_dm(): void
    {
        $this->handle(['event' => [
            'type'         => 'message',
            'channel_type' => 'im',
            'user'         => 'U0HUMAN',
            'channel'      => 'D0DM',
            'ts'           => '1529342081.000100',
            'text'         => 'hello',
        ]]);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_it_ignores_an_edited_message(): void
    {
        $this->handle($this->fileShareEvent(['subtype' => 'message_changed']));

        Queue::assertNothingPushed();
    }

    public function test_it_ignores_a_channel_join_notice(): void
    {
        $this->handle($this->fileShareEvent(['subtype' => 'channel_join', 'files' => []]));

        Queue::assertNothingPushed();
    }

    public function test_it_ignores_the_bots_own_file_share_echo(): void
    {
        $this->handle($this->fileShareEvent(['user' => 'U0BOT']));

        Queue::assertNothingPushed();
    }

    /**
     * files.slack.com answers an unauthorized fetch with a 200 and an HTML
     * sign-in page - which used to be written to R2 under the image's own
     * name, producing a broken attachment and no log line.
     */
    public function test_it_refuses_to_store_an_html_sign_in_page_as_an_image(): void
    {
        Http::fake([
            self::FILE_URL => Http::response('<html>sign in</html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $this->handle($this->fileShareEvent());

        Storage::disk('r2')->assertMissing('uploads/slack/media/F0RDC39U1_photo.png');
    }

    public function test_an_unfetchable_file_does_not_stop_the_message_itself(): void
    {
        Http::fake([
            self::FILE_URL => Http::response('nope', 403),
        ]);

        $this->handle($this->fileShareEvent());

        Queue::assertPushed(ProcessInboundMessage::class);
        Storage::disk('r2')->assertMissing('uploads/slack/media/F0RDC39U1_photo.png');
    }
}
