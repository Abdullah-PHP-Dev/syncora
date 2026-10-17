<?php

namespace Tests\Feature\Coupons;

use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\CouponChange;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CouponService;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionPriceService;
use App\Services\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Tests\Feature\Coupons\Concerns\CreatesCouponPaymentTables;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

class SubscriptionCouponsTest extends TestCase
{
    use CreatesCouponPaymentTables;
    use CreatesSupportModuleTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->createSupportModuleTables();
        Schema::table('users', fn (Blueprint $table) => $table->string('mobile')->nullable());
        foreach (['2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table', '2026_08_18_000800_create_subscription_cycles_table', '2026_10_05_000001_create_tamara_orders_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->createCouponPaymentTables();
        foreach (['admin', 'seller', 'customer_support'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Http::preventStrayRequests();
        config(['services.payment.tap.secret_key' => 'test-key']);
    }

    private function user(string $role = 'seller'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function bundle(array $attributes = []): Bundle
    {
        return Bundle::create(array_replace(['name_en' => 'Pro', 'name_ar' => 'احترافية', 'slug' => 'pro-'.Str::random(6), 'price' => 49, 'currency' => 'SAR', 'is_active' => true, 'is_free' => false, 'meta' => ['yearly_price' => 411.60]], $attributes));
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_replace(['code' => 'SAVE30', 'name' => 'Launch offer', 'type' => 'percentage', 'value' => 30, 'currency' => 'SAR', 'minimum_amount' => 0, 'per_user_limit' => 1, 'cycles' => ['monthly', 'yearly'], 'is_active' => true, 'allow_yearly_discount' => true], $attributes));
    }

    private function quote(User $user, Bundle $bundle, string $code = 'save30', array $extra = [])
    {
        return $this->actingAs($user)->postJson('/subscription/coupon', array_replace(['bundle_id' => $bundle->id, 'cycle' => 'monthly', 'coupon_code' => $code, 'payment_method' => 'card'], $extra));
    }

    private function checkout(User $user, Bundle $bundle, array $extra = [])
    {
        return $this->actingAs($user)->postJson('/subscription/checkout', array_replace(['bundle_id' => $bundle->id, 'cycle' => 'monthly', 'coupon_code' => 'SAVE30', 'payment_method' => 'card', 'checkout_key' => (string) Str::uuid()], $extra));
    }

    private function fakeTap(): void
    {
        Http::fake(['api.tap.company/v2/*charges' => Http::response(['id' => 'chg_test', 'transaction' => ['url' => 'https://checkout.tap.company/test']])]);
    }

    private function adminData(array $extra = []): array
    {
        return array_replace(['version' => 0, 'name' => 'Launch', 'code' => ' launch-30 ', 'type' => 'percentage', 'value' => 30, 'currency' => 'SAR', 'minimum_amount' => 0, 'usage_limit' => 100, 'per_user_limit' => 1, 'cycles' => ['monthly', 'yearly'], 'is_active' => 1, 'first_purchase_only' => 0, 'allow_yearly_discount' => 1], $extra);
    }

    public function test_admin_can_manage_audited_coupons_and_sellers_and_support_cannot(): void
    {
        foreach (['seller', 'customer_support'] as $role) {
            $this->actingAs($this->user($role))->get('/coupons')->assertForbidden();
        }
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/coupons/create')->assertOk();
        $this->post('/coupons', $this->adminData())->assertRedirect();
        $coupon = Coupon::firstOrFail();
        $this->assertSame('LAUNCH-30', $coupon->code);
        $this->assertSame(1, CouponChange::count());
        $this->get('/coupons')->assertOk()->assertSee('LAUNCH-30');
        $this->get('/coupons/'.$coupon->id.'/edit')->assertOk();
        $this->put('/coupons/'.$coupon->id, $this->adminData(['version' => 1, 'value' => 20]))->assertRedirect();
        $this->putJson('/coupons/'.$coupon->id, $this->adminData(['version' => 1]))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->delete('/coupons/'.$coupon->id, ['version' => 2])->assertRedirect();
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
        $this->get('/coupons?status=archived')->assertOk();
        $this->get('/coupons/'.$coupon->id.'/edit')->assertOk()->assertSee(__('coupons.restore_first'));
        $this->post('/coupons/'.$coupon->id.'/restore', ['version' => 3])->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);
        $this->assertSame(4, CouponChange::count());
    }

    public function test_validation_rejects_invalid_values_dates_duplicate_codes_and_non_seller_eligibility(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $this->postJson('/coupons', $this->adminData(['value' => 101, 'code' => 'a', 'ends_at' => '2026-01-01', 'starts_at' => '2027-01-01', 'seller_ids' => [$admin->id]]))->assertUnprocessable()->assertJsonValidationErrors(['value', 'code', 'ends_at']);
        $this->postJson('/coupons', $this->adminData(['seller_ids' => [$admin->id]]))->assertUnprocessable()->assertJsonValidationErrors('seller_ids');
        $this->post('/coupons', $this->adminData(['starts_at' => null, 'ends_at' => '2027-01-01']))->assertRedirect();
        $this->postJson('/coupons', $this->adminData())->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, Coupon::count());
    }

    public function test_inclusive_vat_is_extracted_after_discount_and_never_added_to_the_charge(): void
    {
        config(['subscriptions.vat_rate' => 15]);
        $pricing = app(SubscriptionPriceService::class);
        foreach ([[49, 0, 42.61, 6.39, 49], [49, 14.70, 29.83, 4.47, 34.30], [411.60, 123.48, 250.54, 37.58, 288.12], [49, 49, 0, 0, 0], [0.05, 0, 0.04, 0.01, 0.05]] as [$gross, $discount, $net, $vat, $total]) {
            $quote = $pricing->breakdown($gross, $discount);
            $this->assertEquals($net, $quote['net_amount']);
            $this->assertEquals($vat, $quote['vat']);
            $this->assertEquals($total, $quote['total']);
            $this->assertEquals(CouponService::minor($total), CouponService::minor($quote['net_amount']) + CouponService::minor($quote['vat']));
        }
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon();
        $quote = app(CouponService::class)->quote($user, $bundle, 'monthly');
        $this->assertEquals(49, $quote['total']);
        $this->assertEquals(6.39, $quote['vat']);
        $this->quote($user, $bundle)->assertOk()->assertJsonPath('total', 34.3)->assertJsonPath('vat', 4.47)->assertJsonPath('net_amount', 29.83)->assertJsonPath('prices_include_vat', true);
        $this->fakeTap();
        $this->checkout($user, $bundle, ['vat' => 999, 'expected_total' => 34.30])->assertOk();
        $transaction = WalletTransaction::first();
        $this->assertSame('34.30', $transaction->amount);
        $this->assertSame('4.47', $transaction->vat);
        $this->assertEquals(29.83, $transaction->meta['net_amount']);
        Http::assertSent(fn ($request) => $request['amount'] === 34.3);
    }

    public function test_preview_is_case_insensitive_uses_server_prices_and_never_creates_a_payment(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon();
        $this->quote($user, $bundle, ' save30 ', ['amount' => 1, 'discount' => 999])->assertOk()->assertJsonPath('discount', 14.7)->assertJsonPath('total', 34.3)->assertJsonPath('coupon_code', 'SAVE30');
        $this->quote($user, $bundle, extra: ['cycle' => 'yearly'])->assertOk()->assertJsonPath('subtotal', 411.6)->assertJsonPath('discount', 123.48)->assertJsonPath('total', 288.12);
        $this->assertSame(0, WalletTransaction::count());
        $this->assertSame(0, CouponRedemption::count());
        Http::assertNothingSent();
    }

    public function test_fixed_discounts_caps_minimums_and_rounding_never_produce_negative_totals(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 100, 'maximum_discount' => 20]);
        $this->quote($user, $bundle)->assertOk()->assertJsonPath('discount', 20)->assertJsonPath('total', 29);
        $coupon->update(['maximum_discount' => null]);
        $this->quote($user, $bundle)->assertOk()->assertJsonPath('discount', 49)->assertJsonPath('total', 0);
        $coupon->update(['minimum_amount' => 50]);
        $this->quote($user, $bundle)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $coupon->update(['minimum_amount' => 0, 'type' => 'percentage', 'value' => 33.33]);
        $bundle->update(['price' => 0.05]);
        $this->quote($user, $bundle)->assertOk()->assertJsonPath('discount', 0.02)->assertJsonPath('total', 0.03);
    }

    public function test_dates_eligibility_currency_cycles_and_payment_rules_are_enforced(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $coupon = $this->coupon();
        foreach ([['is_active' => false], ['starts_at' => now()->addDay()], ['ends_at' => now()->subSecond()], ['currency' => 'USD'], ['cycles' => ['yearly']], ['bundle_ids' => [999]], ['seller_ids' => [999]], ['payment_methods' => ['wallet']], ['allow_yearly_discount' => false]] as $restriction) {
            $coupon->update(array_replace(['is_active' => true, 'starts_at' => null, 'ends_at' => null, 'currency' => 'SAR', 'cycles' => ['monthly', 'yearly'], 'bundle_ids' => [], 'seller_ids' => [], 'payment_methods' => [], 'allow_yearly_discount' => true], $restriction));
            $this->quote($user, $bundle, extra: isset($restriction['allow_yearly_discount']) ? ['cycle' => 'yearly'] : [])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        }
    }

    public function test_checkout_reserves_last_use_and_idempotent_replay_never_creates_another_charge(): void
    {
        $this->fakeTap();
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon(['usage_limit' => 1]);
        $key = (string) Str::uuid();
        $this->checkout($user, $bundle, ['checkout_key' => $key, 'amount' => 1, 'discount' => 999, 'expected_total' => 34.30])->assertOk();
        $transaction = WalletTransaction::first();
        $this->assertSame('34.30', $transaction->amount);
        $this->assertSame('14.70', $transaction->discount);
        $this->assertSame('reserved', CouponRedemption::first()->status);
        $this->checkout($user, $bundle, ['checkout_key' => $key])->assertOk();
        $this->checkout($user, $bundle, ['checkout_key' => $key, 'cycle' => 'yearly'])->assertConflict();
        $this->checkout($this->user(), $bundle)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $this->assertSame(1, WalletTransaction::count());
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['amount'] === 34.3 && $r['currency'] === 'SAR');
    }

    public function test_failed_gateway_releases_coupon_but_a_timeout_keeps_it_reserved(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon(['usage_limit' => 1]);
        Http::fake(['api.tap.company/v2/*charges' => Http::sequence()->push([], 400)->pushFailedConnection()]);
        $this->checkout($user, $bundle)->assertUnprocessable()->assertJsonPath('retryable', true);
        $this->assertSame('released', CouponRedemption::first()->status);
        $this->checkout($user, $bundle)->assertConflict()->assertJsonPath('pending', true);
        $this->assertSame(1, CouponRedemption::where('status', 'reserved')->count());
        $this->checkout($this->user(), $bundle)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_zero_total_activates_once_without_contacting_gateway_and_respects_user_limit(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon(['value' => 100]);
        $key = (string) Str::uuid();
        $this->checkout($user, $bundle, ['checkout_key' => $key, 'cycle' => 'yearly'])->assertOk();
        $this->checkout($user, $bundle, ['checkout_key' => $key, 'cycle' => 'yearly'])->assertOk();
        $this->assertSame(1, SubscriptionCycle::count());
        $this->assertSame(12, Subscription::first()->billing_period);
        $this->assertSame('completed', CouponRedemption::first()->status);
        $this->checkout($user, $bundle)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        Http::assertNothingSent();
    }

    public function test_price_change_and_archived_coupon_are_rechecked_before_payment(): void
    {
        $user = $this->user();
        $bundle = $this->bundle();
        $coupon = $this->coupon();
        $this->checkout($user, $bundle, ['expected_total' => 1])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $coupon->delete();
        $this->checkout($user, $bundle)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $this->assertSame(0, WalletTransaction::count());
        Http::assertNothingSent();
    }

    public function test_payment_completion_hook_consumes_a_reservation_once(): void
    {
        $this->fakeTap();
        $user = $this->user();
        $bundle = $this->bundle();
        $this->coupon();
        $this->checkout($user, $bundle)->assertOk();
        $transaction = WalletTransaction::first();
        $this->assertSame('reserved', CouponRedemption::first()->status);
        $transaction->update(['status' => 'completed']);
        app(SubscriptionService::class)->processSuccessfulPayment($transaction);
        app(SubscriptionService::class)->processSuccessfulPayment($transaction->fresh());
        $this->assertSame(1, SubscriptionCycle::count());
        $this->assertSame('completed', CouponRedemption::first()->status);
    }

    public function test_budget_and_first_purchase_rules_include_pending_checkouts(): void
    {
        $this->fakeTap();
        $user = $this->user();
        $bundle = $this->bundle();
        $coupon = $this->coupon(['discount_budget' => 15]);
        $this->checkout($user, $bundle)->assertOk();
        $this->quote($this->user(), $bundle)->assertUnprocessable();
        $coupon->update(['discount_budget' => null, 'per_user_limit' => null, 'first_purchase_only' => true]);
        $this->quote($user, $bundle)->assertUnprocessable();
    }

    public function test_existing_reservation_survives_admin_deactivation_and_cannot_be_renamed(): void
    {
        $this->fakeTap();
        $user = $this->user();
        $bundle = $this->bundle();
        $coupon = $this->coupon();
        $this->checkout($user, $bundle)->assertOk();
        $this->actingAs($this->user('admin'))->putJson('/coupons/'.$coupon->id, $this->adminData(['code' => 'NEWCODE', 'version' => 1]))->assertUnprocessable()->assertJsonValidationErrors('code');
        $coupon->update(['is_active' => false]);
        $transaction = WalletTransaction::first();
        $transaction->update(['status' => 'completed']);
        app(SubscriptionCheckoutService::class)->fulfill($transaction);
        $this->assertSame('completed', CouponRedemption::first()->status);
    }
}
