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
 * Platforms with a Hub driver (Meta, Google); the rest are backfilled in
 * the commit that adds their driver. Grouping:
 *  - every facebook row + Page-linked instagram rows (token_type = page)
 *      -> one `meta.login` connection per user, holding the newest Meta
 *         user token found (ad rows' access_token / page rows' user_token)
 *  - instagram rows from Instagram Login -> `meta.instagram_login`, per account
 *  - whatsapp rows                        -> `meta.whatsapp`, per number
 *  - youtube rows + google (Ads) rows sharing their refresh token
 *      -> one `google.oauth` connection per user (posts.google)
 *  - other google (Ads) rows -> one `google.ads_legacy` per user (ads.google,
 *      the Ads module's own client - kept so their refreshes keep working)
 *  - x posting / DM rows -> `x.oauth2` per account (posts.x)
 *  - x Ads rows sharing a token -> one `x.ads` (ads.x); the plaintext
 *      metadata.legacy_token_secret moves into the encrypted token_secret
 *      and is removed from metadata (design doc §6b)
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

    protected $description = 'Create social_connections for existing social accounts (Meta, Google, X) and link them';

    private array $report = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $rows = SocialAccount::query()
            ->whereNull('social_connection_id')
            ->whereIn('platform', ['facebook', 'instagram', 'whatsapp', 'youtube', 'google', 'x'])
            ->orderBy('id')
            ->get();

        foreach ($rows->groupBy('user_id') as $userId => $userRows) {
            $login = $userRows->filter(fn ($a) => $a->platform === 'facebook'
                || ($a->platform === 'instagram' && $a->token_type === 'page'));

            if ($login->isNotEmpty()) {
                $this->metaLogin((int) $userId, $login, $dryRun);
            }

            $google = $userRows->whereIn('platform', ['youtube', 'google']);
            if ($google->isNotEmpty()) {
                $this->google((int) $userId, $google, $dryRun);
            }

            $x = $userRows->where('platform', 'x');
            if ($x->isNotEmpty()) {
                $this->x((int) $userId, $x, $dryRun);
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

        if ($this->report) {
            $this->table(['user', 'step', 'assets', 'token from', 'status', 'capabilities'], $this->report);
        } else {
            $this->line('Nothing to backfill: every Meta, Google and X account is already linked to a connection.');
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
            'capabilities' => $this->capabilities('meta', $scopes, $accounts),
            // Meta issues no refresh tokens: an expired user token needs a reconnect.
            'status' => SocialConnection::statusFor($token, $expiresAt, false),
        ];

        $tokenFrom = $source
            ? '#' . $source['account']->id . ' ' . ($source['account']->token_type === 'page' ? 'user_token' : 'access_token')
            : 'none';

        $this->persist($userId, 'meta', 'meta.login', null, $attributes, $accounts, $tokenFrom, $dryRun);
    }

    private function google(int $userId, Collection $accounts, bool $dryRun): void
    {
        $youtube = $accounts->where('platform', 'youtube');
        $unifiedTokens = $youtube->pluck('refresh_token')->filter()->unique()->all();

        // Ads rows minted by the same unified consent share the channel's refresh token.
        [$unifiedAds, $legacyAds] = $accounts->where('platform', 'google')
            ->partition(fn ($a) => $a->refresh_token && in_array($a->refresh_token, $unifiedTokens, true));

        $groups = [
            'google.oauth' => ['app' => 'posts.google', 'accounts' => $youtube->merge($unifiedAds)],
            'google.ads_legacy' => ['app' => 'ads.google', 'accounts' => $legacyAds],
        ];

        foreach ($groups as $step => $group) {
            if ($group['accounts']->isEmpty()) {
                continue;
            }

            // Newest row with a refresh token, else newest with any token.
            $source = $group['accounts']->sortByDesc(fn ($a) => [(bool) $a->refresh_token, $a->updated_at])->first();

            $attributes = [
                'provider_app' => $group['app'],
                'access_token' => $source->access_token,
                'refresh_token' => $source->refresh_token,
                'expires_at' => $source->expires_at,
                'granted_scopes' => $source->scopes,
                'capabilities' => $this->capabilities('google', $source->scopes, $group['accounts']),
                'status' => SocialConnection::statusFor($source->access_token, $source->expires_at, (bool) $source->refresh_token),
            ];

            $tokenFrom = '#' . $source->id . ($source->refresh_token ? ' refresh_token' : ' access_token');

            $this->persist($userId, 'google', $step, null, $attributes, $group['accounts'], $tokenFrom, $dryRun);
        }
    }

    private function x(int $userId, Collection $accounts, bool $dryRun): void
    {
        [$ads, $oauth2] = $accounts->partition(fn ($a) => $a->has_ads_permission
            && (isset($a->metadata['legacy_token_secret']) || (! $a->has_posting_permission && ! $a->has_messaging_permission)));

        // One OAuth 1.0a consent mints one token for every Ads account it returned.
        foreach ($ads->groupBy(fn ($a) => (string) $a->access_token) as $group) {
            $source = $group->first();
            $secret = $group->map(fn ($a) => $a->metadata['legacy_token_secret'] ?? null)->filter()->first();

            $attributes = [
                'provider_app' => 'ads.x',
                'access_token' => $source->access_token,
                'token_secret' => $secret,
                'granted_scopes' => ['oauth1'],
                'capabilities' => ['ads'],
                // OAuth 1.0a tokens never expire; without a secret it can't sign.
                'status' => $source->access_token && $secret ? SocialConnection::ACTIVE : SocialConnection::NEEDS_REAUTH,
            ];

            $userIdOnX = $source->metadata['x_user_id'] ?? $source->metadata['profile_id'] ?? null;
            $this->persist($userId, 'x', 'x.ads', $userIdOnX ? (string) $userIdOnX : null, $attributes, $group, '#' . $source->id . ($secret ? ' token + secret' : ' token, no secret'), $dryRun);

            if (! $dryRun && $secret) {
                $this->forgetPlaintextSecret($group);
            }
        }

        foreach ($oauth2 as $account) {
            $attributes = [
                'provider_app' => 'posts.x',
                'access_token' => $account->access_token,
                'refresh_token' => $account->refresh_token,
                'expires_at' => $account->expires_at,
                'granted_scopes' => $account->scopes,
                'capabilities' => $this->capabilities('x', $account->scopes, collect([$account])),
                'status' => SocialConnection::statusFor($account->access_token, $account->expires_at, (bool) $account->refresh_token),
            ];

            $this->persist($userId, 'x', 'x.oauth2', $account->platform_account_id, $attributes, collect([$account]), "#{$account->id} " . ($account->refresh_token ? 'refresh_token' : 'access_token'), $dryRun);
        }
    }

    /** The secret now lives encrypted on the connection - drop the plaintext copy. */
    private function forgetPlaintextSecret(Collection $accounts): void
    {
        foreach ($accounts as $account) {
            $metadata = $account->metadata ?? [];
            unset($metadata['legacy_token_secret']);

            DB::table('social_accounts')->where('id', $account->id)->update(['metadata' => json_encode($metadata)]);
        }
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
            'capabilities' => $this->capabilities('meta', $account->scopes, collect([$account])),
            'status' => SocialConnection::statusFor($token, $account->expires_at, (bool) $account->refresh_token),
        ];

        $this->persist((int) $account->user_id, 'meta', $step, $account->platform_account_id, $attributes, collect([$account]), $token ? "#{$account->id} access_token" : 'none', $dryRun);
    }

    private function persist(int $userId, string $platform, string $step, ?string $providerAccountId, array $attributes, Collection $accounts, string $tokenFrom, bool $dryRun): void
    {
        $this->report[] = [$userId, $step, $accounts->pluck('id')->map(fn ($id) => "#{$id}")->implode(' '), $tokenFrom, $attributes['status'], implode(', ', $attributes['capabilities']) ?: '-'];

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($userId, $platform, $step, $providerAccountId, $attributes, $accounts) {
            $connection = SocialConnection::firstOrNew([
                'user_id' => $userId,
                'platform' => $platform,
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
    private function capabilities(string $platform, ?array $scopes, Collection $accounts): array
    {
        if ($scopes) {
            return SocialConnection::capabilitiesFrom($scopes, config("connections.capabilities.{$platform}"));
        }

        return array_values(array_filter([
            $accounts->contains('has_posting_permission', true) ? 'posting' : null,
            $accounts->contains('has_messaging_permission', true) ? 'messaging' : null,
            $accounts->contains('has_ads_permission', true) ? 'ads' : null,
        ]));
    }
}
