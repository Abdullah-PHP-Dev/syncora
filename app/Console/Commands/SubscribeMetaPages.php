<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\PostServices\MetaPostService;
use Illuminate\Console\Command;

/**
 * Re-subscribes every connected Facebook Page to this app's webhook with
 * the full field list (MetaPostService::PAGE_WEBHOOK_FIELDS). Pages
 * connected before the posts and Messenger subscriptions were combined
 * ended up with only one side's fields (each /subscribed_apps call
 * replaces the list), so comments, likes and shares or DMs never arrived
 * for them. Safe to run repeatedly.
 */
class SubscribeMetaPages extends Command
{
    protected $signature = 'meta:subscribe-pages
                            {--user= : Only this user\'s Pages}
                            {--dry-run : List the Pages without calling Facebook}
                            {--check : Only show which fields each Page is subscribed to now}';

    protected $description = 'Subscribe connected Facebook Pages to the app webhook (feed + Messenger fields)';

    public function handle(MetaPostService $meta): int
    {
        $pages = SocialAccount::where('platform', 'facebook')
            ->where('account_type', 'page')
            ->when($this->option('user'), fn ($q, $user) => $q->where('user_id', $user))
            ->orderBy('id')
            ->get();

        if ($pages->isEmpty()) {
            $this->info('No connected Facebook Pages.');

            return self::SUCCESS;
        }

        $this->line('Fields: ' . MetaPostService::PAGE_WEBHOOK_FIELDS);
        $rows = [];
        $failed = 0;

        foreach ($pages as $page) {
            if (! $page->access_token) {
                $result = 'skipped - no readable token (reconnect the Page)';
                $failed++;
            } elseif ($this->option('check')) {
                $sub = $meta->pageSubscription($page);
                $missing = array_diff(explode(',', MetaPostService::PAGE_WEBHOOK_FIELDS), $sub['fields'] ?? []);
                $result = isset($sub['error'])
                    ? 'ERROR - ' . $sub['error']
                    : (empty($sub['fields']) ? 'NOT subscribed to this app' : implode(',', $sub['fields']) . ($missing ? '  | missing: ' . implode(',', $missing) : '  | OK'));
                $failed += (isset($sub['error']) || $missing) ? 1 : 0;
            } elseif ($this->option('dry-run')) {
                $result = 'would subscribe';
            } elseif ($meta->subscribeToWebhooks($page)) {
                $result = 'subscribed';
            } else {
                $result = 'FAILED - see laravel.log';
                $failed++;
            }

            $rows[] = [$page->id, $page->user_id, $page->name, $page->platform_account_id, $result];
        }

        $this->table(['Account', 'User', 'Page', 'Page id', 'Result'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
