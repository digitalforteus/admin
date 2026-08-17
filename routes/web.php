<?php

use App\Modules\Fortify\NewPasswordController;
use App\Modules\Fortify\PasswordResetLinkController;
use App\Modules\Fortify\TwoFactorLoginController;
use App\Modules\Llms\LlmsController;
use App\Modules\Login\GoogleCallbackController;
use App\Modules\Login\GoogleOneTapController;
use App\Modules\Login\GoogleRedirectController;
use App\Modules\Login\LoginController;
use App\Modules\Logout\LogoutController;
use App\Modules\Passkeys\PasskeyLoginController;
use App\Modules\Passkeys\PasskeyLoginOptionsController;
use App\Modules\Register\RegisterController;
use App\Modules\Robots\RobotsController;
use App\Modules\Sitemap\SitemapController;
use App\Modules\Sitemap\SitemapPageController;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use Illuminate\Support\Facades\Route;

Route::post(Web::register->value, RegisterController::class)->middleware([MiddlewareTag::throttleFivePerMinute->value]);
Route::post(Web::login->value, LoginController::class)->middleware([MiddlewareTag::throttleFivePerMinute->value]);
Route::post(Web::twoFactorChallenge->value, TwoFactorLoginController::class)
    ->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleFivePerMinute->value])
    ->name('two-factor.login.store');
Route::get(Web::passkeyLoginOptions->value, PasskeyLoginOptionsController::class)
    ->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleSixPerMinute->value])
    ->name('passkey.login-options');
Route::post(Web::passkeyLogin->value, PasskeyLoginController::class)
    ->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleSixPerMinute->value])
    ->name('passkey.login');
Route::post(Web::forgotPassword->value, PasswordResetLinkController::class)
    ->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleFivePerMinute->value])
    ->name('password.email');
Route::post(Web::resetPasswordUpdate->value, NewPasswordController::class)
    ->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleFivePerMinute->value])
    ->name('password.update');
Route::get(Web::googleRedirect->value, GoogleRedirectController::class)->middleware([MiddlewareTag::throttleFivePerMinute->value]);
Route::get(Web::googleCallback->value, GoogleCallbackController::class)->middleware([MiddlewareTag::throttleFivePerMinute->value]);
Route::post(Web::googleOneTap->value, GoogleOneTapController::class)->middleware([MiddlewareTag::guest->value, MiddlewareTag::throttleFivePerMinute->value]);
Route::get(Web::logout->value, LogoutController::class)->middleware([MiddlewareTag::throttleFivePerMinute->value]);
Route::get(Web::llms->value, LlmsController::class);
Route::get(Web::robots->value, RobotsController::class);
Route::get(Web::sitemap->value, SitemapController::class);
Route::get(Web::sitemapPage->value, SitemapPageController::class)->whereNumber('page');
