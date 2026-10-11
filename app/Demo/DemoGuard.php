<?php

namespace App\Demo;

/**
 * Fails closed: demo commands only run where DEMO_MODE is explicitly on and a
 * usable DEMO_PASSWORD is set. APP_ENV is deliberately ignored — Railway
 * staging runs with APP_ENV=production.
 */
class DemoGuard
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function password(): string
    {
        if (config('demo.enabled') !== true) {
            throw new DemoRefusedException('DEMO_MODE is not enabled. Set DEMO_MODE=true on this environment to allow demo commands.');
        }

        $password = config('demo.password');
        if (! is_string($password) || mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new DemoRefusedException('DEMO_PASSWORD must be set to at least 12 characters.');
        }

        return $password;
    }
}
