<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\DiscordMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;

class RunDiscordGatewayListener extends Command
{
    protected $signature = 'messaging:discord-listen {channel : message_channels.id of the connected Discord bot}';
    protected $description = 'Maintains a persistent Discord Gateway connection to receive Direct Messages in real time.';

    private const GUILDS                  = 1 << 0;
    private const GUILD_MESSAGES          = 1 << 9;
    private const DIRECT_MESSAGES         = 1 << 12;
    private const DIRECT_MESSAGE_REACTIONS = 1 << 13;
    private const DIRECT_MESSAGE_TYPING    = 1 << 14;
    private const MESSAGE_CONTENT         = 1 << 15;

    public function handle(DiscordMessagingService $service): int
    {
        $channel = MessageChannel::find($this->argument('channel'));

        if (!$channel || $channel->platform !== 'discord') {
            $this->error('No Discord channel found with that ID.');
            return self::FAILURE;
        }

        $this->info("Starting Discord Gateway listener for channel #{$channel->id} ({$channel->name}).");

        $loop = Loop::get();
        $connector = new Connector($loop);

        $gatewayUrl = adminSetting('chats.discord.gateway_url') ?: 'wss://gateway.discord.gg/?v=10&encoding=json';

        $connector($gatewayUrl)->then(
            function (WebSocket $conn) use ($channel, $service, $loop) {
                $this->info('Connected to Discord Gateway!');
                
                $heartbeatTimer = null;
                $lastSeq = null;

                $conn->on('message', function (MessageInterface $msg) use ($conn, $channel, $service, $loop, &$heartbeatTimer, &$lastSeq) {
                    $payload = json_decode((string) $msg, true);
                    if (!is_array($payload)) return;

                    if (isset($payload['s'])) {
                        $lastSeq = $payload['s'];
                    }

                    $op = $payload['op'] ?? null;
                    $t  = $payload['t'] ?? null;

                    Log::info('Discord Dispatch Event', ['op' => $op, 't' => $t]);

                    // HELLO (op 10)
                    if ($op === 10) {
                        $interval = ($payload['d']['heartbeat_interval'] ?? 41250) / 1000;

                        // Start periodic heartbeat
                        $heartbeatTimer = $loop->addPeriodicTimer($interval, function () use ($conn, &$lastSeq) {
                            $conn->send(json_encode(['op' => 1, 'd' => $lastSeq]));
                            Log::info('Heartbeat Sent');
                        });

                        // IDENTIFY (op 2)
                        $rawToken = $channel->access_token
                            ?? adminSetting('chats.discord.bot_token')
                            ?? config('services.discord.bot_token');

                        $botToken = preg_replace('/^(Bot|Bearer)\s+/i', '', trim((string) $rawToken));

                        $conn->send(json_encode([
                            'op' => 2,
                            'd'  => [
                                'token'   => $botToken,
                                'intents' => self::GUILDS 
                                           | self::GUILD_MESSAGES 
                                           | self::DIRECT_MESSAGES 
                                           | self::DIRECT_MESSAGE_REACTIONS 
                                           | self::DIRECT_MESSAGE_TYPING 
                                           | self::MESSAGE_CONTENT,
                                'properties' => [
                                    'os'      => PHP_OS,
                                    'browser' => 'socialeaz',
                                    'device'  => 'socialeaz',
                                ],
                            ],
                        ]));
                    }

                    // HEARTBEAT ACK (op 11)
                    if ($op === 11) {
                        Log::info('Heartbeat ACK');
                    }

                    // DISPATCH (op 0)
                    if ($op === 0) {
                        $this->onDispatch($payload, $channel, $service);
                    }
                });

                $conn->on('close', function ($code = null, $reason = null) use ($loop, &$heartbeatTimer) {
                    if ($heartbeatTimer) $loop->cancelTimer($heartbeatTimer);
                    Log::warning("Discord Gateway closed ($code): $reason");
                    $loop->stop();
                });
            },
            function (\Exception $e) {
                Log::error("Could not connect to Discord Gateway: {$e->getMessage()}");
            }
        );

        $loop->run();

        return self::SUCCESS;
    }

    private function onDispatch(array $payload, MessageChannel $channel, DiscordMessagingService $service): void
    {
        $event = $payload['t'] ?? null;
        Log::info('Discord Dispatch Received', ['event' => $event]);

        if ($event === 'MESSAGE_CREATE') {
            $message = $payload['d'] ?? [];

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
        }
    }
}