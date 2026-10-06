<?php

namespace App\Console\Commands;

use App\Casts\TolerantEncrypted;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Step 0a of the Connection Hub (docs/connection-hub-design.md §1a):
 * brings every social_accounts token to "encrypted with a current key".
 *
 *  - plaintext                      -> encrypted
 *  - encrypted, opens with a key    -> left as-is
 *  - encrypted, no key can open it  -> token cleared, is_token_valid = false,
 *                                      reason in metadata.reauth (the user
 *                                      reconnects; Laravel's docs: data under
 *                                      a lost APP_KEY can't be decrypted)
 *
 * Unlike the 2026_08_26_100007 migration, an undecryptable payload is never
 * mistaken for plaintext and encrypted a second time. Works on raw rows
 * (DB::table) so the model's cast doesn't interfere; idempotent.
 */
class EncryptSocialAccountTokens extends Command
{
    use ConfirmableTrait;

    protected $signature = 'connections:encrypt-tokens
                            {--dry-run : Report what would change without writing anything}
                            {--force : Run in production without the confirmation prompt}';

    protected $description = 'Encrypt plaintext OAuth tokens in social_accounts and flag undecryptable ones for reconnect';

    private const COLUMNS = ['access_token', 'refresh_token', 'asset_token', 'user_token'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $counts = [];
        foreach (self::COLUMNS as $column) {
            $counts[$column] = ['null' => 0, 'plaintext' => 0, 'encrypted_ok' => 0, 'undecryptable' => 0];
        }
        $broken = [];

        DB::table('social_accounts')
            ->select(['id', 'platform', 'account_type', 'metadata', ...self::COLUMNS])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$counts, &$broken, $dryRun) {
                foreach ($rows as $row) {
                    $update = [];
                    $undecryptable = [];

                    foreach (self::COLUMNS as $column) {
                        $state = $this->classify($row->$column);
                        $counts[$column][$state]++;

                        if ($state === 'plaintext') {
                            $update[$column] = Crypt::encryptString($row->$column);
                        } elseif ($state === 'undecryptable') {
                            $update[$column] = null;
                            $undecryptable[] = $column;
                        }
                    }

                    if ($undecryptable) {
                        $broken[] = [$row->id, $row->platform, $row->account_type ?? '-', implode(', ', $undecryptable)];
                        $update['is_token_valid'] = false;
                        $update['metadata'] = $this->withReauthReason($row->metadata, $undecryptable);
                    }

                    if ($update && ! $dryRun) {
                        DB::table('social_accounts')->where('id', $row->id)->update($update);
                    }
                }
            });

        $this->line($dryRun ? '<comment>DRY RUN - nothing was written.</comment>' : '<info>Done.</info>');
        $this->table(
            ['column', 'null', 'plaintext → encrypt', 'encrypted (ok)', 'undecryptable → clear'],
            collect($counts)->map(fn ($c, $column) => [$column, $c['null'], $c['plaintext'], $c['encrypted_ok'], $c['undecryptable']])->values()->all()
        );

        if ($broken) {
            $this->warn(count($broken).' account(s) hold a token no current/previous APP_KEY can decrypt - they will need to reconnect:');
            $this->table(['id', 'platform', 'type', 'columns'], $broken);
        }

        return self::SUCCESS;
    }

    /**
     * null | plaintext | encrypted_ok | undecryptable
     */
    private function classify(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'null';
        }

        if (! TolerantEncrypted::isEncryptedPayload($value)) {
            return 'plaintext';
        }

        try {
            Crypt::decryptString($value);

            return 'encrypted_ok';
        } catch (\Throwable $e) {
            return 'undecryptable';
        }
    }

    private function withReauthReason(?string $metadata, array $columns): string
    {
        $meta = json_decode($metadata ?? '', true) ?: [];

        $meta['reauth'] = [
            'reason' => 'token_undecryptable',
            'detail' => 'Encrypted with an APP_KEY that is no longer configured (not in APP_KEY or APP_PREVIOUS_KEYS); cleared by connections:encrypt-tokens. The user must reconnect.',
            'columns' => $columns,
            'marked_at' => now()->toIso8601String(),
        ];

        return json_encode($meta);
    }
}
