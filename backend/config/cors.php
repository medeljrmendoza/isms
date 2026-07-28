<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // The exact origin(s) the React SPA is served from. Never use '*' here
    // while supports_credentials is true.
    'allowed_origins' => explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Sanctum's SPA cookie authentication to work cross-origin.
    'supports_credentials' => true,

];
