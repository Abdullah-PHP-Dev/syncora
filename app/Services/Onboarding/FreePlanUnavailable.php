<?php

namespace App\Services\Onboarding;

use RuntimeException;

/** No active is_free plan exists in the administrator plan catalog. */
class FreePlanUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Registration blocked: no active free plan is configured (Plans > is_free + is_active).');
    }
}
