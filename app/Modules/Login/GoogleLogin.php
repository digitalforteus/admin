<?php

namespace App\Modules\Login;

use App\Events\UserRegistered;
use App\Helpers\OauthProviderId;
use App\Helpers\SessionKey;
use App\Models\OauthProvider;
use App\Models\User;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

readonly class GoogleLogin
{
    public function login(GoogleUser $GoogleUser, mixed $rawPayload = null): User
    {
        $User = User::query()->getConnection()->transaction(function () use ($GoogleUser, $rawPayload): User {
            $OauthProvider = OauthProvider::query()->firstOrNew([
                OauthProviders::provider_id->value => OauthProviderId::google->value,
                OauthProviders::sub->value => $GoogleUser->sub,
            ]);

            $User = $OauthProvider->exists ? $OauthProvider->user : User::query()->firstOrCreate(
                [Users::email->value => $GoogleUser->email],
                [
                    Users::name->value => $GoogleUser->name ?: Str::before($GoogleUser->email, '@'),
                    Users::password->value => Str::random(64),
                ],
            );

            if (! $User->hasVerifiedEmail()) {
                $User->markEmailAsVerified();
            }

            $oauth_data = array_merge($GoogleUser->toArray(), [
                OauthProviders::user_id->value => $User->id,
                OauthProviders::provider_id->value => OauthProviderId::google->value,
                OauthProviders::payload->value => $rawPayload !== null ? json_encode($rawPayload) : null,
            ]);

            OauthProvider::query()->upsert(
                [$oauth_data],
                [OauthProviders::sub->value],
                array_diff(array_keys($oauth_data), [OauthProviders::sub->value]),
            );

            return $User;
        });

        Auth::login($User);
        request()->session()->regenerate();
        request()->session()->put(SessionKey::user_picture->value, $GoogleUser->picture);

        if ($User->wasRecentlyCreated) {
            request()->session()->flash(SessionKey::sign_up_method->value, 'Google');
            event(new Registered($User));
            UserRegistered::dispatch($User);
        }

        return $User;
    }
}
