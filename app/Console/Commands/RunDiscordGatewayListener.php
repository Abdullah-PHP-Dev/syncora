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
        $this->lastHeartbeatSentAt = microtime(true);

        // Fetch Bot Token (Use fallback to admin setting if access_token is an OAuth token)
        $botToken = config('services.discord.bot_token') 
            ?? adminSetting('chats.discord.bot_token') 
            ?? $channel->access_token;

        $client->text(json_encode([
            'op' => 2, // IDENTIFY
            'd'  => [
                'token'      => $botToken,
                'intents'    => self::GUILDS | self::DIRECT_MESSAGES | self::MESSAGE_CONTENT,
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
        if (($payload['t'] ?? null) !== 'MESSAGE_CREATE') {
            return;
        }

        $message = $payload['d'] ?? [];

        // Ignore messages sent by bots to prevent reply loops
        if (!empty($message['author']['bot'])) {
            return;
        }

        $service->handleGatewayMessage($message, $channel);
    }
}