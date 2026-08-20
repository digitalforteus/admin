<?php

declare(strict_types=1);

use App\Routes\ApiRoute;
use App\Routes\Web;

return [
    'paths' => [
        ltrim(ApiRoute::prefix, '/').'/*',
        'sanctum/csrf-cookie',
        ltrim(Web::openapi->value, '/'),
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
