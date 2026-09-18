<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\MatrixMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Matrix has no webhook for a plain Client-Server API account (see
 * MatrixMessagingService's docblock for why the Application Service API,
 * which does get pushed events, isn't a realistic option here) - the
 * standard way any Matrix client, bot or human, learns about new
 * messages is by long-polling GET /sync, which blocks server-side for up
 * to `timeout` milliseconds and returns immediately once something new
 * exists. That makes this daemon considerably simpler than Discord's
 * Gateway listener: there's no separate heartbeat protocol to maintain,
 * the long-poll itself *is* the heartbeat, and a dropped connection just
 * means retrying the same request.
 *
 * Run one instance per connected Matrix account, under a process
 * supervisor that always restarts it - the exact same operational shape
 * as RunDiscordGatewayListener and `queue:work`:
 *
 *   php artisan messaging:matrix-listen {channel_id}
 */
class RunMatrixSyncListener extends Command
{
    protected $signature = 'messaging:matrix-listen {channel : message_channels.id of the connected Matrix account}';

    protected $description = 'Long-polls Matrix /sync to receive messages in real time. Runs forever - use a process supervisor to keep it alive.';

    public function handle(ApiService $apiService, MatrixMessagingService $service): int
    {
        $channel = MessageChannel::find($this->argument('channel'));

        if (!$channel || $channel->platform !== 'matrix') {
            $this->error('No Matrix channel found with that ID.');

            return self::FAILURE;
        }

        $homeserverUrl = rtrim($channel->meta['homeserver_url'] ?? '', '/');
        $authHeader = ['Authorization' => 'Bearer ' . $channel->socialAccount->access_token];

        $this->info("Starting Matrix /sync listener for channel #{$channel->id} ({$channel->socialAccount->name}). This process runs forever.");

        // The very first /sync without a `since` token returns full
        // current state plus recent timeline for every joined room -
        // processing that as "new" messages would replay old history on
        // every restart. Fetched with timeout=0 (no long-poll) purely to
        // establish a clean starting cursor; its own events are discarded.
        $since = null;
        $initial = $apiService->get($homeserverUrl . '/_matrix/client/v3/sync', $authHeader, ['timeout' => 0]);

        if ($initial['success']) {
            $since = $initial['data']['next_batch'] ?? null;
        }

        while (true) {
            $query = ['timeout' => 30000];

            if ($since) {
                $query['since'] = $since;
            }

            $response = $apiService->get($homeserverUrl . '/_matrix/client/v3/sync', $authHeader, $query);

            if (!$response['success']) {
                Log::warning("Matrix /sync request failed for channel #{$channel->id}, retrying in 5s.", ['error' => $response['error'] ?? $response['data'] ?? null]);
                sleep(5);

                continue;
            }

            $data = $response['data'];
            $since = $data['next_batch'] ?? $since;

            $this->processInvites($apiService, $homeserverUrl, $authHeader, $data, $channel);
            $this->processMessages($service, $data, $channel);
        }
    }

    /**
     * Rooms this account has been invited to but not joined stay in
     * `rooms.invite` on every sync until acted on - so joining here,
     * rather than only on the very first sight of an invite, is safe:
     * nothing is lost if a restart means an invite is "seen" again.
     */
    private function processInvites(ApiService $apiService, string $homeserverUrl, array $authHeader, array $data, MessageChannel $channel): void
    {
        foreach (array_keys($data['rooms']['invite'] ?? []) as $roomId) {
            $joinResponse = $apiService->post($homeserverUrl . "/_matrix/client/v3/rooms/{$roomId}/join", $authHeader, []);

            if (!$joinResponse['success']) {
                Log::warning("Failed to auto-join Matrix room {$roomId} for channel #{$channel->id}.", ['error' => $joinResponse['data'] ?? null]);
            }
        }
    }

    private function processMessages(MatrixMessagingService $service, array $data, MessageChannel $channel): void
    {
        foreach ($data['rooms']['join'] ?? [] as $roomId => $room) {
            foreach ($room['timeline']['events'] ?? [] as $event) {
                $service->handleMessageEvent($event, $roomId, $channel);
            }
        }
    }
}
