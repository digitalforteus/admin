<?php

namespace App\Modules\Login;

use App\Helpers\SocialiteDriver;
use App\Routes\Web;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

readonly class GitHubCallbackController
{
    public function __invoke(GitHubLogin $GitHubLogin): RedirectResponse
    {
        try {
            $github_user = Socialite::driver(SocialiteDriver::github->value)->user();
        } catch (InvalidStateException) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'Your GitHub sign-in session expired. Please try again.',
            ]);
        } catch (ClientException $e) {
            $error_message = 'GitHub authentication failed. Please try again.';
            if ($e->getResponse()->getStatusCode() === 401) {
                $error_message = 'GitHub credentials are invalid. Please check your GitHub app configuration.';
            }

            return redirect(Web::login->value)->withErrors([
                LoginForm::email => $error_message,
            ]);
        } catch (Throwable) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'An unexpected error occurred during GitHub sign-in. Please try again.',
            ]);
        }

        if (! $github_user instanceof AbstractUser) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'GitHub did not provide a valid user account.',
            ]);
        }

        $GitHubUser = GitHubUser::from($github_user->getRaw());

        if (! $GitHubUser->hasVerifiedEmail()) {
            return redirect(Web::login->value)->withErrors([
                LoginForm::email => 'Your GitHub account does not have a public verified email. Please verify your email in GitHub settings.',
            ]);
        }

        $GitHubLogin->login($GitHubUser, $github_user->getRaw());

        return redirect()->intended(Web::home->value);
    }
}
