<?php

namespace App\Services\AdServices;

/** A LinkedIn adAnalytics failure; $reconnect when the account must be connected again. */
class LinkedinReportingException extends \RuntimeException
{
    public function __construct(string $message, public bool $reconnect = false)
    {
        parent::__construct($message);
    }
}
