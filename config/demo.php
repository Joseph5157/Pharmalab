<?php

return [
    /*
     * Master switch for the demo:seed and demo:reset commands. Read through
     * config (not env()) so it survives config:cache on Railway.
     */
    'enabled' => (bool) env('DEMO_MODE', false),

    /*
     * Password applied to every demo account. Required, at least 12
     * characters. Never printed or logged.
     */
    'password' => env('DEMO_PASSWORD'),
];
