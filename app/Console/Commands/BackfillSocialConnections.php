<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates social_connections for existing social_accounts and links them
 * (docs/connection-hub-design.md §9) - no user has to reconnect.
 *
 * Meta only for now; every other platform is backfilled in the commit that
 * adds its driver. Grouping:
 *  - every facebook row + Page-linked instagram rows (token_type = page)
 *      -> one `meta.login` connection per user, holding the newest Meta
 *         user token found (ad rows' access_token / page rows' user_token)
 *  - instagram rows from Instagram Login -> `meta.instagram_login`, per account
 *  - whatsapp rows                        -> `meta.whatsapp`, per number
 *
 * Granted scopes are copied when known; otherwise capabilities fall back to
 * the legacy has_*_permission flags until the validation pass fills scopes.
 * Only rows with no social_connection_id are touched, so it is idempotent.
 */
class BackfillSocialConnections extends Command
{
    use ConfirmableTrait;

    protected $signature = 'connections:backfill
                            {--dry-run : Report what would be created/linked without writing anything}
                            {--force : Run in production without the confirmation prompt}';

    protected $description = 'Create social_connections for existing social accounts (Meta) and link them';

    private array $report = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $rows = SocialAccount::query()
            ->whereNull('social_connection_id')
            ->whereIn('platform', ['facebook', 'instagram', 'whatsapp'])
            ->orderBy('id')
            ->get();

        foreach ($rows->groupBy('user_id') as $userId => $userRows) {
            $login = $userRows->filter(fn ($a) => $a->platform === 'facebook'
                || ($a->platform === 'instagram' && $a->token_type === 'page'));

            if ($login->isNotEmpty()) {
                $this->metaLogin((int) $userId, $login, $dryRun);
            }

            foreach ($userRows as $account) {
                if ($account->platform === 'instagram' && $account->token_type !== 'page') {
                    $this->single('meta.instagram_login', 'posts.instagram', $account, $dryRun);
                } elseif ($account->platform === 'whatsapp') {
                    $this->single('meta.whatsapp', 'posts.facebook', $account, $dryRun);
                }
            }
        }

        $this->line($dryRun ? '<comment>DRY RUN - nothing was written.</comment>' : '<info>Done.</info>');
        $this->table(['user', 'step', 'assets', 'token from', 'status', 'capabilities'], $this->report);

        if (! $this->report) {
            $this->line('Nothing to backfill.');
        }

        return self::SUCCESS;
    }

    private function metaLogin(int $userId, Collection $accounts, bool $dryRun): void
    {
        // Newest Meta *user* token: ad rows carry it as access_token, page
        // rows (since step 0c) as user_token.
        $source = $accounts
            ->map(fn ($a) => ['account' => $a, 'token' => $a->token_type === 'page' ? $a->user_token : $a->access_token])
            ->filter(fn ($c) => $c['token'])
            ->sortByDesc(fn ($c) => $c['account']->updated_at)
            ->first();

        $token = $source['token'] ?? null;
        $expiresAt = $source['account']->expires_at ?? null;
        $scopes = $source['account']->scopes ?? null;

        $attributes = [
            'provider_app' => 'posts.facebook',
            'access_token' => $token,
            'expires_at' => $expiresAt,
            'granted_scopes' => $scopes,
            'capabilities' => $this->capabilities($scopes, $accounts),
            // Meta issues no refresh tokens: an expired user token needs a reconnect.
            'status' => SocialConnection::statusFor($token, $expiresAt, false),
        ];

        $tokenFrom = $source
            ? '#' . $source['account']->id . ' ' . ($source['account']->token_type === 'page' ? 'user_token' : 'access_token')
            : 'none';

        $this->persist($userId, 'meta.login', null, $attributes, $accounts, $tokenFrom, $dryRun);
    }

    private function single(string $step, string $app, SocialAccount $account, bool $dryRun): void
    {
        $token = $account->access_token;

        $attributes = [
            'provider_app' => $app,
            'access_token' => $token,
            'refresh_token' => $account->refresh_token,
            'expires_at' => $account->expires_at,
            'granted_scopes' => $account->scopes,
            'capabilities' => $this->capabilities($account->scopes, collect([$account])),
            'status' => SocialConnection::statusFor($token, $account->expires_at, (bool) $account->refresh_token),
        ];

        $this->persist((int) $account->user_id, $step, $account->platform_account_id, $attributes, collect([$account]), $token ? "#{$account->id} access_token" : 'none', $dryRun);
    }

    private function persist(int $userId, string $step, ?string $providerAccountId, array $attributes, Collection $accounts, string $tokenFrom, bool $dryRun): void
    {
        $this->report[] = [$userId, $step, $accounts->pluck('id')->map(fn ($id) => "#{$id}")->implode(' '), $tokenFrom, $attributes['status'], implode(', ', $attributes['capabilities']) ?: '-'];

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($userId, $step, $providerAccountId, $attributes, $accounts) {
            $connection = SocialConnection::firstOrNew([
                'user_id' => $userId,
                'platform' => 'meta',
                'step' => $step,
                'provider_account_id' => $providerAccountId,
            ]);

            // A connection created by a real connect is newer than anything
            // here - only fill it when backfill created it.
            if (! $connection->exists) {
                $connection->fill($attributes + ['last_checked_at' => null])->save();
            }

            // Query builder, not save(): SocialAccount's model hooks write
            // stat detail tables, which linking must not touch.
            DB::table('social_accounts')
                ->whereIn('id', $accounts->pluck('id'))
                ->whereNull('social_connection_id')
                ->update(['social_connection_id' => $connection->id]);
        });
    }

    /** From granted scopes when known, else the legacy permission flags. */
    private function capabilities(?array $scopes, Collection $accounts): array
    {
        if ($scopes) {
            return SocialConnection::capabilitiesFrom($scopes, config('connections.capabilities.meta'));
        }

        return array_values(array_filter([
            $accounts->contains('has_posting_permission', true) ? 'posting' : null,
            $accounts->contains('has_messaging_permission', true) ? 'messaging' : null,
            $accounts->contains('has_ads_permission', true) ? 'ads' : null,
        ]));
    }
}
