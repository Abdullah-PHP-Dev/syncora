<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentAttemptLimitTest extends TestCase
{
    public function test_fifth_failed_attempt_returns_a_validation_message_for_checkout(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('status');
            $table->timestamps();
        });
        $user = new User();
        $user->id = 42;
        Route::post('/test/payment-attempts', function () use ($user) {
            $method = new \ReflectionMethod(SubscriptionService::class, 'validatePaymentAttempts');
            $method->invoke(app(SubscriptionService::class), $user);
            return response()->json(['success' => true]);
        });
        foreach (['pending', 'rejected', 'pending', 'rejected'] as $status) {
            DB::table('wallet_transactions')->insert(['seller_id' => 42, 'status' => $status,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $this->postJson('/test/payment-attempts')->assertOk();
        DB::table('wallet_transactions')->insert(['seller_id' => 42, 'status' => 'rejected',
            'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/test/payment-attempts')->assertStatus(422)
            ->assertJsonPath('message', 'Too many failed payment attempts. Please try again tomorrow.')
            ->assertJsonValidationErrors('payment_method');
        $this->assertSame(5, DB::table('wallet_transactions')->count());
    }
}
