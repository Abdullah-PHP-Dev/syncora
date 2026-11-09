<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\Connections\GoogleServiceStatus;
use App\Services\Connections\HubPresenter;
use App\Services\SocialAuth\SocialAuthService;
use App\Models\GoogleSuiteSetting;
use App\Support\Google\GoogleServices;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/** Google services in the Connection Hub: per-service consent, Google account identity, statuses, per-account revoke. */
class GoogleServicesTest extends TestCase
{
    use CreatesConnectionTables;

    private const YT = 'https://www.googleapis.com/auth/youtube';
    private const YT_UPLOAD = 'https://www.googleapis.com/auth/youtube.upload';
    private const ADS = 'https://www.googleapis.com/auth/adwords';
    private const GBP = 'https://www.googleapis.com/auth/business.manage';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.google.client_id', 'posts-client');
        Settings::set('posts.google.client_secret', 'posts-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return $q;
    }

    /** Follow our own redirects until Google's authorization URL. */
    private function authorizationUrl(string $url): string
    {
        for ($i = 0; $i < 3 && ! str_starts_with($url, 'https://accounts.google.com/'); $i++) {
            $url = $this->get($url)->assertRedirect()->headers->get('Location');
        }

        return $url;
    }

    private function idToken(array $claims): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256']) . '.' . $part($claims) . '.sig';
    }

    private function consent(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::OAUTH, 'provider_app' => 'posts.google',
            'provider_account_id' => 'sub-a', 'provider_account_label' => 'a@brand.com',
            'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour(), 'status' => SocialConnection::ACTIVE,
            'granted_scopes' => [self::YT, self::YT_UPLOAD], 'capabilities' => ['posting'],
        ], $attributes));
    }

    private function service(string $key): array
    {
        return collect(GoogleServiceStatus::forUser($this->user->id))->firstWhere('key', $key);
    }

    // ---- consent requests ---------------------------------------------

    public function test_connecting_one_service_asks_google_only_for_its_scopes(): void
    {
        $q = $this->query($this->authorizationUrl(route('admin.connections.google.connect', ['service' => 'youtube'])));

        $this->assertSame(['openid', 'email', self::YT_UPLOAD, self::YT], explode(' ', $q['scope']));
        $this->assertSame('true', $q['include_granted_scopes']);
        $this->assertSame('consent select_account', $q['prompt']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertNull(session(GoogleServices::REQUEST_KEY)); // read once

        $q = $this->query($this->authorizationUrl(route('admin.connections.google.connect', ['service' => 'business_profile'])));
        $this->assertSame(['openid', 'email', self::GBP], explode(' ', $q['scope']));

        // GA4 goes through the read-only Analytics consent (both reporting scopes, never
        // widened by earlier grants), still on the same callback URL.
        $q = $this->query($this->authorizationUrl(route('admin.connections.google.connect', ['service' => 'analytics'])));
        $this->assertSame(['openid', 'email', 'https://www.googleapis.com/auth/analytics.readonly', 'https://www.googleapis.com/auth/webmasters.readonly'], explode(' ', $q['scope']));
        $this->assertArrayNotHasKey('include_granted_scopes', $q);
        $this->assertNull(session(GoogleServices::REQUEST_KEY));
        $this->assertStringEndsWith('/social-accounts/google/callback', $q['redirect_uri']);
    }

    public function test_a_consent_started_elsewhere_keeps_the_full_verified_set(): void
    {
        $q = $this->query(app(SocialAuthService::class)->redirect('google')->getTargetUrl());

        $this->assertSame(['openid', 'email', self::YT_UPLOAD, self::YT, self::ADS, self::GBP, 'https://www.googleapis.com/auth/analytics.readonly'], explode(' ', $q['scope']));
    }

    public function test_services_without_a_google_sign_in_cannot_be_connected_through_oauth(): void
    {
        foreach (['pagespeed', 'tag_manager', 'maps', 'nope'] as $service) {
            $this->get(route('admin.connections.google.connect', ['service' => $service]))->assertNotFound();
        }

        Settings::set('connections.flags.google.business_profile', '0');
        $this->get(route('admin.connections.google.connect', ['service' => 'business_profile']))->assertNotFound();
    }

    // ---- Google account identity ---------------------------------------

    public function test_each_google_account_gets_its_own_connection(): void
    {
        $token = fn (string $sub, string $email, string $access) => ['access_token' => $access, 'refresh_token' => 'r-' . $access, 'expires_in' => 3599,
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email ' . self::YT_UPLOAD . ' ' . self::YT, 'id_token' => $this->idToken(['sub' => $sub, 'email' => $email])];
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push($token('sub-a', 'a@brand.com', 'token-a'))
                ->push($token('sub-b', 'b@agency.com', 'token-b')),
            'www.googleapis.com/youtube/v3/channels*' => Http::sequence()
                ->push(['items' => [['id' => 'UC-sub-a', 'snippet' => ['title' => 'A']]]])
                ->push(['items' => [['id' => 'UC-sub-b', 'snippet' => ['title' => 'B']]]]),
            '*' => Http::response([], 404),
        ]);
        foreach ([1, 2] as $_) {
            session(['social_oauth_state_google' => 's']);
            app(SocialAuthService::class)->callback('google', 'code', 's');
        }

        $connections = SocialConnection::where('step', GoogleDriver::OAUTH)->orderBy('id')->get();
        $this->assertSame(['sub-a', 'sub-b'], $connections->pluck('provider_account_id')->all());
        $this->assertSame(['a@brand.com', 'b@agency.com'], $connections->pluck('provider_account_label')->all());
        // Account A's tokens and channel stay with account A.
        $this->assertSame('token-a', $connections[0]->access_token);
        $this->assertSame($connections[0]->id, SocialAccount::where('platform_account_id', 'UC-sub-a')->sole()->social_connection_id);
        $this->assertSame($connections[1]->id, SocialAccount::where('platform_account_id', 'UC-sub-b')->sole()->social_connection_id);

        $youtube = $this->service('youtube');
        $this->assertSame('connected', $youtube['status']);
        $this->assertSame(['a@brand.com', 'b@agency.com'], array_column($youtube['accounts'], 'email'));
        $this->assertSame([1, 1], array_column($youtube['accounts'], 'assets'));
    }

    public function test_disconnecting_one_google_account_revokes_it_even_while_another_account_is_live(): void
    {
        $a = $this->consent();
        $this->consent(['provider_account_id' => 'sub-b', 'provider_account_label' => 'b@agency.com', 'refresh_token' => 'r-b']);
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);

        app(GoogleDriver::class)->disconnect($a);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/revoke') && $r['token'] === 'r');
        $this->assertSame(['b@agency.com'], array_column($this->service('youtube')['accounts'], 'email'));
    }

    public function test_the_same_accounts_other_consent_still_blocks_the_revoke(): void
    {
        $a = $this->consent();
        $this->consent(['step' => GoogleDriver::ANALYTICS, 'granted_scopes' => ['https://www.googleapis.com/auth/analytics.readonly']]);
        Http::fake();

        app(GoogleDriver::class)->disconnect($a);

        Http::assertNothingSent(); // revoking would also cut this account's Analytics consent
    }

    // ---- statuses ------------------------------------------------------

    public function test_statuses_follow_what_google_granted_and_the_consents_health(): void
    {
        $this->assertSame('not_connected', $this->service('youtube')['status']);

        $consent = $this->consent();
        $this->assertSame('connected', $this->service('youtube')['status']);
        // Ads wasn't granted on this consent: not connected, not "connected by association".
        $this->assertSame('not_connected', $this->service('google_ads')['status']);

        $consent->update(['status' => SocialConnection::EXPIRED]);
        $this->assertSame('expired', $this->service('youtube')['status']);
        $consent->update(['status' => SocialConnection::NEEDS_REAUTH]);
        $this->assertSame('permission_required', $this->service('youtube')['status']);
        $consent->update(['status' => SocialConnection::ERROR]);
        $this->assertSame('error', $this->service('youtube')['status']);

        // Disconnected by the user: gone, not "error".
        $consent->update(['status' => SocialConnection::REVOKED, 'last_error' => SocialConnection::DISCONNECTED_BY_USER]);
        $this->assertSame('not_connected', $this->service('youtube')['status']);
    }

    public function test_connected_services_say_what_is_still_missing(): void
    {
        $this->consent(['granted_scopes' => [self::GBP]]);

        $gbp = $this->service('business_profile');
        $this->assertSame(['connected', 'no_assets'], [$gbp['status'], $gbp['note']]);
        $this->assertSame(['youtube', 'google_ads'], $gbp['shares_with']);
    }

    public function test_earlier_ads_only_connections_count_for_google_ads(): void
    {
        $this->consent(['step' => GoogleDriver::ADS_LEGACY, 'provider_app' => 'ads.google', 'granted_scopes' => null, 'provider_account_id' => null, 'provider_account_label' => null]);

        $this->assertSame('connected', $this->service('google_ads')['status']);
    }

    public function test_services_without_oauth_report_their_own_state(): void
    {
        $this->assertSame(['not_connected', 'needs_key'], [$this->service('pagespeed')['status'], $this->service('pagespeed')['reason']]);
        GoogleSuiteSetting::setPageSpeedKey($this->user->id, 'AIzaSyA1234567890abcdefghijklmnopqrstuv');
        $this->assertSame(['connected', 'own_key'], [$this->service('pagespeed')['status'], $this->service('pagespeed')['reason']]);

        $this->assertSame(['unavailable', 'planned'], [$this->service('tag_manager')['status'], $this->service('tag_manager')['reason']]);
        $this->assertSame(['unavailable', 'planned'], [$this->service('maps')['status'], $this->service('maps')['reason']]);

        Settings::set('connections.flags.google.business_profile', '0');
        $this->assertSame(['unavailable', 'approval'], [$this->service('business_profile')['status'], $this->service('business_profile')['reason']]);
    }

    public function test_another_workspaces_google_accounts_never_show(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $this->consent(['user_id' => $other->id]);

        $this->assertSame('not_connected', $this->service('youtube')['status']);
        $this->assertSame([], $this->service('youtube')['accounts']);
    }

    public function test_the_hub_google_card_carries_the_services_and_account_emails(): void
    {
        $this->consent();

        $card = app(HubPresenter::class)->card($this->user->id, 'google');

        $this->assertSame(['youtube', 'google_ads', 'business_profile', 'analytics', 'search_console', 'pagespeed', 'tag_manager', 'maps'], array_column($card['google_services'], 'key'));
        $this->assertSame('a@brand.com', $card['connections'][0]['account_label']);
        $this->assertNull(app(HubPresenter::class)->card($this->user->id, 'meta')['google_services']);
        // No token reaches the page.
        $this->assertStringNotContainsString('"r"', json_encode($card['google_services']));
    }
}
