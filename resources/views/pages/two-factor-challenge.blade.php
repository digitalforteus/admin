<?php

use App\View\DataModels\AuthCard;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('two-factor.login');

Head::title('Two-factor authentication')
    ->description('Complete two-factor authentication to sign in.')
    ->hiddenFromRobots();

render(function (View $view) {
    if (! session()->has('login.id')) {
        return redirect()->route('login');
    }

    return $view;
});
?>
<x-auth-card :authCard="[AuthCard::title => 'Authentication code']">
    <p class="mb-4 text-sm text-base-content/70">
        Enter the code from your authenticator app, or use one of your recovery codes.
    </p>

    <form class="space-y-4" method="POST" action="{{route('two-factor.login.store')}}">
        @csrf
        <x-field :fieldset="['legend' => 'Authentication code']">
            <input class="input w-full @error('code') input-error @enderror"
                   name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
        </x-field>
        <button class="btn btn-primary w-full">Sign in</button>
    </form>

    <div class="divider">or</div>

    <form class="space-y-4" method="POST" action="{{route('two-factor.login.store')}}">
        @csrf
        <x-field :fieldset="['legend' => 'Recovery code']">
            <input class="input w-full @error('recovery_code') input-error @enderror"
                   name="recovery_code" autocomplete="one-time-code">
        </x-field>
        <button class="btn btn-outline w-full">Use recovery code</button>
    </form>
</x-auth-card>
