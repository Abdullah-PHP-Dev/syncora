<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\DiscordMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;
use WebSocket\TimeoutException;
use WebSocket\ConnectionException;

class RunDiscordGatewayListener extends Command
{
    protected $signature = 'messaging:discord-listen {channel : message_channels.id of the connected Discord bot}';

    protected $description = 'Maintains a persistent Discord Gateway connection to receive Direct Messages in real time.';

    private const GUILDS = 1 << 0;
    private const DIRECT_MESSAGES = 1 << 12;
    private const MESSAGE_CONTENT = 1 << 15;
    private const GUILD_MESSAGES = 1 << 9;
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
                Log::error("Discord Gateway connection dropped for channel #{$channel->id}: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString()
                ]);
                $this->error("Connection dropped: {$e->getMessage()} - reconnecting in 5s...");
            }

            $this->heartbeatIntervalMs = null;
            $this->lastSequence = null;
            sleep(5);
        }
    }

    private function runConnection(MessageChannel $channel, DiscordMessagingService $service): void
    {
        $gatewayUrl = adminSetting('chats.discord.gateway_url') ?: 'wss://gateway.discord.gg/?v=10&encoding=json';

        // 15s: long enough to comfortably survive normal network latency to
        // Discord's servers without destabilizing the connection, short
        // enough to still wake up and check the heartbeat clock well before
        // Discord's own ~41s interval. A too-short timeout here (2s was
        // tried and made the connection reconnect roughly every second,
        // never getting far enough to receive real events) isn't just a
        // heartbeat-precision knob - it can make the read loop unstable.
        $client = new Client($gatewayUrl, ['timeout' => 15]);

        while (true) {
            try {
                $raw = $client->receive();
            } catch (TimeoutException $e) {
                $raw = null;
            } catch (\Throwable $e) {
                Log::error('Discord Gateway Receive Error', [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                throw $e;
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
        Log::info('Discord Dispatch Event', [
            'event' => $payload['op'] ?? null,
        ]);
        match ($payload['op'] ?? null) {
            10      => $this->onHello($payload, $client, $channel),
            11 => Log::info('Heartbeat ACK'),
            0       => $this->onDispatch($payload, $channel, $service),
            1       => $this->sendHeartbeat($client),
            7, 9    => $this->onReconnectOrInvalidSession($payload),
            default => null,
        };
    }

    /**
     * Logs the raw op 7/9 payload before throwing, so a genuine rejection
     * reason (rather than just "op 9 happened") is visible in
     * storage/logs/laravel.log for the next occurrence - op 9's `d` field
     * is a resumable flag, and any additional context Discord includes
     * here is otherwise lost once this exception unwinds to the generic
     * "Connection dropped" log line in handle().
     */
    private function onReconnectOrInvalidSession(array $payload): never
    {
        Log::warning('Discord Gateway op ' . $payload['op'] . ' payload', ['payload' => $payload]);

        throw new ConnectionException('Discord requested a reconnect (op ' . $payload['op'] . ').');
    }

    private function onHello(array $payload, Client $client, MessageChannel $channel): void
    {
        $this->heartbeatIntervalMs = $payload['d']['heartbeat_interval'] ?? 41250;
        $this->lastHeartbeatSentAt = microtime(true);

        // Fetch Bot Token (Use fallback to admin setting if access_token is an OAuth token)
        $botToken = config('services.discord.bot_token') 
            ?? adminSetting('chats.discord.bot_token') 
            ?? $channel->access_token;


        $client->text(json_encode([
            'op' => 2, // IDENTIFY
            'd'  => [
                'token'      => $botToken,
                'intents'    => self::GUILDS | self::GUILD_MESSAGES | self::DIRECT_MESSAGES | self::MESSAGE_CONTENT,
                'properties' => [
                    'os'      => PHP_OS,
                    'browser' => 'socialeaz',
                    'device'  => 'socialeaz',
                ],
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
        Log::info('Discord Dispatch Event', [
            'event' => $payload['t'] ?? null,
        ]);

        switch ($payload['t'] ?? null) {

            case 'MESSAGE_CREATE':

                $message = $payload['d'] ?? [];

                // Ignore messages sent by bots
                if (!empty($message['author']['bot'])) {
                    return;
                }

                Log::info('Discord Incoming Message', [
                    'guild_id'   => $message['guild_id'] ?? null,
                    'channel_id' => $message['channel_id'] ?? null,
                    'author'     => $message['author']['username'] ?? null,
                    'content'    => $message['content'] ?? '',
                ]);

                $service->handleGatewayMessage($message, $channel);
                break;

            case 'MESSAGE_UPDATE':
            case 'MESSAGE_DELETE':
            case 'CHANNEL_CREATE':
            case 'CHANNEL_DELETE':
            case 'THREAD_CREATE':
                Log::info('Discord Event Payload', [
                    'type' => $payload['t'],
                    'data' => $payload['d'],
                ]);
                break;
        }
    }
}