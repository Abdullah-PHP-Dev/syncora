<?php

namespace Tests\Feature\Payments;

use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\Gateways\ClickPayGateway;
use App\Services\Payments\Gateways\NoonGateway;
use App\Services\Payments\Gateways\TapGateway;
use App\Services\Payments\PaymentCallbackUrl;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Feature\Coupons\Concerns\CreatesCouponPaymentTables;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

/** Exercises the actual HTTP clients against postman/simulations/payment-callback.sim.yaml. */
class SharedPaymentCallbackTest extends TestCase
{
    use CreatesCouponPaymentTables;
    use CreatesSupportModuleTables;

    private string $simulation;

    protected function setUp(): void
    {
        parent::setUp();
        $base = getenv('PAYMENT_SIMULATION_URL');
        if (! $base) {
            $this->markTestSkipped('Start the payment-callback Postman simulation and set PAYMENT_SIMULATION_URL.');
        }
        $this->simulation = rtrim($base, '/');
        $this->withoutVite();
        $this->createSupportModuleTables();
        Schema::table('users', fn (Blueprint $table) => $table->string('mobile')->nullable());
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        foreach (['2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table', '2026_08_18_000800_create_subscription_cycles_table', '2026_10_05_000001_create_tamara_orders_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->createCouponPaymentTables();
        Role::findOrCreate('seller', 'web');
        foreach (['tap', 'clickpay', 'tamara'] as $provider) {
            config(["services.payment.$provider.base_url" => $this->simulation.'/'.$provider.($provider === 'tap' ? '/v2' : ''),
                "services.payment.$provider.secret_key" => 'simulation-key']);
        }
        config(['services.payment.clickpay.merchant_id' => '12345', 'services.payment.tamara.api_token' => 'simulation-key']);
    }

    public static function providers(): array
    {
        return [['tap'], ['clickpay'], ['tamara']];
    }

    private function checkout(string $provider): WalletTransaction
    {
        $user = User::factory()->create(['mobile' => '+966500000000']);
        $user->assignRole('seller');
        $bundle = Bundle::create(['name_en' => 'Pro', 'name_ar' => 'Pro', 'slug' => 'pro-'.Str::random(8),
            'price' => 49, 'currency' => 'SAR', 'is_active' => true, 'is_free' => false]);
        $coupon = Coupon::create(['code' => 'SAVE'.Str::upper(Str::random(8)), 'name' => 'Test', 'type' => 'percentage',
            'value' => 30, 'currency' => 'SAR', 'minimum_amount' => 0, 'per_user_limit' => 1,
            'cycles' => ['monthly'], 'is_active' => true, 'allow_yearly_discount' => true]);
        $result = app(SubscriptionCheckoutService::class)->start($user, $bundle, 'monthly', $provider === 'tamara' ? 'installment' : 'card', $provider, $coupon->code);
        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $transaction = WalletTransaction::findOrFail($result['transaction_id']);
        $this->assertNotEmpty($transaction->gateway_reference);
        $this->assertSame('34.30', $transaction->amount);

        return $transaction;
    }

    private function remote(WalletTransaction $transaction, array $changes = []): array
    {
        return Http::post($this->simulation.'/'.$transaction->payment_gateway.'/__test/'.$transaction->gateway_reference, $changes)->throw()->json();
    }

    private function paidPatch(string $provider): array
    {
        return match ($provider) {
            'tap' => ['status' => 'CAPTURED'],
            'clickpay' => ['payment_result' => ['response_status' => 'A']],
            'tamara' => ['status' => 'fully_captured'],
        };
    }

    #[DataProvider('providers')]
    public function test_all_providers_use_shared_route_and_complete_exactly_once(string $provider): void
    {
        $transaction = $this->checkout($provider);
        $record = $this->remote($transaction, ['patch' => $this->paidPatch($provider)]);
        $request = $record['request'];
        [$browser, $notification] = match ($provider) {
            'tap' => [$request['redirect']['url'], $request['post']['url']],
            'clickpay' => [$request['return'], $request['callback']],
            'tamara' => [$request['merchant_url']['success'], $request['merchant_url']['notification']],
        };
        $this->assertSame('/callback/payment', parse_url($browser, PHP_URL_PATH));
        $this->assertSame('/callback/payment', parse_url($notification, PHP_URL_PATH));
        $this->assertStringContainsString('token=', $browser);
        // Provider selection and status in the incoming payload cannot override stored data.
        set_adminSetting('current_payment_gateway', $provider === 'tap' ? 'clickpay' : 'tap');
        $this->postJson($notification, ['gateway' => 'noon', 'status' => 'FAILED', 'tap_id' => 'forged', 'transaction' => 999])
            ->assertOk()->assertJsonPath('status', 'paid');
        $this->get($browser.'&tap_id=forged&status=failed')->assertRedirect(route('admin.subscription.select'))
            ->assertSessionHas('payment_notice.type', 'success')
            ->assertSessionHas('payment_notice.message', __('subscriptions.payment_success'));
        $this->assertStringContainsString(__('subscriptions.payment_success_title'), view('layouts.partials.connect-toast')->render());
        $this->postJson($notification)->assertOk();
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertNotNull($transaction->fresh()->fulfilled_at);
        $this->assertSame(1, SubscriptionCycle::count());
        $this->assertSame('completed', CouponRedemption::sole()->status);
        $this->assertSame(1, $this->remote($transaction)['queries']);
    }

    public function test_clickpay_browser_post_returns_to_result_without_a_login_session(): void
    {
        $transaction = $this->checkout('clickpay');
        $this->remote($transaction, ['patch' => $this->paidPatch('clickpay')]);
        $url = PaymentCallbackUrl::forTransaction($transaction);
        $this->post($url, ['respStatus' => 'D', 'tranRef' => 'fake'])->assertStatus(303)->assertRedirect(route('admin.subscription.select'))->assertSessionHas('payment_notice.type', 'success');
        $this->get($url)->assertRedirect(route('admin.subscription.select'))->assertSessionHas('payment_notice.type', 'success');
        $this->assertSame(1, SubscriptionCycle::count());
    }

    public function test_success_toast_appears_on_subscription_page_once_after_redirect(): void
    {
        // This test application registers the default-locale routes once, rather than rebooting on each redirect.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LocaleCookieRedirect::class,
            LaravelLocalizationRedirectFilter::class,
        ]);
        $transaction = $this->checkout('tap');
        $this->remote($transaction, ['patch' => $this->paidPatch('tap')]);
        $this->actingAs(User::findOrFail($transaction->seller_id))->followingRedirects()
            ->get(PaymentCallbackUrl::forTransaction($transaction))
            ->assertOk()->assertSee(__('subscriptions.payment_success_title'))
            ->assertSee('se-connect-toast is-success', false);
        $this->get(route('admin.subscription.select'))->assertOk()->assertDontSee(__('subscriptions.payment_success_title'));
        $this->assertSame(1, SubscriptionCycle::count());
    }

