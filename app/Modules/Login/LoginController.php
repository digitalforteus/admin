<?php

namespace App\Modules\Login;

use App\Models\User;
use App\Routes\Web;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

readonly class LoginController
{
    public function __invoke(): RedirectResponse
    {
        $LoginRequest = LoginRequest::from(request()->all());
        $Validator = Validator::make(...$LoginRequest->validator());

        $credentials = $Validator->validate();
        $User = User::query()->where(LoginRequest::email, $credentials[LoginRequest::email])->first();

        if ($User instanceof User
            && Hash::check($credentials[LoginRequest::password], $User->password)
            && $User->hasEnabledTwoFactorAuthentication()) {
            request()->session()->put([
                'login.id' => $User->getKey(),
                'login.remember' => $LoginRequest->remember_token,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($User);

            return redirect()->route('two-factor.login');
        }

        if (Auth::attempt($credentials, $LoginRequest->remember_token)) {
            request()->session()->regenerate();

            return redirect()->intended(Web::home->value);
        }

        throw ValidationException::withMessages([
            LoginForm::email => [trans('auth.failed')],
        ]);
    }
}
