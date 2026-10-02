<?php

namespace App\Console\Commands;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\XChat\XChatKeyService;
use App\Services\MessagingServices\XChat\XChatWorkerClient;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only health check of X Chat for one account: worker, stored PIN,
 * token scopes and the X endpoints used for text and media. Prints HTTP
 * statuses and X's error titles only - never tokens, PINs or message content.
 *
 * The two media checks only *initialize* an upload (nothing is appended or
 * sent), which tells "token lacks media.write" (403 on the posts media
 * endpoint) apart from "X refuses X Chat media" (503 on the chat endpoint).
 */
class DiagnoseXChat extends Command
{
    protected $signature = 'messaging:x-chat-diagnose {--account= : social_account_id (default: every X account with X Chat enabled)} {--conversation= : X Chat conversation id "A:B" to test media with}';

    protected $description = 'Diagnose X Chat (encrypted DMs) setup and media upload access for X accounts';

    private const API = 'https://api.x.com/2/';

    public function handle(XChatWorkerClient $worker, XChatKeyService $keys): int
    {
        $this->line('Worker (' . config('services.xchat_worker.url') . '): ' . ($worker->isAvailable() ? '<info>OK</info>' : '<error>NOT REACHABLE</error>'));

        $accounts = SocialAccount::query()
            ->where('platform', 'x')
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($accounts->isEmpty()) {
            $this->error('No X social account found.');

            return self::FAILURE;
        }

        foreach ($accounts as $account) {
            $this->newLine();
            $this->info("Account #{$account->id} @{$account->username} (X user {$account->platform_account_id})");

            $credential = $keys->credentialFor($account);
            $this->line('  X Chat PIN stored: ' . ($credential ? "yes (status: {$credential->status})" : '<comment>no - enable X Chat in Channels</comment>'));

            $channel = MessageChannel::where('social_account_id', $account->id)->where('platform', 'x')->first();

            try {
                $token = $channel ? app(XMessagingService::class)->ensureFreshToken($channel) : (string) $account->access_token;
            } catch (\Throwable $e) {
                $this->line('  <error>Access token could not be refreshed: ' . class_basename($e) . '</error> - reconnect the account.');
                continue;
            }

            if ($channel && !empty($channel->scopes ?? null)) {
                $this->line('  Stored scopes: ' . (is_array($channel->scopes) ? implode(' ', $channel->scopes) : $channel->scopes));
            }

            $this->report('users/me', Http::withToken($token)->timeout(20)->get(self::API . 'users/me'));
            $this->report('public_keys (dm.read)', Http::withToken($token)->timeout(20)->get(self::API . "users/{$account->platform_account_id}/public_keys"));

            // Posts media upload: same media.write scope, different service.
            $this->report('media.write (posts media initialize)', Http::withToken($token)->timeout(30)->asJson()->post(self::API . 'media/upload/initialize', [
                'media_type'     => 'image/png',
                'total_bytes'    => 1024,
                'media_category' => 'tweet_image',
            ]));

            // Read meta as a whole: Eloquent's value('meta->key') can't map a
            // JSON-path select back to an attribute and returns null.
            $conversationId = $this->option('conversation') ?: (Conversation::where('social_account_id', $account->id)
                ->whereNotNull('meta->x_chat_conversation_id')
                ->latest('id')
                ->first(['id', 'meta'])
                ?->meta['x_chat_conversation_id'] ?? null);

            if (!$conversationId) {
                $this->line('  X Chat media: <comment>skipped - no X Chat conversation yet (pass --conversation=A:B)</comment>');
                continue;
            }

            $colon = XChatKeyService::canonicalConversationId(trim((string) $conversationId, '"'));

            foreach (['colon' => $colon, 'hyphen' => str_replace(':', '-', $colon)] as $form => $id) {
                $this->report("X Chat media initialize ({$form} id)", Http::withToken($token)->timeout(30)->acceptJson()->asJson()->post(self::API . 'chat/media/upload/initialize', [
                    'conversation_id' => $id,
                    'total_bytes'     => 1024,
                ]));
            }
        }

        $this->newLine();
        $this->line('How to read this: 403 on "media.write" = reconnect the X account (token lacks media.write).');
        $this->line('Only "X Chat media initialize" = 503 = X-side: open an X developer ticket with the x-transaction-id shown.');

        return self::SUCCESS;
    }

    private function report(string $label, Response $response): void
    {
        $status = $response->status();
        $result = $response->successful() ? "<info>{$status} OK</info>" : "<error>{$status}</error> " . ($response->json('title') ?? $response->json('errors.0.message') ?? '');
        $txn = $response->header('x-transaction-id');

        $this->line("  {$label}: {$result}" . ($txn ? " (x-transaction-id {$txn})" : ''));
    }
}
