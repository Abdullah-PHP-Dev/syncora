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
        // Discord's own ~41s interval. (A once-per-second reconnect loop
        // was previously seen and mistakenly attributed to this timeout
        // being too short - the real cause was the missing close-frame
        // check below, unrelated to this value.)
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

            // textalk/websocket's receive() returns null - not an
            // exception - when the frame it just read was a WS close
            // frame; it fclose()s the underlying socket internally
            // (lib/Base.php's receiveFragment()) but never signals the
            // caller beyond that. Because a plain read timeout above
            // *also* yields $raw === null, this loop was previously
            // unable to tell "nothing arrived yet, keep waiting" apart
            // from "the connection was just closed" - it silently called
            // receive() again, which re-opened a brand new connection via
            // isConnected()/connect() and got a fresh HELLO, over and
            // over, roughly once a second, never reaching the 5s-backoff
            // retry in handle() below. That silent, tight reconnect loop
            // is also what risks tripping Discord's Gateway session-start
            // rate limit. getCloseStatus() is only non-null when a close
            // frame was actually processed (untouched by a timeout), so
            // it distinguishes the two cases and turns a real close into
            // a logged, backed-off reconnect instead of an invisible one.
            if ($raw === null && $client->getCloseStatus() !== null) {
                throw new ConnectionException(
                    'Discord Gateway closed the connection (status ' . $client->getCloseStatus() . ').'
                );
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

        // $channel->access_token is the bot token storeDiscord() already
        // verified via verifyBotToken() (GET /users/@me) before saving it
        // - the one actually confirmed to work for this specific channel.
        // config()/adminSetting() are only a fallback for the (currently
        // theoretical) case where a channel has no token of its own; they
        // must never take priority over an already-verified per-channel
        // token, which is what caused every IDENTIFY to send a stale or
        // unrelated global bot_token instead and get closed with Gateway
        // status 4004 (Authentication failed) regardless of how valid the
        // channel's own saved token was.
        $botToken = 'bot ' . $channel->access_token
            ?? adminSetting('chats.discord.bot_token')
            ?? config('services.discord.bot_token');


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