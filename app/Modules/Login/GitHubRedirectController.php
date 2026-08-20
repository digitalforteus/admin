<?php

namespace App\Modules\Login;

use App\Helpers\SocialiteDriver;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

readonly class GitHubRedirectController
{
    public function __invoke(): RedirectResponse
    {
        $response = Socialite::driver(SocialiteDriver::github->value)->redirect();
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
