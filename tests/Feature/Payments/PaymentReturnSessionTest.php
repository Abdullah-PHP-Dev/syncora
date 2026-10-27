<?php

namespace Tests\Feature\Payments;

use App\Http\Middleware\PreservePaymentReturnSession;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

class PaymentReturnSessionTest extends TestCase
{
    public function test_browser_post_bounces_before_session_without_setting_cookies(): void
    {
        $url = '/callback/payment?transaction=123&token='.str_repeat('a', 64).'&notification=0';
        // Use normal production CSRF behavior. A gateway POST has neither a session cookie nor CSRF token.
        $this->app['env'] = 'production';
        $response = $this->post($url, ['status' => 'CAPTURED', 'transaction' => 999]);
        $response->assertStatus(303)->assertRedirect($url)->assertCookieMissing(config('session.cookie'));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($this->app['request']->hasSession());
    }

    public function test_return_middleware_precedes_start_session_in_actual_route_stack(): void
    {
        $router = $this->app['router'];
        $route = $router->getRoutes()->match(Request::create('/callback/payment', 'POST'));
        $stack = $router->gatherRouteMiddleware($route);
        $this->assertLessThan(array_search(StartSession::class, $stack, true), array_search(PreservePaymentReturnSession::class, $stack, true));
    }

    public function test_provider_post_cannot_claim_notification_mode_in_body_to_avoid_bounce(): void
    {
        $url = '/callback/payment?transaction=123&token='.str_repeat('b', 64);
        $response = $this->post($url, ['notification' => '1']);
        $response->assertStatus(303)->assertRedirect($url);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_get_and_server_notifications_pass_through_to_verification(): void
    {
        foreach ([['GET', '/callback/payment'], ['POST', '/callback/payment?notification=1']] as [$method, $url]) {
            $called = false;
            $response = (new PreservePaymentReturnSession)->handle(Request::create($url, $method), function () use (&$called) {
                $called = true;

                return response('verified');
            });
            $this->assertTrue($called);
            $this->assertSame('verified', $response->getContent());
        }
    }
}
