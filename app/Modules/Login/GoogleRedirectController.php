<?php

namespace App\Modules\Login;

use App\Helpers\SocialiteDriver;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

readonly class GoogleRedirectController
{
    public function __invoke(): RedirectResponse
    {
        $response = Socialite::driver(SocialiteDriver::google->value)->redirect();
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
