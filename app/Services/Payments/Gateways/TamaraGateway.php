<?php

namespace App\Services\Payments\Gateways;

use App\Services\Payments\TamaraPayment;

/** @deprecated Use TamaraPayment through InstallmentGatewayManager. */
class TamaraGateway extends TamaraPayment implements CardGatewayInterface {}
