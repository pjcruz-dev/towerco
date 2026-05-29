<?php

return [

    'guard' => 'web',

    'middleware' => ['web'],

    'path' => env('PASSPORT_PATH', 'oauth'),

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    'connection' => env('PASSPORT_CONNECTION'),
];
