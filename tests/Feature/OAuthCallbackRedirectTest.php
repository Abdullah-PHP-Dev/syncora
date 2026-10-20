<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * OAuth callbacks that fail on a plan limit (ValidationException) or a
 * module the plan doesn't include (403) must land on an app page - never
 * Laravel's redirect()->back(), which on a callback is the provider's site
 * (the Referer), e.g. facebook.com.
 */
class OAuthCallbackRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function () {
            Route::get('/__test/ads/facebook/callback', fn () => throw ValidationException::withMessages(['package' => 'Your plan allows 1 advertising account.']))
                ->name('admin.ads.test.callback');
            Route::get('/__test/social/facebook/callback', fn () => abort(403, 'Publishing is not included in your plan.'))
                ->name('admin.social-accounts.test.callback');
            Route::get('/__test/form', fn () => throw ValidationException::withMessages(['name' => 'Required.']))
                ->name('admin.test.form');
        });
    }

    public function test_ads_callback_plan_limit_goes_to_the_ads_dashboard_not_the_provider(): void
    {
        $response = $this->get('/__test/ads/facebook/callback?code=abc', ['Referer' => 'https://www.facebook.com/']);

        $response->assertRedirect(route('admin.ads.dashboard'));
        $this->assertStringNotContainsString('facebook.com', $response->headers->get('Location'));
        $response->assertSessionHas('error', 'Your plan allows 1 advertising account.');
    }

    public function test_hub_callback_module_403_goes_to_the_connection_hub(): void
    {
        $response = $this->get('/__test/social/facebook/callback', ['Referer' => 'https://www.facebook.com/']);

        $response->assertRedirect(route('admin.connections.index'));
        $response->assertSessionHas('error', 'Publishing is not included in your plan.');
    }

    public function test_other_pages_keep_the_normal_back_redirect(): void
    {
        $this->get('/__test/form', ['Referer' => 'https://syncora.test/en/somewhere'])
            ->assertRedirect('https://syncora.test/en/somewhere');
    }
}
