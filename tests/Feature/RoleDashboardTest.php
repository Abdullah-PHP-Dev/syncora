<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Listeners\AssignFreeSubscriptionListener;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\SubscriptionService;
use Mockery;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use \Tests\Feature\Concerns\FakesTeamDashboardMetrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTeamDashboardMetrics();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
    }

    private function userWithRoles(array $roles): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->name = 'Test user';
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($role) => in_array($role, $roles));
        $user->shouldReceive('hasAnyRole')->andReturnUsing(fn ($required) => count(array_intersect((array) $required, $roles)) > 0);

        return $user;
    }

    public function test_login_url_has_no_admin_prefix(): void
    {
        $this->assertSame('/login', route('login', absolute: false));
    }

    public function test_dashboard_resolves_sellers_and_team_admins(): void
    {
        foreach ([['seller'], ['admin'], ['admin', 'seller']] as $roles) {
            $user = $this->userWithRoles($roles);
            $expected = '/dashboard';
            $view = in_array('admin', $roles) ? 'admin.team-dashboard' : 'admin.dashboard';
            $this->assertSame($view, app(DashboardService::class)->viewName($user));
            $this->assertSame($expected, app(DashboardService::class)->url($user));
            $this->actingAs($user);
            $request = \Illuminate\Http\Request::create('/dashboard');
            $request->setUserResolver(fn () => $user);
            $response = app(\App\Http\Controllers\Admin\DashboardController::class)->dashboard($request, app(DashboardService::class), app(\App\Services\TeamDashboardService::class));
            $this->assertSame($view, $response->name());
            $this->get('/login')->assertRedirect($expected);
        }
    }

    public function test_login_uses_role_dashboard_even_with_an_intended_admin_url(): void
    {
        foreach ([['seller'], ['admin']] as $roles) {
            $user = $this->userWithRoles($roles);
            $request = Mockery::mock(\App\Http\Requests\Auth\LoginRequest::class)->makePartial();
            $request->shouldReceive('authenticate')->once();
            $request->setUserResolver(fn () => $user);
            $session = app('session.store');
            $session->put('url.intended', '/admin/dashboard');
            $request->setLaravelSession($session);

            $response = app(\App\Http\Controllers\Auth\AuthenticatedSessionController::class)->store($request);

            $this->assertSame(url(app(DashboardService::class)->url($user)), $response->getTargetUrl());
            $this->assertFalse($session->has('url.intended'));
        }
    }

    public function test_team_dashboard_is_accessible_without_a_subscription(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRoles(['admin']))->get('/dashboard')
            ->assertOk()->assertSee('Social Eaz Team Dashboard')->assertDontSee('Manage your plan');
    }

    public function test_seller_dashboard_is_rendered_at_the_shared_url(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRoles(['seller']))->get('/dashboard')
            ->assertOk()->assertViewIs('admin.dashboard')->assertDontSee('Social Eaz Team Dashboard');
    }

    public function test_unknown_role_cannot_resolve_a_dashboard(): void
    {
        $this->actingAs($this->userWithRoles([]))->get('/dashboard')->assertForbidden();
    }

    public function test_application_routes_have_no_role_prefixes(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('~(?:^|/)(admin|seller)(?:/|$)~', $route->uri());
        }
        $this->assertSame('/subscription/select', route('admin.subscription.select', absolute: false));
    }

    public function test_team_admins_cannot_access_seller_modules_or_subscription_routes(): void
    {
        foreach ([['admin'], ['admin', 'seller']] as $roles) {
            $this->actingAs($this->userWithRoles($roles));
            foreach (['/posts/dashboard', '/subscription/select', '/subscription/plans', '/subscription/checkout', '/subscription/checkout-data'] as $url) {
                $this->get($url)->assertForbidden();
            }
            foreach (['/subscription/checkout', '/subscription/activate', '/subscription/cancel'] as $url) {
                $this->post($url)->assertForbidden();
            }
        }
    }

    public function test_admin_registration_event_does_not_assign_a_subscription(): void
    {
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldNotReceive('assignFreeTrial');
        $this->app->instance(SubscriptionService::class, $service);
        (new AssignFreeSubscriptionListener)->handle(new UserRegistered($this->userWithRoles(['admin'])));
        $this->assertTrue(true);
    }

    public function test_subscription_service_rejects_admins_before_creating_a_trial(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SubscriptionService::class)->assignFreeTrial($this->userWithRoles(['admin', 'seller']));
    }
}
