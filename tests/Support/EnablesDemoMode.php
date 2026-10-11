<?php

namespace Tests\Support;

trait EnablesDemoMode
{
    protected const DEMO_TEST_PASSWORD = 'demo-password-for-tests';

    protected function enableDemoMode(string $password = self::DEMO_TEST_PASSWORD): void
    {
        config(['demo.enabled' => true, 'demo.password' => $password]);
    }
}
