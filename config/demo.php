<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Demo mode
|--------------------------------------------------------------------------
|
| Turns the installation into a public demo: visitors log in with a shared
| demo user, may change any data except users, and everything is wiped and
| restored from the demo archive every day. Never enable it on a real
| installation: `demo:reset` drops the whole database.
|
*/

return [
    'enabled' => (bool) env('DEMO_MODE', false),

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo'),
        'email' => env('DEMO_USER_EMAIL', 'demo@trama.test'),
        'password' => env('DEMO_USER_PASSWORD', 'demo'),
    ],

    // A tenant export (.zip) or a directory of them, relative to base_path().
    'archive' => env('DEMO_ARCHIVE', 'database/demo'),

    'reset_time' => env('DEMO_RESET_TIME', '03:00'),
    'timezone' => env('DEMO_TIMEZONE', 'Europe/Rome'),
];
