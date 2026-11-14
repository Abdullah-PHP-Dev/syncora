<?php

namespace App\Console\Commands;

use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Why aren't Facebook Messenger messages arriving? Walks the whole path and
 * prints one line per check:
 *  1. the app's webhook in Meta (callback URL + "messages" field on the Page object),
 *  2. each Page: Inbox channel, token, pages_messaging grant, Page subscription fields,
 *  3. the queue (inbound messages are stored by a queued job),
 *  4. the last stored inbound message per Page.
 * Read-only: it only makes GET calls to Facebook.
 */
class DiagnoseMetaMessages extends Command
{
    protected $signature = 'meta:diagnose-messages {page? : A Facebook Page id to check (default: all connected Pages)}';

    protected $description = 'Explain why Facebook Messenger messages are or are not reaching the Inbox';

    private int $problems = 0;

    public function handle(MetaPostService $meta): int
    {
        $this->appWebhook();
        $this->pages($meta);
        $this->queue();
        $this->newLine();
        $this->problems ? $this->error("{$this->problems} problem(s) found - fix the FAIL lines above.") : $this->info('No problems found on this server. If messages still do not arrive, check the Meta App is in Live mode and pages_messaging has Advanced Access.');

        return $this->problems ? self::FAILURE : self::SUCCESS;
    }

    private function appWebhook(): void
    {
        $this->info('1. App webhook in Meta');
        $appId = (string) adminSetting('posts.facebook.client_id');
        $secret = (string) adminSetting('posts.facebook.client_secret');
        if (! $appId || ! $secret) {
            $this->bad('posts.facebook client_id / client_secret not set in admin settings');

            return;
        }
        $version = adminSetting('messaging.meta.graph_version') ?: 'v21.0';
        try {
            $res = Http::timeout(15)->get("https://graph.facebook.com/{$version}/{$appId}/subscriptions", ['access_token' => $appId.'|'.$secret]);
        } catch (\Throwable $e) {
            // Never print the exception: its message contains the request URL with the app token.
            $this->bad('cannot reach graph.facebook.com from this server ('.class_basename($e).')');

            return;
        }
        if (! $res->successful()) {
            $this->bad('cannot read app subscriptions: '.($res->json('error.message') ?? 'HTTP '.$res->status()));

            return;
        }
        $page = collect($res->json('data') ?? [])->firstWhere('object', 'page');
        if (! $page) {
            $this->bad('the app has NO webhook for the "page" object (App Dashboard > Webhooks > Page)');

            return;
        }
        $fields = collect($page['fields'] ?? [])->pluck('name')->all();
        $this->ok('callback URL: '.$page['callback_url'].($page['active'] ?? false ? ' (active)' : ' (INACTIVE)'));
        if (! ($page['active'] ?? false)) {
            $this->bad('the Page webhook is inactive in Meta - re-verify it in the App Dashboard');
        }
        if (! str_contains($page['callback_url'], '/api/comments/facebook') && ! str_contains($page['callback_url'], '/api/messaging/facebook')) {
            $this->bad('callback URL does not point at /api/comments/facebook or /api/messaging/facebook');
        }
        in_array('messages', $fields, true) ? $this->ok('app subscribes the "messages" field') : $this->bad('"messages" is NOT ticked for the Page object in App Dashboard > Webhooks (fields: '.implode(',', $fields).')');
    }

    private function pages(MetaPostService $meta): void
    {
        $this->newLine();
        $this->info('2. Pages');
        $pages = SocialAccount::with('connection')->where('platform', 'facebook')->where('account_type', 'page')
            ->when($this->argument('page'), fn ($q, $id) => $q->where('platform_account_id', $id))->orderBy('id')->get();
        if ($pages->isEmpty()) {
            $this->bad('no connected Facebook Page'.($this->argument('page') ? ' with id '.$this->argument('page') : ''));

            return;
        }
        foreach ($pages as $page) {
            $this->line("  <comment>{$page->name}</comment> (page {$page->platform_account_id}, account #{$page->id}, user #{$page->user_id})");
            $channel = MessageChannel::where('platform', 'facebook')->where('external_id', $page->platform_account_id)->first();
            if (! $channel) {
                $this->bad('no Inbox channel for this Page - messages are dropped (reconnect the Page from Connections)');
            } elseif ($channel->social_account_id !== $page->id) {
                $this->bad("Inbox channel #{$channel->id} points at account #{$channel->social_account_id}, not this one - messages go there");
            } else {
                $this->ok("Inbox channel #{$channel->id}");
            }
            $token = FacebookMessengerService::pageToken($page);
            $token ? $this->ok('Page token present'.($page->is_token_valid === false ? ' (marked invalid!)' : '')) : $this->bad('no Page token - reconnect the Page');
            $scopes = $page->scopes ?? null;
            if (is_array($scopes)) {
                in_array('pages_messaging', $scopes, true) ? $this->ok('pages_messaging granted') : $this->bad('pages_messaging NOT granted on this connection');
            }
            if ($token) {
                try {
                    $sub = $meta->pageSubscription($page);
                } catch (\Throwable $e) {
                    $sub = ['error' => 'cannot reach graph.facebook.com ('.class_basename($e).')'];
                }
                if (isset($sub['error'])) {
                    $this->bad('Page subscription check failed: '.$sub['error']);
                } elseif (! in_array('messages', $sub['fields'] ?? [], true)) {
                    $this->bad('Page is not subscribed to "messages" for this app (run: php artisan meta:subscribe-pages --user='.$page->user_id.')');
                } else {
                    $this->ok('Page subscribed: '.implode(',', $sub['fields']));
                }
            }
            $last = $channel ? Message::whereHas('conversation', fn ($q) => $q->where('social_account_id', $channel->social_account_id))->where('direction', 'inbound')->latest('id')->first() : null;
            $this->line('    last inbound message stored: '.($last ? $last->created_at.' ('.$last->created_at->diffForHumans().')' : 'never'));
        }
    }

    private function queue(): void
    {
        $this->newLine();
        $this->info('3. Queue (inbound messages are stored by a queued job)');
        $driver = config('queue.default');
        $this->line("    QUEUE_CONNECTION={$driver}");
        if ($driver === 'sync') {
            $this->ok('sync queue - stored immediately');

            return;
        }
        if ($driver === 'database' && Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->where('payload', 'like', '%ProcessInboundMessage%');
            $count = (clone $pending)->count();
            $oldest = (clone $pending)->min('created_at');
            if ($count && $oldest && now()->timestamp - (int) $oldest > 120) {
                $this->bad("{$count} inbound message job(s) waiting, oldest ".now()->diffInMinutes(\Illuminate\Support\Carbon::createFromTimestamp($oldest))." min - the queue worker is NOT running (php artisan queue:work under supervisor)");
            } else {
                $this->ok("{$count} inbound message job(s) waiting");
            }
        }
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('payload', 'like', '%ProcessInboundMessage%')->where('failed_at', '>=', now()->subDays(7))->latest('failed_at')->first();
            $failed ? $this->bad('inbound message jobs FAILED in the last 7 days, latest: '.mb_substr(strtok((string) $failed->exception, "\n"), 0, 200)) : $this->ok('no failed inbound message jobs in 7 days');
        }
    }

    private function ok(string $text): void
    {
        $this->line('    <info>OK</info>   '.$text);
    }

    private function bad(string $text): void
    {
        $this->problems++;
        $this->line('    <error>FAIL</error> '.$text);
    }
}
