<?php

namespace Tests\Feature\Payments;

use App\Models\WalletTransaction;
use App\Services\PaymentManager;
use App\Services\Payments\Gateways\InstallmentGatewayManager;
use App\Services\Payments\InstallmentPayment;
use App\Services\Payments\TabbyPayment;
use App\Services\Payments\TamaraPayment;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallmentPaymentTest extends TestCase
{
    public static function providers(): array
    {
        return [['tamara', TamaraPayment::class], ['tabby', TabbyPayment::class]];
    }

    #[DataProvider('providers')]
    public function test_selected_provider_receives_payment_and_verification(string $gateway, string $class): void
    {
        $transaction = Mockery::mock(WalletTransaction::class);
        $transaction->shouldReceive('update')->once()->with([
            'payment_method' => 'installment', 'payment_gateway' => $gateway,
        ])->andReturn(true);
        $data = ['gateway' => $gateway, 'transaction' => $transaction, 'amount' => 100];
        $provider = Mockery::mock($class);
        $provider->shouldReceive('pay')->once()->with($data)->andReturn(['status' => 'pending']);
        $provider->shouldReceive('verify')->once()->with(['gateway' => $gateway])->andReturn(true);
        $this->app->instance($class, $provider);

        $driver = app(PaymentManager::class)->driver('installment');
        $this->assertInstanceOf(InstallmentPayment::class, $driver);
        $this->assertSame(['status' => 'pending'], $driver->pay($data));
        $this->assertTrue($driver->verify(['gateway' => $gateway]));
    }

    public function test_provider_selection_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InstallmentPayment::class)->pay([]);
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InstallmentGatewayManager::class)->driver('tap');
    }

    public function test_unconfigured_tabby_does_not_route_to_tamara(): void
    {
        $driver = app(InstallmentGatewayManager::class)->driver('tabby');
        $this->assertInstanceOf(TabbyPayment::class, $driver);
        $this->assertSame('failed', $driver->pay([])['status']);
        $this->assertFalse($driver->verify([]));
    }
}
