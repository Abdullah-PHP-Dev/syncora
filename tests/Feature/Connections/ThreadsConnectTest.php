<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\ThreadsDriver;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Threads from the Hub: Connect -> threads.net/oauth/authorize -> callback, over HTTP. */
class ThreadsConnectTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        $this->createConnectionTables();
        $this->createSettingsTable();
        // The keys production stores them under; no posts.threads.* rows at all.
        Settings::set('threads.facebook.client_id', '1312337257646594');
        Settings::set('threads.facebook.client_key', 'th-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    /** Hub Connect, following the internal hop to Threads' authorize URL. */
    private function connect(): array
    {
        $hop = $this->get(route('admin.connections.connect', ['threads', ThreadsDriver::LOGIN]))->assertRedirect();
        $authorize = $this->get($hop->headers->get('Location'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($authorize, PHP_URL_QUERY), $query);

        return [$authorize, $query];
    }

    private function returnFromThreads(array $query): TestResponse
    {
        return $this->get(route('admin.post-accounts.threads.callback', $query));
    }

    private function fakeThreads(): void
    {
        Http::fake([
            'graph.threads.net/oauth/access_token' => Http::response(['access_token' => 'short-token', 'user_id' => 25000000000000001]),
            'graph.threads.net/access_token*' => Http::response(['access_token' => 'long-token', 'token_type' => 'bearer', 'expires_in' => 5183944]),
            'graph.threads.net/v1.0/*' => Http::response(['id' => '25000000000000001', 'username' => 'socialeaz', 'threads_profile_picture_url' => 'https://scontent.cdninstagram.com/me.jpg']),
        ]);
    }

    public function test_connect_redirects_to_threads_with_the_registered_callback(): void
    {
        [$authorize, $q] = $this->connect();

        $this->assertStringStartsWith('https://threads.net/oauth/authorize?', $authorize);
        $this->assertSame(['1312337257646594', url('/post-accounts/threads/callback'), 'code', session('threads_oauth_state')], [$q['client_id'], $q['redirect_uri'], $q['response_type'], $q['state']]);
        $this->assertStringNotContainsString('/en/', $q['redirect_uri']);
    }

    public function test_callback_connects_the_profile_and_returns_to_the_hub(): void
    {
        $this->fakeThreads();
        [, $q] = $this->connect();

        $this->returnFromThreads(['code' => 'auth-code', 'state' => $q['state']])
            ->assertRedirect(route('admin.connections.index'))
            ->assertSessionHas('success', 'Threads account connected.');

        $account = SocialAccount::where('platform', 'threads')->sole();
        $this->assertSame(['25000000000000001', 'socialeaz', 'long-token', true], [$account->platform_account_id, $account->username, $account->access_token, (bool) $account->has_posting_permission]);
        $this->assertTrue($account->expires_at->gt(now()->addDays(59)));
        $connection = SocialConnection::where('step', ThreadsDriver::LOGIN)->sole();
        $this->assertSame([SocialConnection::ACTIVE, '25000000000000001', $connection->id], [$connection->status, $connection->provider_account_id, $account->social_connection_id]);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://graph.threads.net/oauth/access_token' && $r['code'] === 'auth-code' && $r['client_id'] === '1312337257646594' && $r['client_secret'] === 'th-secret' && $r['redirect_uri'] === url('/post-accounts/threads/callback'));
        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://graph.threads.net/access_token?') && str_contains($r->url(), 'grant_type=th_exchange_token') && str_contains($r->url(), 'client_secret=th-secret'));
    }

    public function test_credentials_come_from_the_threads_facebook_keys_before_the_old_ones(): void
    {
        Settings::set('posts.threads.client_id', 'old-app');
        Settings::set('posts.threads.client_secret', 'old-secret');
        $this->assertSame(['1312337257646594', 'th-secret'], [ThreadsDriver::clientId(), ThreadsDriver::clientSecret()]);
        $this->assertTrue(collect(app(ThreadsDriver::class)->steps())->first()['available']);

        // Only the older keys: still works.
        Settings::set('threads.facebook.client_id', '');
        Settings::set('threads.facebook.client_key', '');
        $this->assertSame(['old-app', 'old-secret'], [ThreadsDriver::clientId(), ThreadsDriver::clientSecret()]);

        // Neither: the step says "Not available" instead of sending an empty client_id.
        Settings::set('posts.threads.client_id', '');
        $this->assertFalse(collect(app(ThreadsDriver::class)->steps())->first()['available']);
    }

    public function test_the_drawer_offers_only_what_the_threads_sign_in_grants(): void
    {
        $this->fakeThreads();
        [, $q] = $this->connect();
        $this->returnFromThreads(['code' => 'auth-code', 'state' => $q['state']]);

        $card = app(\App\Services\Connections\HubPresenter::class)->card($this->user->id, 'threads');
        $connection = $card['connections'][0];

        // No "Insights - Upgrade" link the same sign-in could never satisfy.
        $this->assertSame([['posting'], ['posting'], ['posting']], [$card['presentation']['benefits'], $connection['offered_capabilities'], $connection['capabilities']]);
        $this->assertSame(['posting'], $connection['assets']['threads_profile'][0]['enabled_capabilities']);
    }

    public function test_reconnecting_updates_instead_of_duplicating(): void
    {
        $this->fakeThreads();
        foreach ([1, 2] as $_) {
            [, $q] = $this->connect();
            $this->returnFromThreads(['code' => 'auth-code', 'state' => $q['state']])->assertSessionHas('success');
        }

        $this->assertSame(1, SocialAccount::where('platform', 'threads')->count());
        $this->assertSame(1, SocialConnection::where('platform', 'threads')->count());
    }

    public function test_a_declined_consent_or_forged_state_connects_nothing(): void
    {
        Http::fake();
        [, $q] = $this->connect();

        $this->returnFromThreads(['error' => 'access_denied', 'error_reason' => 'user_denied', 'state' => $q['state']])
            ->assertRedirect(route('admin.connections.index'))->assertSessionHas('error');
        [, $q] = $this->connect();
        $this->returnFromThreads(['code' => 'auth-code', 'state' => 'forged'])->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_a_refused_code_exchange_shows_threads_reason(): void
    {
        Http::fake(['graph.threads.net/oauth/access_token' => Http::response(['error' => ['message' => 'Error validating verification code. Please make sure your redirect_uri is identical to the one you used in the OAuth dialog request', 'type' => 'OAuthException', 'code' => 100]], 400)]);
        [, $q] = $this->connect();

        $this->returnFromThreads(['code' => 'auth-code', 'state' => $q['state']])
            ->assertRedirect(route('admin.connections.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'redirect_uri is identical'));
        $this->assertSame(0, SocialAccount::count());
    }
}
