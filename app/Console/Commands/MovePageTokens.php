<?php

namespace App\Console\Commands;

use App\Casts\TolerantEncrypted;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 0c of the Connection Hub (docs/connection-hub-design.md §1c).
 *
 * Facebook Page / Instagram rows (token_type = 'page') used refresh_token
 * for things that aren't refresh tokens - Meta issues none:
 *  - a copy of the page token itself      -> dropped (asset_token keeps it)
 *  - the Meta user token that issued it   -> moved to user_token
 * and the page token is copied to asset_token. access_token is left as-is
 * so every existing reader keeps working until ConnectionService::tokenFor().
 *
 * Values are copied raw (encrypted stays encrypted, plaintext stays
 * plaintext - connections:encrypt-tokens covers both new columns);
 * equality is decided on decrypted values, since two encryptions of the
 * same token never match. Idempotent.
 *
 * Also clears platform_pages.access_token: the Facebook Ads connect wrote
 * Page tokens there in plaintext, but nothing ever reads them (the same
 * tokens live on the social_accounts page rows).
 */
class MovePageTokens extends Command
{
    use ConfirmableTrait;

    protected $signature = 'connections:move-page-tokens
                            {--dry-run : Report what would change without writing anything}
                            {--force : Run in production without the confirmation prompt}';

    protected $description = 'Move Facebook Page / Instagram tokens out of refresh_token into asset_token / user_token';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $rows = [];
        $totals = ['asset_token set' => 0, 'refresh copy dropped' => 0, 'user token moved' => 0, 'unchanged' => 0];

        DB::table('social_accounts')
            ->whereIn('platform', ['facebook', 'instagram'])
            ->where('token_type', 'page')
            ->select(['id', 'platform', 'account_type', 'access_token', 'refresh_token', 'asset_token', 'user_token'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$rows, &$totals, $dryRun) {
                foreach ($chunk as $row) {
                    $update = [];
                    $actions = [];

                    if ($row->asset_token === null && $row->access_token !== null) {
                        $update['asset_token'] = $row->access_token;
                        $actions[] = 'asset_token set';
                    }

                    if ($row->refresh_token !== null) {
                        if ($this->sameToken($row->refresh_token, $row->access_token)) {
                            $actions[] = 'refresh copy dropped';
                        } else {
                            // Never overwrite a user_token recorded by a newer connect.
                            if ($row->user_token === null) {
                                $update['user_token'] = $row->refresh_token;
                            }
                            $actions[] = 'user token moved';
                        }
                        $update['refresh_token'] = null;
                    }

                    if (! $actions) {
                        $totals['unchanged']++;
                        continue;
                    }

                    foreach ($actions as $action) {
                        $totals[$action]++;
                    }
                    $rows[] = [$row->id, $row->platform, $row->account_type ?? '-', implode(', ', $actions)];

                    if (! $dryRun) {
                        DB::table('social_accounts')->where('id', $row->id)->update($update);
                    }
                }
            });

        $this->line($dryRun ? '<comment>DRY RUN - nothing was written.</comment>' : '<info>Done.</info>');
        $this->table(array_keys($totals), [array_values($totals)]);

        if ($rows) {
            $this->table(['id', 'platform', 'type', 'actions'], $rows);
        }

        if (Schema::hasTable('platform_pages')) {
            $unused = DB::table('platform_pages')->whereNotNull('access_token')->count();
            $this->line("platform_pages.access_token (unused) to clear: {$unused}");

            if ($unused && ! $dryRun) {
                DB::table('platform_pages')->whereNotNull('access_token')->update(['access_token' => null]);
            }
        }

        return self::SUCCESS;
    }

    private function sameToken(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return $this->plain($a) === $this->plain($b);
    }

    /** Decrypted value, or the raw value for plaintext / unreadable ciphertext. */
    private function plain(string $value): string
    {
        if (! TolerantEncrypted::isEncryptedPayload($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
