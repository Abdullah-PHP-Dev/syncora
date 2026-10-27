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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_card_checkout_uses_selected_gateway_and_keeps_it_on_retry(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        set_adminSetting('current_payment_gateway', 'clickpay');
        config(['services.payment.tap.secret_key' => null]);
        $this->mock(\App\Services\Payments\Gateways\ClickPayGateway::class, function ($mock) {
            $mock->shouldReceive('pay')->once()->withArgs(fn ($data) => $data['gateway'] === 'clickpay'
                && $data['transaction']->payment_gateway === 'clickpay')
                ->andReturn(['status' => 'pending', 'redirect_url' => 'https://example.com/payment']);
        });
        $user = $this->user();
        $bundle = $this->bundle();
        $data = ['coupon_code' => null, 'checkout_key' => (string) Str::uuid()];
        $this->checkout($user, $bundle, $data)->assertOk()->assertJsonPath('checkout_url', 'https://example.com/payment');
        set_adminSetting('current_payment_gateway', 'tap');
        $this->checkout($user, $bundle, $data)->assertOk()->assertJsonPath('checkout_url', 'https://example.com/payment');
        $this->assertSame('clickpay', WalletTransaction::sole()->payment_gateway);
        Http::assertNothingSent();
    }

    public static function subscriptionExpiryCases(): array
    {
        return [
            'same bundle retains remaining time' => ['2026-02-28', 'monthly', false, '2026-03-28', '2025-12-01'],
            'different bundle starts today' => ['2026-02-28', 'monthly', true, '2026-02-15', '2026-01-15'],
            'same bundle yearly renewal retains remaining time' => ['2026-02-28', 'yearly', false, '2027-02-28', '2025-12-01'],
            'different bundle yearly purchase starts today' => ['2026-02-28', 'yearly', true, '2027-01-15', '2026-01-15'],
            'same bundle expiry today is still valid' => ['2026-01-15', 'monthly', false, '2026-02-15', '2025-12-01'],
            'expired monthly starts today' => ['2026-01-14', 'monthly', false, '2026-02-15', '2026-01-15'],
            'expired yearly starts today' => ['2026-01-14', 'yearly', true, '2027-01-15', '2026-01-15'],
            'missing expiry starts today' => [null, 'monthly', false, '2026-02-15', '2026-01-15'],
        ];
    }

    #[DataProvider('subscriptionExpiryCases')]
    public function test_fulfillment_extends_same_bundle_or_starts_new_term(?string $expiry, string $cycle, bool $changeBundle, string $expectedExpiry, string $expectedStart): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-15 12:00:00'));
        $user = $this->user();
        $oldBundle = $this->bundle(['sort_order' => 1]);
        $bundle = $changeBundle ? $this->bundle(['name_en' => 'Business', 'price' => 99, 'sort_order' => 2]) : $oldBundle;
        $subscription = Subscription::create(['user_id' => $user->id, 'bundle_id' => $oldBundle->id,
            'bundle_name' => $oldBundle->name_en, 'billing_period' => 1, 'start_date' => '2025-12-01',
            'end_date' => $expiry, 'status' => 'active', 'is_active' => true]);
        $transaction = WalletTransaction::create(['seller_id' => $user->id, 'amount' => 49,
            'direction' => 'debit', 'category' => 'subscription', 'status' => 'completed',
            'meta' => ['bundle_id' => $bundle->id, 'bundle_name' => $bundle->name_en, 'cycle' => $cycle]]);

        $service = app(SubscriptionCheckoutService::class);
        $service->fulfill($transaction);
        $service->fulfill($transaction->fresh());

        $subscription->refresh();
        $this->assertSame($bundle->id, $subscription->bundle_id);
        $this->assertSame($bundle->name_en, $subscription->bundle_name);
        $this->assertSame($expectedStart, $subscription->start_date->toDateString());
        $this->assertSame($expectedExpiry, $subscription->end_date->toDateString());
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->is_active);
        $this->assertSame(1, SubscriptionCycle::count());
        $subscriptionCycle = SubscriptionCycle::sole();
        $this->assertSame($expectedExpiry, $subscriptionCycle->end_date->toDateString());
        $this->assertSame($expectedStart === '2025-12-01' ? $expiry : '2026-01-15', $subscriptionCycle->start_date->toDateString());
        $this->assertSame($expectedStart === '2025-12-01' ? 'renewal' : 'purchase', $subscriptionCycle->type);
        $this->assertNotNull($transaction->fresh()->fulfilled_at);
        Http::assertNothingSent();
    }

    public static function packageSelectionCases(): array
    {
        return [
            'active package blocks lower monthly package' => ['active', true, '2026-02-28', 'lower', 'monthly', false],
            'yearly billing cannot bypass lower tier restriction' => ['active', true, '2026-02-28', 'lower', 'yearly', false],
            'expiry today still blocks downgrade' => ['active', true, '2026-01-15', 'lower', 'monthly', false],
            'no expiry still blocks downgrade' => ['active', true, null, 'lower', 'monthly', false],
            'expired package allows downgrade' => ['active', true, '2026-01-14', 'lower', 'monthly', true],
            'inactive package allows selection' => ['active', false, '2026-02-28', 'lower', 'monthly', true],
            'cancelled package allows selection' => ['cancelled', true, '2026-02-28', 'lower', 'monthly', true],
            'same package renewal allowed' => ['active', true, '2026-02-28', 'same', 'monthly', true],
            'upgrade allowed' => ['active', true, '2026-02-28', 'higher', 'monthly', true],
        ];
    }

    #[DataProvider('packageSelectionCases')]
    public function test_package_selection_and_checkout_enforce_active_subscription_policy(string $status, bool $active, ?string $expiry, string $selection, string $cycle, bool $allowed): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-15 12:00:00'));
        $user = $this->user();
        $current = $this->bundle(['price' => 99, 'sort_order' => 2]);
        $target = match ($selection) {
            'same' => $current,
            'lower' => $this->bundle(['price' => 49, 'sort_order' => 1]),
            'higher' => $this->bundle(['price' => 199, 'sort_order' => 3]),
        };
        Subscription::create(['user_id' => $user->id, 'bundle_id' => $current->id,
            'bundle_name' => $current->name_en, 'billing_period' => 1, 'start_date' => '2025-12-01',
            'end_date' => $expiry, 'status' => $status, 'is_active' => $active]);
        $this->coupon(['value' => 100]);
        $page = $this->actingAs($user)->get('/subscription/select')->assertOk();
        $this->assertMatchesRegularExpression('/class="admin-subscription-plan">\s*'.preg_quote(e($current->name), '/').'\s*<\/span>/', $page->getContent());
        $plans = $this->getJson('/subscription/plans')->assertOk();
        $plan = collect($plans->json('data.packages'))->firstWhere('id', $target->id);
        $this->assertSame($allowed, $plan['can_select']);
        $this->assertSame($allowed ? null : __('subscriptions.downgrade_blocked'), $plan['selection_message']);
        $url = '/subscription/checkout?plan_id='.$target->id.'&cycle='.$cycle;
        $dataUrl = '/subscription/checkout-data?plan_id='.$target->id.'&cycle='.$cycle;
        if ($allowed) {
            $this->get($url)->assertOk();
            $this->getJson($dataUrl)->assertOk();
            $this->checkout($user, $target, ['cycle' => $cycle])->assertOk();
            $this->assertSame($target->id, Subscription::sole()->bundle_id);
        } else {
            $this->get($url)->assertRedirect(route('admin.subscription.select'))->assertSessionHas('error', __('subscriptions.downgrade_blocked'));
            $this->getJson($dataUrl)->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
            $this->quote($user, $target, extra: ['cycle' => $cycle])->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
            $this->checkout($user, $target, ['cycle' => $cycle])->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
            $this->assertSame(0, WalletTransaction::count());
            $this->assertSame(0, CouponRedemption::count());
            $this->assertSame($current->id, Subscription::sole()->bundle_id);
        }
        Http::assertNothingSent();
    }

    public function test_older_completed_checkout_cannot_replace_a_newer_active_higher_package(): void
    {
        $user = $this->user();
        $lower = $this->bundle(['price' => 49, 'sort_order' => 1]);
        $higher = $this->bundle(['price' => 99, 'sort_order' => 2]);
        $subscription = Subscription::create(['user_id' => $user->id, 'bundle_id' => $higher->id,
            'bundle_name' => $higher->name_en, 'billing_period' => 1, 'start_date' => today(),
            'end_date' => today()->addMonth(), 'status' => 'active', 'is_active' => true]);
        $transaction = WalletTransaction::create(['seller_id' => $user->id, 'amount' => 49,
            'direction' => 'debit', 'category' => 'subscription', 'status' => 'completed',
            'meta' => ['bundle_id' => $lower->id, 'bundle_name' => $lower->name_en, 'cycle' => 'monthly']]);
        try {
            app(SubscriptionCheckoutService::class)->fulfill($transaction);
            $this->fail('An older payment downgraded the active subscription.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('bundle_id', $exception->errors());
        }
        $this->assertSame($higher->id, $subscription->fresh()->bundle_id);
        $this->assertSame(0, SubscriptionCycle::count());
        $this->assertNull($transaction->fresh()->fulfilled_at);
        Http::assertNothingSent();
    }

    public function test_package_order_defines_upgrade_independently_of_discounts_and_billing_cycle(): void
    {
        $user = $this->user();
        $current = $this->bundle(['sort_order' => 2, 'price' => 99]);
        $subscription = Subscription::create(['user_id' => $user->id, 'bundle_id' => $current->id,
            'bundle_name' => $current->name_en, 'start_date' => today(), 'end_date' => today()->addMonth(),
            'status' => 'active', 'is_active' => true]);
        $policy = app(\App\Services\SubscriptionChangePolicy::class);
        $this->assertFalse($policy->canSelect($subscription, $this->bundle(['sort_order' => 1, 'price' => 199])));
        $this->assertTrue($policy->canSelect($subscription, $this->bundle(['sort_order' => 3, 'price' => 49])));
        $this->assertFalse($policy->canSelect($subscription, $this->bundle(['sort_order' => 2, 'price' => 49])));
        $this->assertTrue($policy->canSelect($subscription, $this->bundle(['sort_order' => 2, 'price' => 199])));
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
