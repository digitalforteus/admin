<?php

use App\Helpers\SvgName;
use App\Modules\Login\LoginForm;
use App\Routes\Web;
use App\View\DataModels\AuthCard;
use App\View\DataModels\Svg;
use Illuminate\View\View;
use Laravel\Head\Facades\Head;

use function Laravel\Folio\render;

Head::title('Login')
    ->description('Sign in to your account.')
    ->hiddenFromRobots();

render(function (View $view) {
    if (auth()->check()) {
        return redirect(Web::home->value);
    }

    return $view;
});
?>
<x-auth-card :authCard="[AuthCard::title => 'Login']">
    <form class="space-y-4" method="POST" action="{{Web::login->value}}" data-login-form>
        @csrf
        <x-text-input :textInput="LoginForm::textInput(LoginForm::email)"/>
        <x-text-input :textInput="LoginForm::textInput(LoginForm::password)"/>
        <div class="flex items-center justify-between gap-4">
            <label class="flex cursor-pointer items-center gap-2 text-sm" data-remember-login>
                <input type="checkbox"
                       name="{{LoginForm::remember_token}}"
                       value="1"
                       class="checkbox checkbox-primary checkbox-sm"
                       @checked(old(LoginForm::remember_token))
                />
                <span>Remember me</span>
            </label>
            <a href="{{Web::forgotPassword->value}}" class="link link-primary text-sm">Forgot password?</a>
        </div>
        <button class="btn btn-primary mt-4 w-full">Login</button>
    </form>
    <button type="button" class="btn btn-outline mt-3 w-full" data-passkey-login>
        Sign in with a passkey
    </button>
    <p class="mt-2 hidden text-center text-sm text-error" data-passkey-error></p>
    <div class="divider text-xs uppercase">or continue with</div>
    <a href="{{Web::googleRedirect->value}}" class="btn btn-outline w-full" data-google-login>
        <x-svg :svg="[Svg::name => SvgName::google, Svg::classname => 'size-5']"/>
        Google
    </a>
    <x-slot:footer>
        <a href="{{Web::register->value}}" class="link link-primary text-center p-3">Register</a>
    </x-slot:footer>
</x-auth-card>
