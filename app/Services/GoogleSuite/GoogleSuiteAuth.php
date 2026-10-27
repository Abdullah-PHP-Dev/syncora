<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Models\GoogleSyncEvent;
use App\Models\SocialConnection;
use App\Services\Connections\ConnectionRecorder;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Support\Connections\GoogleClient;
use App\Support\Connections\GrantedScopes;
use App\Support\Google\GoogleApiException;
use App\Support\Google\GoogleSuiteScopes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

/**
 * The Google Suite consent (GoogleDriver::ANALYTICS). Uses the same OAuth
 * client and the same registered callback URL as the YouTube/Ads consent -
 * a session flag routes the callback here - but asks only for read-only
 * reporting scopes, so connecting Analytics never widens other access.
 */
class GoogleSuiteAuth
{
    /** Session key SocialAuthService::callback() checks before its own handling. */
    public const FLOW_KEY = 'google_oauth_flow';
    public const FLOW = 'suite';

    /** Same key SocialAuthService validates, so one callback route serves both. */
    private const STATE_KEY = 'social_oauth_state_google';

    public function __construct(private GoogleSuiteDiscovery $discovery)
    {
    }

    public static function configured(): bool
    {
        $client = GoogleClient::credentials();

        return (bool) ($client['client_id'] && $client['client_secret']);
    }

    /** The workspace's live (or broken, but not user-disconnected) Suite consent. */
    public static function connectionFor(int $userId): ?SocialConnection
    {
        return SocialConnection::where(['user_id' => $userId, 'platform' => 'google', 'step' => GoogleDriver::ANALYTICS])
            ->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', SocialConnection::DISCONNECTED_BY_USER))
            ->latest('updated_at')
            ->first();
    }

    public function redirect(): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('admin.google.status')->with('google_oauth', ['status' => 'failed', 'code' => GoogleApiException::NOT_CONFIGURED]);
        }

        $state = Str::random(40);
        session([self::STATE_KEY => $state, self::FLOW_KEY => self::FLOW]);

        return Redirect::away('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => GoogleClient::credentials()['client_id'],
            'redirect_uri' => oauthCallbackUrl('admin.social-accounts.callback', ['platform' => 'google']),
            'response_type' => 'code',
            'access_type' => 'offline',
            // Always return a refresh token, even for an account that consented before.
            'prompt' => 'consent select_account',
            'scope' => implode(' ', GoogleSuiteScopes::requested()),
            'state' => $state,
        ]));
    }

    public function callback(?string $code, ?string $state, ?string $providerError): RedirectResponse
    {
        $expected = session()->pull(self::STATE_KEY);

        if ($providerError) {
            // access_denied = the user pressed Cancel on Google's screen.
            $status = str_contains($providerError, 'access_denied') ? 'cancelled' : 'denied';

            return $this->result($status, $status === 'denied' ? 'consent_error' : null);
        }

        if (! $code || ! $state || ! $expected || ! hash_equals($expected, $state)) {
            Log::warning('Google Suite OAuth callback rejected.', ['has_code' => (bool) $code, 'state_matches' => false]);

            return $this->result('failed', 'state_mismatch');
        }

        $client = GoogleClient::credentials();

        try {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'redirect_uri' => oauthCallbackUrl('admin.social-accounts.callback', ['platform' => 'google']),
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException $e) {
            return $this->result('failed', GoogleApiException::PROVIDER_OUTAGE);
        }

        if (! $response->successful() || ! $response->json('access_token')) {
            Log::warning('Google Suite token exchange failed.', ['status' => $response->status(), 'error' => $response->json('error')]);

            return $this->result('failed', 'token_exchange');
        }

        $token = $response->json();
        $userId = (int) Auth::id();
        $granted = GrantedScopes::fromTokenResponse($token) ?? [];
        // The id_token came straight from Google's token endpoint over TLS,
        // so its claims can be read without re-verifying the signature
        // (Google OpenID Connect docs, "Obtain user information from the ID token").
        $identity = $this->idTokenClaims($token['id_token'] ?? null);

        $connection = ConnectionRecorder::record($userId, 'google', GoogleDriver::ANALYTICS, $identity['sub'] ?? null, [
            'provider_app' => GoogleClient::current(),
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'granted_scopes' => $granted,
        ]);

        GoogleSuiteSetting::store($userId, ['account' => array_filter([
            'email' => $identity['email'] ?? null,
            'connected_at' => now()->toIso8601String(),
        ])]);

        $missing = GoogleSuiteScopes::missing($granted);
        GoogleSyncEvent::log($userId, 'google', 'connected', $missing ? 'warning' : 'ok', $identity['email'] ?? null);

        $found = $this->discovery->discover($connection);

        return $this->result($missing === [] ? 'success' : 'partial', null, [
            'missing' => array_values(array_keys(array_intersect(GoogleSuiteScopes::BY_SERVICE, $missing))),
            'found' => $found,
        ]);
    }

    /**
     * Local clean-up when the Suite consent is disconnected: forget the
     * discovered properties, cached reports and the linked account identity.
     * The sync log keeps a "disconnected" line, nothing else.
     */
    public function forget(SocialConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            GoogleProperty::ownedBy($connection->user_id)->delete();
            $preferences = GoogleSuiteSetting::for($connection->user_id);
            unset($preferences['account']);
            GoogleSuiteSetting::updateOrCreate(['user_id' => $connection->user_id], ['preferences' => $preferences]);
        });

        GoogleReportCache::flush($connection->user_id);
        GoogleSyncEvent::log($connection->user_id, 'google', 'disconnected');
    }

    private function result(string $status, ?string $code = null, array $extra = []): RedirectResponse
    {
        return redirect()->route('admin.google.status')->with('google_oauth', ['status' => $status, 'code' => $code] + $extra);
    }

    private function idTokenClaims(?string $idToken): array
    {
        $parts = $idToken ? explode('.', $idToken) : [];

        if (count($parts) !== 3) {
            return [];
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) ? array_intersect_key($claims, array_flip(['sub', 'email'])) : [];
    }
}