    public function test_provider_post_is_csrf_exempt_with_normal_web_middleware(): void
    {
        $transaction = $this->checkout('tap');
        $this->remote($transaction, ['patch' => $this->paidPatch('tap')]);
        // Enable the normal CSRF check that Laravel bypasses in the testing environment.
        $this->app['env'] = 'production';
        $this->postJson(PaymentCallbackUrl::forTransaction($transaction, true))->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_unconfigured_card_providers_fail_before_any_request(): void
    {
        config(['services.payment.tap.secret_key' => null, 'services.payment.clickpay.secret_key' => null]);
        Http::preventStrayRequests();
        $this->assertSame('failed', app(TapGateway::class)->pay([])['status']);
        $this->assertSame('failed', app(ClickPayGateway::class)->pay([])['status']);
    }

    public static function states(): array
    {
        return [
            ['tap', ['status' => 'INITIATED'], 'pending'], ['tap', ['status' => 'DECLINED'], 'failed'],
            ['clickpay', ['payment_result' => ['response_status' => 'H']], 'pending'],
            ['clickpay', ['payment_result' => ['response_status' => 'D']], 'failed'],
            ['tamara', ['status' => 'new'], 'pending'], ['tamara', ['status' => 'declined'], 'failed'],
        ];
    }

    #[DataProvider('states')]
    public function test_pending_and_failed_statuses_preserve_or_release_reservation(string $provider, array $patch, string $status): void
    {
        $transaction = $this->checkout($provider);
        $this->remote($transaction, ['patch' => $patch]);
        $this->postJson(PaymentCallbackUrl::forTransaction($transaction, true), ['status' => 'CAPTURED'])
            ->assertStatus($status === 'pending' ? 503 : 200)->assertJsonPath('status', $status);
        $this->get(PaymentCallbackUrl::forTransaction($transaction))->assertRedirect(route('admin.subscription.select'))
            ->assertSessionHas('payment_notice.type', $status === 'pending' ? 'warning' : 'error')
            ->assertSessionHas('payment_notice.message', __($status === 'pending' ? 'subscriptions.payment_pending' : 'subscriptions.payment_failed'));
        if ($status === 'pending') {
            $this->assertSame(PaymentCallbackUrl::forTransaction($transaction), session('payment_notice.retry'));
            $this->assertStringContainsString(__('subscriptions.check_payment'), view('layouts.partials.connect-toast')->render());
        } else {
            $this->assertStringContainsString(__('subscriptions.payment_failed_title'), view('layouts.partials.connect-toast')->render());
        }
        $this->assertSame($status === 'pending' ? 'pending' : 'rejected', $transaction->fresh()->status);
        $this->assertSame($status === 'pending' ? 'reserved' : 'released', CouponRedemption::sole()->status);
        $this->assertSame(0, SubscriptionCycle::count());
    }

    public static function mismatches(): array
    {
        return [
            ['tap', ['amount' => 1]], ['tap', ['currency' => 'USD']], ['tap', ['reference' => ['transaction' => 'wrong']]], ['tap', ['id' => 'wrong']],
            ['clickpay', ['cart_amount' => 1]], ['clickpay', ['cart_currency' => 'USD']], ['clickpay', ['cart_id' => 'wrong']], ['clickpay', ['tran_ref' => 'wrong']], ['clickpay', ['tran_type' => 'Auth']],
            ['tamara', ['total_amount' => ['amount' => 1, 'currency' => 'SAR']]],
            ['tamara', ['total_amount' => ['amount' => 34.30, 'currency' => 'USD']]],
            ['tamara', ['order_reference_id' => 'wrong']], ['tamara', ['order_id' => 'wrong']],
        ];
    }

    #[DataProvider('mismatches')]
    public function test_mismatched_success_never_activates_or_releases_reservation(string $provider, array $patch): void
    {
        $transaction = $this->checkout($provider);
        $this->remote($transaction, ['patch' => array_replace($this->paidPatch($provider), $patch)]);
        $this->postJson(PaymentCallbackUrl::forTransaction($transaction, true))->assertStatus(503)->assertJsonPath('status', 'pending');
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('reserved', CouponRedemption::sole()->status);
        $this->assertSame(0, SubscriptionCycle::count());
    }

    #[DataProvider('providers')]
    public function test_provider_outage_is_retryable_then_recovers(string $provider): void
    {
        $transaction = $this->checkout($provider);
        $url = PaymentCallbackUrl::forTransaction($transaction, true);
        $this->remote($transaction, ['http_status' => 502, 'html' => true]);
        $this->postJson($url)->assertStatus(503)->assertJsonPath('status', 'pending');
        $this->assertSame('reserved', CouponRedemption::sole()->status);
        $this->remote($transaction, ['http_status' => 200, 'html' => false, 'patch' => $this->paidPatch($provider)]);
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame(1, SubscriptionCycle::count());
    }

    public function test_invalid_and_forged_returns_never_query_provider(): void
    {
        $this->get('/callback/payment')->assertStatus(400)->assertSee('return link is incomplete');
        $transaction = $this->checkout('tap');
        $url = route('payment.callback', ['transaction' => $transaction->id, 'token' => str_repeat('x', 64)]);
        $this->get($url)->assertForbidden();
        $this->postJson($url, ['status' => 'CAPTURED'])->assertForbidden();
        $this->assertSame(0, $this->remote($transaction)['queries']);
        $this->assertSame(0, SubscriptionCycle::count());
    }

    public function test_early_notification_without_persisted_reference_requests_retry(): void
    {
        $transaction = $this->checkout('tap');
        $reference = $transaction->gateway_reference;
        $transaction->update(['gateway_reference' => null]);
        $url = PaymentCallbackUrl::forTransaction($transaction, true);
        $this->postJson($url, ['id' => $reference, 'status' => 'CAPTURED'])->assertStatus(503);
        $transaction->update(['gateway_reference' => $reference]);
        $this->remote($transaction, ['patch' => $this->paidPatch('tap')]);
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_tamara_authorisation_and_capture_complete_coupon_checkout_once(): void
    {
        $transaction = $this->checkout('tamara');
        $this->remote($transaction, ['patch' => ['status' => 'approved']]);
        $url = PaymentCallbackUrl::forTransaction($transaction, true);
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'paid');
        $this->postJson($url)->assertOk();
        $this->assertSame(1, SubscriptionCycle::count());
        $this->assertSame('completed', CouponRedemption::sole()->status);
        $this->assertSame(3, $this->remote($transaction)['queries']);
    }

    public function test_noon_placeholder_cannot_accept_payments(): void
    {
        $provider = app(NoonGateway::class);
        $this->assertSame('failed', $provider->pay([])['status']);
        $this->assertFalse($provider->verify([]));
    }
}
