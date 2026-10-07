<?php

/*
| Web Push (SPEC §11: the nightly summary). Generate the keys once with
| `php artisan webpush:vapid` and put them in .env. Without them the app
| works but sends no notifications.
*/

return [
    'public_key' => env('WEBPUSH_PUBLIC_KEY'),
    'private_key' => env('WEBPUSH_PRIVATE_KEY'),
    // Who push services can contact about this sender: a mailto: or https: URL.
    'subject' => env('WEBPUSH_SUBJECT', env('APP_URL', 'mailto:admin@example.com')),
];
