<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\DiscordMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;
use WebSocket\TimeoutException;
use WebSocket\ConnectionException;

/**
 * Discord is the one platform in this messaging module with no webhook
 * and no viable REST polling substitute - GET /users/@me/channels always
 * returns empty for bots, so the Gateway's MESSAGE_CREATE dispatch is the
 * *only* way a bot can learn about an inbound DM (confirmed against
 * discord/discord-api-docs issue threads this session, not just general
 * docs). This command IS that connection: unlike every other piece of
 * this app, it's designed to run forever, not respond to a request or
 * finish a scheduled tick.
 *
 * Run one instance per connected Discord channel, under a process
 * supervisor (Supervisor, systemd, etc.) configured to always restart it
 * - the same operational pattern as `php artisan queue:work`, just for a
 * Discord connection instead of your own queue:
 *
 *   php artisan messaging:discord-listen {channel_id}
 *
 * Deliberately does not implement Gateway RESUME (op 6) - only a fresh
 * IDENTIFY on every (re)connect. RESUME is a network-efficiency
 * optimization; skipping it trades a little bandwidth on reconnect for a
 * meaningfully smaller, easier-to-verify implementation, which matters
 * here specifically because this code has no live Discord bot token to
 * test against in this environment.
 */
class RunDiscordGatewayListener extends Command
{
    protected $signature = 'messaging:discord-listen {channel : message_channels.id of the connected Discord bot}';

    protected $description = 'Maintains a persistent Discord Gateway connection to receive Direct Messages in real time. Runs forever - use a process supervisor to keep it alive.';

    private const GUILDS = 1 << 0;
    private const DIRECT_MESSAGES = 1 << 12;
    private const MESSAGE_CONTENT = 1 << 15;

    private ?int $heartbeatIntervalMs = null;
    private ?int $lastSequence = null;
    private float $lastHeartbeatSentAt = 0;

    public function handle(DiscordMessagingService $service): int
    {
        $channel = MessageChannel::find($this->argument('channel'));

        if (!$channel || $channel->platform !== 'discord') {
            $this->error('No Discord channel found with that ID.');

            return self::FAILURE;
        }

        $this->info("Starting Discord Gateway listener for channel #{$channel->id} ({$channel->name}). This process runs forever.");

        while (true) {
            try {
                $this->runConnection($channel, $service);
            } catch (\Throwable $e) {
                Log::error("Discord Gateway connection dropped for channel #{$channel->id}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                $this->error("Connection dropped: {$e->getMessage()} - reconnecting in 5s...");
            }

            $this->heartbeatIntervalMs = null;
            $this->lastSequence = null;
            sleep(5);
        }
    }

    private function runConnection(MessageChannel $channel, DiscordMessagingService $service): void
    {
        $gatewayUrl = adminSetting('messaging.discord.gateway_url') ?: 'wss://gateway.discord.gg/?v=10&encoding=json';

        // Shorter than Discord's own heartbeat interval (~41s) so the loop
        // wakes up via TimeoutException often enough to send heartbeats on
        // schedule even when Discord itself is quiet - receive() only
        // returns when *something* arrives, so without a short client-side
        // timeout there'd be no chance to proactively send anything.
        $client = new Client($gatewayUrl, ['timeout' => 15]);

        while (true) {
            try {
                $raw = $client->receive();
            } catch (TimeoutException $e) {
                $raw = null;
            }

            if ($raw !== null && $raw !== '') {
                $this->handleFrame($raw, $client, $channel, $service);
            }

            if ($this->heartbeatIntervalMs !== null) {
                $elapsedMs = (microtime(true) - $this->lastHeartbeatSentAt) * 1000;

                if ($elapsedMs >= $this->heartbeatIntervalMs) {
                    $this->sendHeartbeat($client);
                }
            }
        }
    }

    private function handleFrame(string $raw, Client $client, MessageChannel $channel, DiscordMessagingService $service): void
    {
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            return;
        }

        if (isset($payload['s'])) {
            $this->lastSequence = $payload['s'];
        }

        match ($payload['op'] ?? null) {
            10      => $this->onHello($payload, $client, $channel),
            0       => $this->onDispatch($payload, $channel, $service),
            1       => $this->sendHeartbeat($client),
            7, 9    => throw new ConnectionException('Discord requested a reconnect (op ' . $payload['op'] . ').'),
            default => null,
        };
    }

    private function onHello(array $payload, Client $client, MessageChannel $channel): void
    {
        $this->heartbeatIntervalMs = $payload['d']['heartbeat_interval'] ?? 41250;

        $client->text(json_encode([
            'op' => 2, // IDENTIFY
            'd'  => [
                // No "Bot " prefix here - that's an HTTP Authorization
                // header convention, the Gateway IDENTIFY payload takes
                // the raw token.
                'token'      => $channel->access_token,
                'intents'    => self::GUILDS | self::DIRECT_MESSAGES | self::MESSAGE_CONTENT,
                'properties' => ['os' => PHP_OS, 'browser' => 'syncora', 'device' => 'syncora'],
            ],
        ]));

        $this->sendHeartbeat($client);
    }

    private function sendHeartbeat(Client $client): void
    {
        $client->text(json_encode(['op' => 1, 'd' => $this->lastSequence]));
        $this->lastHeartbeatSentAt = microtime(true);
    }

    private function onDispatch(array $payload, MessageChannel $channel, DiscordMessagingService $service): void
    {
        if (($payload['t'] ?? null) !== 'MESSAGE_CREATE') {
            return;
        }

        $message = $payload['d'] ?? [];

        // DMs carry no guild_id at all; ignore messages from bots
        // (including this one's own sent messages echoing back) to avoid
        // reply loops.
        if (isset($message['guild_id']) || !empty($message['author']['bot'])) {
            return;
        }

        $service->handleGatewayMessage($message, $channel);
    }
}
