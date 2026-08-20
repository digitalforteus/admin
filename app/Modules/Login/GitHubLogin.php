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

readonly class GitHubLogin
{
    public function login(GitHubUser $GitHubUser, mixed $rawPayload = null): User
    {
        $User = User::query()->getConnection()->transaction(function () use ($GitHubUser, $rawPayload): User {
            $OauthProvider = OauthProvider::query()->firstOrNew([
                OauthProviders::provider_id->value => OauthProviderId::github->value,
                OauthProviders::sub->value => $GitHubUser->id,
            ]);

            $User = $OauthProvider->exists ? $OauthProvider->user : User::query()->firstOrCreate(
                [Users::email->value => $GitHubUser->email],
                [
                    Users::name->value => $GitHubUser->getDisplayName(),
                    Users::password->value => Str::random(64),
                ],
            );

            if (! $User->hasVerifiedEmail()) {
                $User->markEmailAsVerified();
            }

            $oauth_data = [
                OauthProviders::user_id->value => $User->id,
                OauthProviders::provider_id->value => OauthProviderId::github->value,
                OauthProviders::sub->value => $GitHubUser->id,
                OauthProviders::name->value => $GitHubUser->getDisplayName(),
                OauthProviders::given_name->value => Str::before($GitHubUser->getDisplayName(), ' '),
                OauthProviders::family_name->value => Str::contains($GitHubUser->getDisplayName(), ' ')
                    ? Str::afterLast($GitHubUser->getDisplayName(), ' ')
                    : '',
                OauthProviders::picture->value => $GitHubUser->avatar_url ?? '',
                OauthProviders::email->value => $GitHubUser->email ?? '',
                OauthProviders::email_verified->value => $GitHubUser->hasVerifiedEmail(),
                OauthProviders::hd->value => null,
                OauthProviders::id->value => $GitHubUser->id,
                OauthProviders::verified_email->value => $GitHubUser->hasVerifiedEmail(),
                OauthProviders::link->value => null,
                OauthProviders::payload->value => $rawPayload !== null ? json_encode($rawPayload) : null,
            ];

            OauthProvider::query()->upsert(
                [$oauth_data],
                [OauthProviders::sub->value],
                array_diff(array_keys($oauth_data), [OauthProviders::sub->value]),
            );

            return $User;
        });

        Auth::login($User);
        request()->session()->regenerate();
        request()->session()->put(SessionKey::user_picture->value, $GitHubUser->avatar_url);

        if ($User->wasRecentlyCreated) {
            request()->session()->flash(SessionKey::sign_up_method->value, 'GitHub');
            event(new Registered($User));
            UserRegistered::dispatch($User);
        }

        return $User;
    }
}
