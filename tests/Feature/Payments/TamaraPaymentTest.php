<?php

namespace Tests\Feature\Payments;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\TamaraOrder;
use App\Models\User;
use App\Services\Payments\TamaraPayment;
use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TamaraPaymentTest extends TestCase
{
    private const BASE = 'https://api-sandbox.tamara.co';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payment.tamara.api_token' => 'sandbox-test-token',
            'services.payment.tamara.base_url' => self::BASE,
            'services.payment.tamara.notification_token' => str_repeat('s', 32)]);
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->string('mobile'); $t->timestamps(); });
        Schema::create('bundles', function (Blueprint $t) { $t->id(); $t->string('name_en'); $t->string('name_ar')->nullable(); $t->string('currency'); $t->decimal('price', 12, 2); $t->json('meta')->nullable(); $t->timestamps(); });
        (require database_path('migrations/2026_08_17_234708_create_subscriptions_table.php'))->up();
        (require database_path('migrations/2026_08_18_000800_create_subscription_cycles_table.php'))->up();
        (require database_path('migrations/2026_10_05_000001_create_tamara_orders_table.php'))->up();
    }

    private function checkout(string $cycle = 'monthly'): array
    {
        $user = User::create(['name' => 'Test Seller', 'email' => 'seller@example.com', 'mobile' => '+966500000000']);
        $bundle = Bundle::create(['name_en' => 'Pro', 'currency' => 'SAR', 'price' => 100]);
        return ['user' => $user, 'bundle' => $bundle, 'cycle' => $cycle, 'amount' => $cycle === 'yearly' ? 1200 : 100];
    }

    private function order(string $cycle = 'monthly'): TamaraOrder
    {
        $data = $this->checkout($cycle);
        Http::fake([self::BASE.'/checkout' => Http::response(['order_id' => 'gateway-1', 'checkout_id' => 'session-1',
            'status' => 'new',
            'checkout_url' => 'https://checkout-sandbox.tamara.co/session-1'])]);
        $result = app(TamaraPayment::class)->pay($data);
        $this->assertSame('pending', $result['status']);
        return TamaraOrder::findOrFail($result['transaction_id']);
    }

    private function remote(TamaraOrder $order, string $status): array
    {
        return ['order_id' => $order->gateway_order_id, 'order_reference_id' => $order->id,
            'status' => $status, 'total_amount' => ['amount' => (float) $order->amount, 'currency' => $order->currency]];
    }

    public function test_new_checkout_returns_url_for_browser_redirect(): void
    {
        $order = $this->order();
        $this->assertSame('new', $order->status);
        $this->assertSame('https://checkout-sandbox.tamara.co/session-1', $order->checkout_url);
        $this->assertSame('gateway-1', $order->gateway_order_id);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, Subscription::count());
    }

    public function test_checkout_sends_customer_and_server_amount_and_signed_returns(): void
    {
        $order = $this->order('yearly');
        Http::assertSent(fn ($r) => $r->url() === self::BASE.'/checkout' &&
            $r['total_amount']['amount'] === 1200.0 && $r['consumer']['phone_number'] === '+966500000000' &&
            str_contains($r['merchant_url']['success'], '/payments/tamara/return/'.$order->id) &&
            str_contains($r['merchant_url']['success'], 'signature='));
        $this->assertNull($order->paid_at);
        $this->assertSame(0, Subscription::count());
    }

    public function test_missing_credentials_and_invalid_mobile_do_not_contact_gateway(): void
    {
        $data = $this->checkout();
        config(['services.payment.tamara.api_token' => null]);
        $this->assertSame('failed', app(TamaraPayment::class)->pay($data)['status']);
        config(['services.payment.tamara.api_token' => 'test']);
        $data['user']->mobile = '';
        $this->assertSame('failed', app(TamaraPayment::class)->pay($data)['status']);
        Http::assertNothingSent();
        $this->assertSame(0, TamaraOrder::count());
    }

    public function test_failed_checkout_is_recorded_without_redirect(): void
    {
        Http::fake([self::BASE.'/checkout' => Http::response(['message' => 'declined'], 400)]);
        $response = app(TamaraPayment::class)->pay($this->checkout());
        $this->assertSame('failed', $response['status']);
        $this->assertArrayNotHasKey('redirect_url', $response);
        $this->assertSame('failed', TamaraOrder::first()->status);
    }

    public function test_approved_order_is_authorised_captured_and_activated_once(): void
    {
        $order = $this->order('yearly');
        Http::fake([
            self::BASE.'/orders/gateway-1' => Http::sequence()
                ->push($this->remote($order, 'approved'))->push($this->remote($order, 'authorised'))
                ->push($this->remote($order, 'fully_captured')),
            self::BASE.'/orders/gateway-1/authorise' => Http::response(['status' => 'authorised']),
            self::BASE.'/payments/capture' => Http::response(['capture_id' => 'capture-1', 'status' => 'fully_captured']),
        ]);
        $service = app(TamaraPayment::class);
        $this->assertTrue($service->synchronize($order));
        $this->assertTrue($service->synchronize($order));
        $this->assertSame(1, SubscriptionCycle::count());
        $sub = Subscription::first();
        $this->assertSame('active', $sub->status);
        $this->assertSame(12, $sub->billing_period);
        $this->assertTrue($sub->end_date->equalTo($sub->start_date->copy()->addMonthsNoOverflow(12)));
        $this->assertNotNull($order->fresh()->paid_at);
        Http::assertSent(fn ($r) => $r->url() === self::BASE.'/payments/capture' && $r['total_amount']['amount'] === 1200.0);
    }

    public function test_auto_captured_order_activates_without_another_capture(): void
    {
        $order = $this->order();
        Http::fake([self::BASE.'/orders/gateway-1' => Http::response($this->remote($order, 'fully_captured'))]);
        $this->assertTrue(app(TamaraPayment::class)->synchronize($order));
        Http::assertNotSent(fn ($r) => $r->url() === self::BASE.'/payments/capture');
    }

    public function test_declined_and_pending_orders_do_not_activate(): void
    {
        $order = $this->order();
        Http::fake([self::BASE.'/orders/gateway-1' => Http::sequence()
            ->push($this->remote($order, 'new'))->push($this->remote($order, 'declined'))]);
        $this->assertFalse(app(TamaraPayment::class)->synchronize($order));
        $this->assertFalse(app(TamaraPayment::class)->synchronize($order));
        $this->assertSame('declined', $order->fresh()->status);
        $this->assertSame(0, Subscription::count());
    }

    public function test_amount_mismatch_never_activates(): void
    {
        $order = $this->order();
        $remote = $this->remote($order, 'fully_captured');
        $remote['total_amount']['amount'] = 1;
        Http::fake([self::BASE.'/orders/gateway-1' => Http::response($remote)]);
        try {
            app(TamaraPayment::class)->synchronize($order);
            $this->fail('Mismatched payment accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, Subscription::count());
        }
    }

    public function test_capture_failure_is_retryable_without_activation(): void
    {
        $order = $this->order();
        Http::fake([self::BASE.'/orders/gateway-1' => Http::response($this->remote($order, 'authorised')),
            self::BASE.'/payments/capture' => Http::response([], 503)]);
        $token = JWT::encode(['exp' => time() + 300], str_repeat('s', 32), 'HS256');
        $this->withToken($token)->postJson('/api/payments/tamara/webhook', ['order_id' => 'gateway-1'])->assertStatus(503);
        $this->assertSame(0, Subscription::count());
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_webhook_rejects_forged_and_expired_tokens(): void
    {
        $this->postJson('/api/payments/tamara/webhook', ['order_id' => 'gateway-1'])->assertUnauthorized();
        foreach ([JWT::encode(['exp' => time() + 300], str_repeat('x', 32), 'HS256'),
            JWT::encode(['exp' => time() - 300], str_repeat('s', 32), 'HS256')] as $token) {
            $this->withToken($token)->postJson('/api/payments/tamara/webhook', ['order_id' => 'gateway-1'])->assertUnauthorized();
        }
        Http::assertNothingSent();
    }

    public function test_valid_webhook_uses_api_status_and_is_idempotent(): void
    {
        $order = $this->order();
        Http::fake([self::BASE.'/orders/gateway-1' => Http::response($this->remote($order, 'fully_captured'))]);
        $token = JWT::encode(['exp' => time() + 300], str_repeat('s', 32), 'HS256');
        for ($i = 0; $i < 2; $i++) {
            $this->withToken($token)->postJson('/api/payments/tamara/webhook', ['order_id' => 'gateway-1', 'status' => 'new'])->assertOk();
        }
        $this->assertSame(1, SubscriptionCycle::count());
    }


    public function test_return_requires_a_signature_and_the_payment_owner(): void
    {
        $order = $this->order();
        $owner = User::findOrFail($order->user_id);
        $this->actingAs($owner)->get('/payments/tamara/return/'.$order->id)->assertForbidden();
        $url = \Illuminate\Support\Facades\URL::signedRoute('payments.tamara.return', ['order' => $order->id, 'result' => 'success']);
        $this->get($url.'&orderId=gateway-1&status=approved')->assertOk()->assertSee('confirmation is processing');
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'mobile' => '+966500000001']);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->assertSame(0, Subscription::count());
    }

    public function test_order_reference_and_currency_mismatches_are_rejected(): void
    {
        $order = $this->order();
        foreach (['order_reference_id', 'currency'] as $field) {
            $remote = $this->remote($order, 'fully_captured');
            if ($field === 'currency') {
                $remote['total_amount']['currency'] = 'AED';
            } else {
                $remote[$field] = 'another-order';
            }
            Http::fake([self::BASE.'/orders/gateway-1' => Http::response($remote)]);
            try {
                app(TamaraPayment::class)->synchronize($order);
                $this->fail('Mismatched order accepted');
            } catch (\RuntimeException $e) {
                $this->assertSame(0, Subscription::count());
            }
        }
    }

    public function test_verify_rejects_empty_and_unknown_references(): void
    {
        $this->assertFalse(app(TamaraPayment::class)->verify([]));
        $this->assertFalse(app(TamaraPayment::class)->verify(['gateway_reference' => 'unknown']));
        Http::assertNothingSent();
    }
}
