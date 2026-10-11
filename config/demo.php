<?php

return [
    /*
     * Master switch for the demo:seed and demo:reset commands. Read through
     * config (not env()) so it survives config:cache on Railway. Only an
     * explicit true value (true/1/on/yes) enables it; anything else,
     * including "off" or "disabled", keeps it off.
     */
    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),

    /*
     * Password applied to every demo account. Required, at least 12
     * characters. Never printed or logged.
     */
    'password' => env('DEMO_PASSWORD'),
];
