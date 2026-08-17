<?php

namespace App\Modules\Login;

use App\Helpers\SocialiteDriver;
use App\Routes\Web;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

readonly class GoogleCallbackController
{
    public function __invoke(GoogleLogin $GoogleLogin): RedirectResponse
    {
        try {
            $google_user = Socialite::driver(SocialiteDriver::google->value)->user();
        } catch (InvalidStateException) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'Your Google sign-in session expired. Please try again.',
            ]);
        }

        if (! $google_user instanceof AbstractUser) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'Google did not provide a verified email address.',
            ]);
        }

        $GoogleUser = GoogleUser::from($google_user->getRaw());

        if (! $GoogleUser->hasVerifiedEmail()) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'Google did not provide a verified email address.',
            ]);
        }

        $GoogleLogin->login($GoogleUser);

        return redirect()->intended(Web::home->value);
    }
}
