<?php

use App\Models\User;
use App\Modules\Settings\Authentication\PasswordForm;
use App\Routes\Auth;
use App\View\DataModels\Avatar;
use App\View\DataModels\SettingsCard;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laravel\Head\Facades\Head;

$User = auth()->user();
$OauthProviders = $User instanceof User ? $User->oauthProviders : collect();
$Passkeys = $User instanceof User ? $User->passkeys()->latest()->get() : collect();
$passwordConfirmed = Date::now()->unix() - (int) session('auth.password_confirmed_at', 0)
    < (int) config('auth.password_timeout', 10800);
$status = session('status');
$statusMessage = match ($status) {
    'two-factor-authentication-enabled' => 'Two-factor authentication setup started.',
    'two-factor-authentication-confirmed' => 'Two-factor authentication enabled.',
    'two-factor-authentication-disabled' => 'Two-factor authentication disabled.',
    'recovery-codes-generated' => 'New recovery codes generated.',
    'passkey-registered' => 'Passkey registered.',
    'passkey-deleted' => 'Passkey deleted.',
    default => is_string($status) ? $status : null,
};

Head::title('Security')
    ->description('Review your sign-in methods and update your password.')
    ->hiddenFromRobots();
?>
<x-settings-card :settingsCard="[SettingsCard::title => 'Security']">
    <x-status-toast :statusToast="['message' => $statusMessage]"/>

    <section aria-labelledby="password-heading" data-password-settings>
        <h2 id="password-heading" class="text-lg font-semibold">Password</h2>
        <p class="text-sm text-base-content/70">
            Confirm your current password to choose a new one.
        </p>
        <form class="mt-2 space-y-4" method="POST" action="{{Auth::settingsSecurity->value}}">
            @csrf
            <input type="text" name="username" autocomplete="username" value="{{auth()->user()?->email}}" readonly hidden>
            <x-text-input :textInput="PasswordForm::textInput(PasswordForm::current_password)"/>
            <x-text-input :textInput="PasswordForm::textInput(PasswordForm::password)"/>
            <x-text-input :textInput="PasswordForm::textInput(PasswordForm::password_confirmation)"/>
            <button class="btn btn-primary">Update Password</button>
        </form>
    </section>

    <div class="divider"></div>

    <section aria-labelledby="two-factor-heading" data-two-factor-settings>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 id="two-factor-heading" class="text-lg font-semibold">Two-factor authentication</h2>
                    @if($User?->hasEnabledTwoFactorAuthentication())
                        <span class="badge badge-success badge-sm" data-two-factor-enabled>Enabled</span>
                    @else
                        <span class="badge badge-ghost badge-sm">Disabled</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-base-content/70">
                    Add an authenticator app as an extra layer of security.
                </p>
            </div>

            @if($User?->hasEnabledTwoFactorAuthentication())
                <form method="POST" action="{{route('two-factor.disable')}}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-error btn-outline btn-sm">Disable</button>
                </form>
            @elseif($User?->two_factor_secret === null)
                <form method="POST" action="{{route('two-factor.enable')}}">
                    @csrf
                    <button class="btn btn-primary btn-sm">Enable</button>
                </form>
            @endif
        </div>

        @if($User?->two_factor_secret !== null && ! $User->hasEnabledTwoFactorAuthentication())
                <div class="mt-5 rounded-box border border-base-300 p-4" data-two-factor-setup>
                <h3 class="font-semibold">Finish setup</h3>
                <p class="mt-1 text-sm text-base-content/70">
                    Scan this QR code with your authenticator app, then enter its six-digit code.
                </p>
                <div class="my-4 w-fit overflow-hidden rounded-box bg-white p-3">
                    {!! $User->twoFactorQrCodeSvg() !!}
                </div>
                <form class="flex max-w-sm flex-col gap-3 lg:flex-row" method="POST" action="{{route('two-factor.confirm')}}">
                    @csrf
                    <input class="input grow @error('code', 'confirmTwoFactorAuthentication') input-error @enderror"
                           name="code" inputmode="numeric" autocomplete="one-time-code" required
                           aria-label="Authentication code" placeholder="123456">
                    <button class="btn btn-primary">Confirm</button>
                </form>
                @error('code', 'confirmTwoFactorAuthentication')
                <p class="mt-2 text-sm text-error">{{$message}}</p>
                @enderror
            </div>
        @elseif($User?->hasEnabledTwoFactorAuthentication())
            <div class="mt-5 rounded-box border border-base-300 p-4" data-recovery-codes>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="font-semibold">Recovery codes</h3>
                        <p class="mt-1 text-sm text-base-content/70">
                            Store these somewhere safe. Each code can be used once if you lose your authenticator.
                        </p>
                    </div>
                    <form method="POST" action="{{route('two-factor.regenerate-recovery-codes')}}">
                        @csrf
                        <button class="btn btn-outline btn-sm">Generate new codes</button>
                    </form>
                </div>
                <ul class="mt-4 grid gap-2 font-mono text-sm lg:grid-cols-2">
                    @foreach($User->recoveryCodes() as $recoveryCode)
                        <li class="rounded bg-base-200 px-3 py-2" title="{{$recoveryCode}}">{{$recoveryCode}}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <div class="divider"></div>

    <section aria-labelledby="passkeys-heading" data-passkey-settings>
        <div>
            <h2 id="passkeys-heading" class="text-lg font-semibold">Passkeys</h2>
            <p class="mt-1 text-sm text-base-content/70">
                Sign in securely with Face ID, Touch ID, Windows Hello, or a security key.
            </p>
        </div>

        <div class="mt-4 overflow-hidden rounded-box border border-base-300">
            @forelse($Passkeys as $Passkey)
                <div class="flex flex-col gap-3 border-b border-base-300 p-4 last:border-b-0 lg:flex-row lg:items-center lg:justify-between" data-passkey>
                    <div>
                        <p class="font-medium" title="{{$Passkey->name}}">{{$Passkey->name}}</p>
                        <p class="text-sm text-base-content/60" title="{{$Passkey->authenticator ?? 'Passkey'}} · Added {{$Passkey->created_at?->diffForHumans()}}">
                            {{$Passkey->authenticator ?? 'Passkey'}} · Added {{$Passkey->created_at?->diffForHumans()}}
                        </p>
                    </div>
                    @if($passwordConfirmed)
                        <form method="POST" action="{{route('passkey.destroy', $Passkey)}}">
                            @csrf
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="btn btn-error btn-outline btn-sm">Remove</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="p-4 text-sm text-base-content/70">No passkeys registered.</p>
            @endforelse
        </div>

        @if($passwordConfirmed)
            <div class="mt-4 flex max-w-lg flex-col gap-3 lg:flex-row">
                <input class="input grow" type="text" maxlength="255" autocomplete="off"
                       placeholder="Passkey name, e.g. MacBook Pro" aria-label="Passkey name" data-passkey-name>
                <button type="button" class="btn btn-primary" data-passkey-register>Add passkey</button>
            </div>
            <p class="mt-2 hidden text-sm text-error" data-passkey-error></p>
        @else
            <a class="btn btn-primary btn-sm mt-4" href="{{Auth::passkeyManagementConfirm->value}}" data-passkey-confirm-password>
                Confirm password to manage passkeys
            </a>
        @endif
    </section>

    <div class="divider"></div>

    <section aria-labelledby="sign-in-methods-heading" data-sign-in-methods>
        <div>
            <h2 id="sign-in-methods-heading" class="text-lg font-semibold">Sign in methods</h2>
            <p class="mt-1 text-sm text-base-content/70">Accounts you can use to sign in.</p>
        </div>

        <div class="mt-4 overflow-hidden rounded-box border border-base-300">
            @forelse($OauthProviders as $OauthProvider)
                <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between" data-oauth-provider>
                    <div class="flex min-w-0 items-center gap-3">
                        <x-avatar :avatar="[Avatar::name => $OauthProvider->name, Avatar::picture => $OauthProvider->picture, Avatar::size => 'w-11']"/>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium" title="{{Str::headline($OauthProvider->provider_id->value)}}">{{Str::headline($OauthProvider->provider_id->value)}}</span>
                                @if($OauthProvider->verified_email)
                                    <span class="badge badge-success badge-sm">Verified</span>
                                @endif
                            </div>
                            <p class="truncate text-sm text-base-content/70" title="{{$OauthProvider->name}}">{{$OauthProvider->name}}</p>
                        </div>
                    </div>

                    <dl class="grid gap-1 text-sm lg:text-right">
                        <div>
                            <dt class="sr-only">Email</dt>
                            <dd title="{{$OauthProvider->email}}">{{$OauthProvider->email}}</dd>
                        </div>
                        @if($OauthProvider->hd !== null)
                            <div>
                                <dt class="sr-only">Hosted domain</dt>
                                <dd class="text-base-content/60" title="{{$OauthProvider->hd}}">{{$OauthProvider->hd}}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @empty
                <p class="p-4 text-sm text-base-content/70" data-oauth-providers-empty>No connected providers.</p>
            @endforelse
        </div>
    </section>

</x-settings-card>
