<?php

namespace Tests\Feature\Navigation;

use App\Http\Middleware\ReturnToOrigin;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Return-to-origin for OAuth connect flows: start -> provider -> callback
 * returns the user to the page they started on, safely.
 */
class ReturnToOriginTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x')]);
    }

    public function test_sanitize_allows_only_internal_app_pages(): void
    {
        $host = 'labs.socialeaz.com';

        $this->assertSame('/en/ads/facebook/campaigns/create', ReturnToOrigin::sanitize('/en/ads/facebook/campaigns/create', $host));
        $this->assertSame('/ar/posts/listing?platform=x', ReturnToOrigin::sanitize('https://labs.socialeaz.com/ar/posts/listing?platform=x&connected=5', $host));
        $this->assertSame('/en/chats/dashboard', ReturnToOrigin::sanitize('/en/chats/dashboard', $host));

        foreach ([
            'https://evil.com/en/ads/facebook/campaigns',   // other host
            '//evil.com/en/ads',                            // protocol-relative
            '/\\evil.com/ads',                              // backslash trick
            'javascript:alert(1)',
            '/en/admin/users',                              // not an allowed area
            '/en/ads/facebook/redirect',                    // the OAuth route itself (loop)
            '/en/social-accounts/facebook/callback',
            'ads/facebook',                                 // relative without leading slash
            '',
            null,
        ] as $bad) {
            $this->assertNull(ReturnToOrigin::sanitize($bad, $host), (string) $bad);
        }
    }

    private function request(string $routeName, string $uri, array $query = [], array $headers = [], ?string $path = null): Request
    {
        $request = Request::create('https://labs.socialeaz.com/' . ltrim($path ?? $uri, '/'), 'GET', $query, [], [], array_merge(['HTTP_HOST' => 'labs.socialeaz.com'], $headers));
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->user);
        $route = (new Route('GET', $uri, []))->name($routeName);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function startFrom(string $origin): void
    {
        $start = $this->request('admin.ads.redirect', 'ads/{platform}/redirect', [], ['HTTP_REFERER' => 'https://labs.socialeaz.com' . $origin], 'ads/facebook/redirect');
        (new ReturnToOrigin)->handle($start, fn () => redirect()->away('https://www.facebook.com/dialog/oauth'));
    }

    public function test_success_returns_to_the_task_and_preselects_the_new_account(): void
    {
        $this->startFrom('/en/ads/facebook/campaigns/create');

        $callback = $this->request('admin.ads.platform.callback', 'ads/facebook/callback');
        $response = (new ReturnToOrigin)->handle($callback, function () {
            $account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'act_1', 'name' => 'ABC Business', 'has_ads_permission' => true]);
            session()->flash('success', 'Facebook ad account connected.');

            return redirect()->route('admin.ads.dashboard');
        });

        $id = SocialAccount::value('id');
        $this->assertSame(url('/en/ads/facebook/campaigns/create?connected=' . $id), $response->getTargetUrl());
        $this->assertSame('success', session('connect_notice.type'));
        $this->assertSame('Facebook connected', session('connect_notice.title'));
        $this->assertNull(session(ReturnToOrigin::SESSION_KEY), 'origin is consumed once');
        $this->assertNull(session('success'), 'toast replaces the page alert - no double message');
    }

    public function test_failure_returns_to_the_task_with_retry(): void
    {
        $this->startFrom('/en/ads/facebook/campaigns/create?step=2');

        $callback = $this->request('admin.ads.platform.callback', 'ads/facebook/callback');
        $response = (new ReturnToOrigin)->handle($callback, function () {
            session()->flash('error', 'The user denied access.');

            return redirect()->route('admin.ads.dashboard');
        });

        $this->assertSame(url('/en/ads/facebook/campaigns/create?step=2'), $response->getTargetUrl());
        $this->assertSame('error', session('connect_notice.type'));
        $this->assertSame('The user denied access.', session('connect_notice.message'));
        $this->assertStringContainsString('ads/facebook/redirect', session('connect_notice.retry'));
    }

    public function test_intermediate_callback_steps_are_left_alone(): void
    {
        $this->startFrom('/en/ads/facebook/campaigns/create');

        $callback = $this->request('admin.ads.platform.callback', 'ads/facebook/callback');
        $response = (new ReturnToOrigin)->handle($callback, fn () => redirect('/en/ads/facebook/select-pages'));

        $this->assertSame(url('/en/ads/facebook/select-pages'), $response->getTargetUrl());
        $this->assertNull(session('connect_notice'));
    }

    public function test_untrusted_origin_keeps_the_default_landing(): void
    {
        $start = $this->request('admin.ads.redirect', 'ads/{platform}/redirect', ['return_to' => 'https://evil.com/steal'], ['HTTP_REFERER' => 'https://evil.com/x']);
        (new ReturnToOrigin)->handle($start, fn () => redirect()->away('https://www.facebook.com/dialog/oauth'));

        $callback = $this->request('admin.ads.platform.callback', 'ads/facebook/callback');
        $response = (new ReturnToOrigin)->handle($callback, fn () => redirect()->route('admin.ads.dashboard'));

        $this->assertSame(route('admin.ads.dashboard'), $response->getTargetUrl());
    }

    public function test_explicit_return_to_wins_over_referer(): void
    {
        $start = $this->request('admin.social-accounts.redirect', 'social-accounts/{platform}/redirect', ['return_to' => '/en/posts/listing?platform=facebook'], ['HTTP_REFERER' => 'https://labs.socialeaz.com/en/posts/dashboard']);
        (new ReturnToOrigin)->handle($start, fn () => redirect()->away('https://www.facebook.com/dialog/oauth'));

        $this->assertSame('/en/posts/listing?platform=facebook', session(ReturnToOrigin::SESSION_KEY . '.url'));
    }
}
