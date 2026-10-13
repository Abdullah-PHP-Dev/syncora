<?php

namespace App\Support\Ads;

use App\Models\SocialAccount;
use App\Services\AdServices\FacebookAdService;
use App\Support\AdAccountSelection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Graph API client for ONE Meta ad account (the seller's selected one,
 * App\Support\AdAccountSelection). Used by the campaign builder
 * (FacebookCampaignBuilder): every call carries the account's own token
 * (server-side only - never sent to the browser), and appsecret_proof is
 * added by the global MetaAppSecretProof HTTP middleware.
 *
 * Errors come back as MetaApiException with Meta's user-facing title and
 * message (error_user_title / error_user_msg) when it provides them.
 */
class MetaAdsGraph
{
    /** Currencies Meta budgets in whole units (offset 1); every other one is minor units (offset 100). */
    private const ZERO_DECIMAL = ['CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND'];

    private ?array $accountInfo = null;

    public function __construct(public readonly SocialAccount $account)
    {
    }

    /** The user's selected Facebook ad account, or null when none is connected. */
    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'facebook');

        return $account && $account->access_token ? new self($account) : null;
    }

    /** "act_123" - the ad account node id. */
    public function actId(): string
    {
        $id = (string) $this->account->platform_account_id;

        return str_starts_with($id, 'act_') ? $id : 'act_' . $id;
    }

    /** Bare numeric account id, as Meta reports it in an object's account_id. */
    public function accountId(): string
    {
        return preg_replace('/^act_/', '', (string) $this->account->platform_account_id);
    }

    /** https://graph.facebook.com/vXX.X/ - the version configured for ads. */
    public function base(): string
    {
        $configured = (string) adminSetting('ads.facebook.base_url');
        preg_match('~^(https://graph\.facebook\.com/v\d+\.\d+)/~', $configured, $m);

        return ($m[1] ?? 'https://graph.facebook.com/v22.0') . '/';
    }

    public function get(string $path, array $params = []): array
    {
        return $this->send('get', $path, $params);
    }

    public function post(string $path, array $params = []): array
    {
        return $this->send('post', $path, $params);
    }

    private function send(string $method, string $path, array $params): array
    {
        // Arrays as JSON, booleans as the literal "true"/"false" Meta documents.
        $params = array_map(fn ($v) => is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v), $params);
        $params['access_token'] = $this->token();

        $url = $this->base() . ltrim($path, '/');
        $request = Http::timeout(60)->acceptJson();
        $response = $method === 'get' ? $request->get($url, $params) : $request->asForm()->post($url, $params);

        if (! $response->successful() || $response->json('error')) {
            throw MetaApiException::fromResponse($response);
        }

        return $response->json() ?? [];
    }

    /** Follows paging.next up to $max items (lists are capped - the UI searches). */
    public function list(string $path, array $params = [], int $max = 200): array
    {
        $items = [];
        $params['limit'] = min($params['limit'] ?? 100, 100);
        $page = $this->get($path, $params);

        while (true) {
            array_push($items, ...($page['data'] ?? []));
            $after = $page['paging']['cursors']['after'] ?? null;
            if (count($items) >= $max || empty($page['paging']['next']) || ! $after) {
                break;
            }
            $page = $this->get($path, $params + ['after' => $after]);
        }

        return array_slice($items, 0, $max);
    }

    /** Ad account details the builder needs (cached for the request). */
    public function info(): array
    {
        return $this->accountInfo ??= $this->get($this->actId(), [
            'fields' => 'id,account_id,name,currency,timezone_name,account_status,disable_reason,min_daily_budget,business',
        ]);
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->info()['currency'] ?? 'USD'));
    }

    /** Multiplier from major units (what people type) to what Meta expects. */
    public function currencyOffset(): int
    {
        return in_array($this->currency(), self::ZERO_DECIMAL, true) ? 1 : 100;
    }

    public function toMinor(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * $this->currencyOffset());
    }

    public function toMajor(int|string|null $minor): ?float
    {
        return $minor === null || $minor === '' ? null : round(((int) $minor) / $this->currencyOffset(), 2);
    }

    /**
     * A token that hasn't expired - refreshed through FacebookAdService
     * (same exchange the rest of the ads module uses).
     */
    private function token(): string
    {
        $expires = $this->account->expires_at;
        if ($expires && Carbon::parse($expires)->gt(now())) {
            return (string) $this->account->access_token;
        }

        $refreshed = app(FacebookAdService::class)->refreshToken((string) $this->account->access_token);
        if (! ($refreshed['success'] ?? false)) {
            throw new MetaApiException('Your Facebook ad account connection has expired. Reconnect it in Connections and try again.', 190);
        }
        $this->account->refresh();

        return (string) $this->account->access_token;
    }
}
