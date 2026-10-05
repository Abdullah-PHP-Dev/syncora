<?php

namespace Tests\Unit;

use App\Services\Payments\TamaraPayment;
use PHPUnit\Framework\TestCase;

class TamaraPaymentTest extends TestCase
{
    public function test_verification_requires_a_gateway_reference(): void
    {
        $this->assertFalse((new TamaraPayment())->verify([]));
        $this->assertFalse((new TamaraPayment())->verify(['gateway_reference' => '']));
    }
}
