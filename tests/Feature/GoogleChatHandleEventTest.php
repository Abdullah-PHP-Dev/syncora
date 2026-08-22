<?php

namespace Tests\Feature;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\GoogleChatMessagingService;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\CreatesAdminSettingsTable;
use Tests\TestCase;

/**
 * A Chat app built as a Google Workspace add-on receives the add-on
 * EventObject (message/space nested under chat.messagePayload, sender at
 * chat.user, no top-level `type`) rather than the Chat API interaction
 * Event. Both shapes must reach the inbox identically.
 */
class GoogleChatHandleEventTest extends TestCase
{
    use CreatesAdminSettingsTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdminSettingsTable();
    }

    private function channel(): MessageChannel
    {
        $channel = new MessageChannel();
        $channel->id = 3;
        $channel->platform = 'google_chat';
        $channel->meta = ['project_number' => '868193692422'];

        return $channel;
    }

    private function handle(array $payload): void
    {
        app(GoogleChatMessagingService::class)->handleEvent($payload, $this->channel());
    }

    public function test_it_queues_an_inbound_message_from_an_add_on_event_object(): void
    {
        Queue::fake();

        $this->handle([
            'chat' => [
                'user'           => ['name' => 'users/12345', 'displayName' => 'Ada'],
                'messagePayload' => [
                    'message' => [
                        'name'         => 'spaces/AAAA/messages/BBBB',
                        'text'         => 'hello from an add-on',
                        'argumentText' => 'hello from an add-on',
                    ],
                    'space'   => ['name' => 'spaces/AAAA', 'spaceType' => 'DIRECT_MESSAGE'],
                ],
            ],
            'commonEventObject' => ['hostApp' => 'CHAT'],
        ]);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_it_still_queues_an_inbound_message_from_a_classic_interaction_event(): void
    {
        Queue::fake();

        $this->handle([
            'type'    => 'MESSAGE',
            'message' => [
                'name'         => 'spaces/AAAA/messages/BBBB',
                'text'         => 'hello from a classic app',
                'argumentText' => 'hello from a classic app',
                'sender'       => ['name' => 'users/12345', 'displayName' => 'Ada'],
            ],
            'space'   => ['name' => 'spaces/AAAA', 'type' => 'DM'],
        ]);

        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_it_ignores_a_non_dm_add_on_event(): void
    {
        Queue::fake();

        $this->handle([
            'chat' => [
                'user'           => ['name' => 'users/12345'],
                'messagePayload' => [
                    'message' => ['name' => 'spaces/AAAA/messages/BBBB', 'text' => 'hi'],
                    'space'   => ['name' => 'spaces/AAAA', 'spaceType' => 'SPACE'],
                ],
            ],
        ]);

        Queue::assertNothingPushed();
    }

    public function test_it_ignores_an_add_on_event_that_is_not_a_message(): void
    {
        Queue::fake();

        $this->handle([
            'chat' => [
                'addedToSpacePayload' => [
                    'space'          => ['name' => 'spaces/AAAA', 'spaceType' => 'DIRECT_MESSAGE'],
                    'interactionAdd' => true,
                ],
            ],
        ]);

        Queue::assertNothingPushed();
    }
}
