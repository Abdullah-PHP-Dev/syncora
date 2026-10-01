<?php

namespace App\Console\Commands;

use App\Models\Messaging\Message;
use App\Services\MessagingServices\XChat\XChatDecryptionService;
use App\Services\MessagingServices\XChat\XChatException;
use App\Services\MessagingServices\XChat\XChatMessageMapper;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Decrypts X Chat messages stored as pending (messages.meta.x_chat.status =
 * pending): ones that arrived before the account's X Chat PIN was provided,
 * while the worker was down, or before their conversation key was known.
 * Their encrypted event (ciphertext only) is kept in meta for exactly this.
 */
class DecryptPendingXChatMessages extends Command
{
    protected $signature = 'messaging:x-chat-decrypt-pending {--account= : Only this social_account_id} {--limit=200}';

    protected $description = 'Decrypt X Chat messages that are still pending decryption';

    public function handle(XChatDecryptionService $decryption, XMessagingService $messaging): int
    {
        $query = Message::query()
            ->where('meta->x_chat->status', 'pending')
            ->where('created_at', '>=', now()->subDays(30))
            ->with('conversation.channel')
            ->orderBy('id')
            ->limit((int) $this->option('limit'));

        if ($accountId = $this->option('account')) {
            $query->whereHas('conversation', fn ($q) => $q->where('social_account_id', $accountId));
        }

        $done = $failed = $waiting = 0;

        foreach ($query->get() as $message) {
            $account = $message->conversation?->channel;
            $payload = $message->meta['x_chat']['payload'] ?? null;

            if (!$account || !$payload) {
                continue;
            }

            try {
                $event = $decryption->decryptIncomingEvent($payload, $account);
                $mapped = XChatMessageMapper::map($event);
                $meta = ['x_chat' => [
                    'status'       => 'decrypted',
                    'verified'     => (bool) ($event['verified'] ?? false),
                    'content_type' => $event['content_type'] ?? null,
                    'key_version'  => $event['key_version'] ?? null,
                ]];

                if ($mapped['action'] === 'edit') {
                    $messaging->applyXChatEdit($account->id, $mapped['target_message_id'], $mapped['body']);
                    $message->delete(); // the placeholder was the edit event itself
                } elseif ($mapped['action'] === 'ignore') {
                    $message->delete(); // protocol event, not a chat message
                } else {
                    $message->update(['type' => $mapped['type'], 'body' => $mapped['body'], 'meta' => $meta]);

                    if ($message->conversation->messages()->latest('id')->value('id') === $message->id) {
                        $message->conversation->update(['last_message_preview' => \Illuminate\Support\Str::limit((string) $mapped['body'], 120)]);
                    }
                }

                $done++;
            } catch (XChatException $e) {
                if ($e->isRetryable()) {
                    $waiting++;
                    continue;
                }

                $message->update([
                    'type' => 'unsupported',
                    'body' => XChatMessageMapper::UNVERIFIED_BODY,
                    'meta' => ['x_chat' => ['status' => 'failed', 'reason' => $e->reason]],
                ]);
                $failed++;
            } catch (\Throwable $e) {
                Log::error('X Chat pending decrypt crashed.', ['message_id' => $message->id, 'error' => $e->getMessage()]);
                $waiting++;
            }
        }

        $this->info("X Chat pending: {$done} decrypted, {$failed} failed verification, {$waiting} still waiting.");

        return self::SUCCESS;
    }
}
