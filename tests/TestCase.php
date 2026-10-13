<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings memoizes all settings in a static property; without
        // this, settings read in one test class leak into the next.
        \App\Support\Settings::forgetRequestCache();
    }
}
