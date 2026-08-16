<?php

use App\Routes\MiddlewareTag;
use Laravel\Fortify\Features;

$url = config('app.url');

if (! is_string($url)) {
    throw new RuntimeException('The application URL must be a string.');
}

return [
    'guard' => 'web',
    'middleware' => [MiddlewareTag::web->value],
    'auth_middleware' => MiddlewareTag::auth->value,
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'views' => false,
    'home' => '/',
    'features' => [
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],
    'redirects' => [
        'email-verification' => '/',
        'password-reset' => '/login',
    ],
    'passkeys' => [
        'relying_party_id' => parse_url($url, PHP_URL_HOST) ?: 'localhost',
        'allowed_origins' => [$url],
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],
];
