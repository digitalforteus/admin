<?php

use App\Modules\Fortify\EmailVerificationNotificationController;
use App\Modules\Fortify\RecoveryCodesRegenerateController;
use App\Modules\Fortify\TwoFactorConfirmController;
use App\Modules\Fortify\TwoFactorDisableController;
use App\Modules\Fortify\TwoFactorEnableController;
use App\Modules\Passkeys\PasskeyConfirmationController;
use App\Modules\Passkeys\PasskeyConfirmationOptionsController;
use App\Modules\Passkeys\PasskeyDestroyController;
use App\Modules\Passkeys\PasskeyRegistrationController;
use App\Modules\Passkeys\PasskeyRegistrationOptionsController;
use App\Modules\PasswordConfirmation\PasswordConfirmationController;
use App\Modules\Settings\Appearance\AppearanceController;
use App\Modules\Settings\Authentication\PasswordController;
use App\Modules\Settings\Credentials\TokenController;
use App\Modules\Settings\Credentials\TokenDestroyController;
use App\Modules\Settings\Credentials\TokenUpdateController;
use App\Modules\Settings\Profile\ProfileController;
use App\Modules\Settings\Profile\ProfilePictureController;
use App\Modules\Settings\Profile\ProfilePictureDestroyController;
use App\Modules\Settings\Sessions\SessionDestroyController;
use App\Modules\Settings\Sessions\SessionsDestroyController;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use Illuminate\Support\Facades\Route;

Route::post(Auth::verificationSend->value, EmailVerificationNotificationController::class)
    ->middleware(MiddlewareTag::throttleTenPerMinute->value)
    ->name('verification.send');

Route::middleware(MiddlewareTag::verified->value)->group(function () {
    Route::get(Auth::passkeyManagementConfirm->value, static fn () => redirect(Auth::settingsSecurity->value))
        ->middleware(MiddlewareTag::passwordConfirm->value)
        ->name('passkey.management.confirm');
    Route::get(Auth::passkeyConfirmOptions->value, PasskeyConfirmationOptionsController::class)
        ->middleware(MiddlewareTag::throttleTenPerMinute->value)
        ->name('passkey.confirm-options');
    Route::post(Auth::passkeyConfirm->value, PasskeyConfirmationController::class)
        ->middleware(MiddlewareTag::throttleTenPerMinute->value)
        ->name('passkey.confirm');
    Route::post(Auth::confirmPassword->value, PasswordConfirmationController::class)
        ->middleware(MiddlewareTag::throttleTenPerMinute->value);
    Route::post(Auth::settingsProfile->value, ProfileController::class);
    Route::post(Auth::settingsProfilePicture->value, ProfilePictureController::class);
    Route::delete(Auth::settingsProfilePicture->value, ProfilePictureDestroyController::class);
    Route::delete(Auth::settingsSessions->value, SessionsDestroyController::class);
    Route::delete(Auth::settingsSession->value, SessionDestroyController::class);
    Route::post(Auth::settingsSecurity->value, PasswordController::class);
    Route::middleware(MiddlewareTag::passwordConfirm->value)->group(function (): void {
        Route::post(Auth::twoFactorAuthentication->value, TwoFactorEnableController::class)
            ->name('two-factor.enable');
        Route::post(Auth::confirmedTwoFactorAuthentication->value, TwoFactorConfirmController::class)
            ->name('two-factor.confirm');
        Route::delete(Auth::twoFactorAuthentication->value, TwoFactorDisableController::class)
            ->name('two-factor.disable');
        Route::post(Auth::twoFactorRecoveryCodes->value, RecoveryCodesRegenerateController::class)
            ->name('two-factor.regenerate-recovery-codes');
        Route::get(Auth::passkeyRegistrationOptions->value, PasskeyRegistrationOptionsController::class)
            ->middleware(MiddlewareTag::throttleTenPerMinute->value)
            ->name('passkey.registration-options');
        Route::post(Auth::passkeys->value, PasskeyRegistrationController::class)
            ->middleware(MiddlewareTag::throttleTenPerMinute->value)
            ->name('passkey.store');
        Route::delete(Auth::passkey->value, PasskeyDestroyController::class)
            ->name('passkey.destroy');
    });
    Route::post(Auth::settingsCredentials->value, TokenController::class);
    Route::post(Auth::settingsCredential->value, TokenUpdateController::class);
    Route::delete(Auth::settingsCredential->value, TokenDestroyController::class);
    Route::post(Auth::settingsAppearance->value, AppearanceController::class);
    Route::get(Auth::dashboard->value, static fn () => response()->noContent());
});
