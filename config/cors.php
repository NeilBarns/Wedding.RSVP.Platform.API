<?php

use App\Support\FrontendOrigins;

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => FrontendOrigins::resolve(
        env('FRONTEND_URLS'),
        env('FRONTEND_URL'),
        env('APP_ENV', 'production') === 'local',
    ),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
