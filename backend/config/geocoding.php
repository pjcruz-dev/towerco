<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Geocoding driver
    |--------------------------------------------------------------------------
    |
    | auto     — Mapbox when MAPBOX_ACCESS_TOKEN is set, otherwise Nominatim
    | mapbox   — require Mapbox token
    | nominatim — OpenStreetMap Nominatim (local/dev friendly; respect usage policy)
    |
    */

    'driver' => env('GEOCODING_DRIVER', 'auto'),

    'country' => env('GEOCODING_COUNTRY', 'ph'),

    'timeout_seconds' => max(3, (int) env('GEOCODING_TIMEOUT_SECONDS', 8)),

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN'),
        'base_url' => env('MAPBOX_GEOCODING_BASE_URL', 'https://api.mapbox.com/geocoding/v5/mapbox.places'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env(
            'GEOCODING_USER_AGENT',
            'TowerOS/1.0 (geocoding; '.env('APP_URL', 'http://localhost').')',
        ),
        'email' => env('GEOCODING_CONTACT_EMAIL'),
    ],

];
