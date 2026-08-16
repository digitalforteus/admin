<?php

namespace App\Modules\Register;

use App\Helpers\SessionKey;
use App\Models\User;
use App\Routes\Auth as AuthRoute;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

readonly class RegisterController
{
    public function __invoke(): RedirectResponse
    {
        $RegisterRequest = RegisterRequest::from(request()->all());
        $key = 'register:'.($RegisterRequest->email ?? '');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                RegisterRequest::email => 'Too many registration attempts. Please try again later.',
            ]);
        }

        RateLimiter::hit($key);

        $Validator = Validator::make(...$RegisterRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($RegisterRequest->toArray());
        }

        User::query()->getConnection()->transaction(static function () use ($RegisterRequest): void {
            $User = User::query()->create([
                RegisterRequest::name => $RegisterRequest->name,
                RegisterRequest::email => $RegisterRequest->email,
                RegisterRequest::password => Hash::make($RegisterRequest->password),
            ]);

            Auth::login($User);

            event(new Registered($User));
        });

        request()->session()->flash(SessionKey::sign_up_method->value, 'Email');

        return redirect(AuthRoute::verificationNotice->value);
    }
}
