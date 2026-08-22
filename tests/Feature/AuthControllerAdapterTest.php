<?php

use App\Modules\Fortify\EmailVerificationNotificationController;
use App\Modules\Fortify\NewPasswordController;
use App\Modules\Fortify\PasswordResetLinkController;
use App\Modules\Fortify\RecoveryCodesRegenerateController;
use App\Modules\Fortify\TwoFactorConfirmController;
use App\Modules\Fortify\TwoFactorDisableController;
use App\Modules\Fortify\TwoFactorEnableController;
use App\Modules\Fortify\TwoFactorLoginController;
use App\Modules\Passkeys\PasskeyConfirmationController;
use App\Modules\Passkeys\PasskeyConfirmationOptionsController;
use App\Modules\Passkeys\PasskeyDestroyController;
use App\Modules\Passkeys\PasskeyLoginController;
use App\Modules\Passkeys\PasskeyLoginOptionsController;
use App\Modules\Passkeys\PasskeyRegistrationController;
use App\Modules\Passkeys\PasskeyRegistrationOptionsController;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as FortifyEmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController as LaravelPasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController as LaravelPasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController as LaravelPasskeyRegistrationController;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use Mockery\MockInterface;

/**
 * @template T of object
 *
 * @param  class-string<T>  $class
 * @return T&MockInterface
 */
function typedMock(string $class): MockInterface
{
    $Mock = Mockery::mock($class);

    if (! $Mock instanceof $class) {
        throw new LogicException('Mock does not implement the requested type.');
    }

    return $Mock;
}

test('every fortify and passkey route controller delegates to the package controller', function (): void {
    $Responsable = typedMock(Responsable::class);
    $Request = typedMock(Request::class);

    $TwoFactorLoginRequest = typedMock(TwoFactorLoginRequest::class);
    $TwoFactorAuthenticatedSessionController = typedMock(TwoFactorAuthenticatedSessionController::class);
    $TwoFactorAuthenticatedSessionController->shouldReceive('store')->once()->with($TwoFactorLoginRequest)->andReturn($Responsable);
    expect((new TwoFactorLoginController($TwoFactorAuthenticatedSessionController))($TwoFactorLoginRequest))->toBe($Responsable);

    $SendPasswordResetLinkRequest = typedMock(SendPasswordResetLinkRequest::class);
    $FortifyPasswordResetLinkController = typedMock(FortifyPasswordResetLinkController::class);
    $FortifyPasswordResetLinkController->shouldReceive('store')->once()->with($SendPasswordResetLinkRequest)->andReturn($Responsable);
    expect((new PasswordResetLinkController($FortifyPasswordResetLinkController))($SendPasswordResetLinkRequest))->toBe($Responsable);

    $FortifyNewPasswordController = typedMock(FortifyNewPasswordController::class);
    $FortifyNewPasswordController->shouldReceive('store')->once()->with($Request)->andReturn($Responsable);
    expect((new NewPasswordController($FortifyNewPasswordController))($Request))->toBe($Responsable);

    $FortifyEmailVerificationNotificationController = typedMock(FortifyEmailVerificationNotificationController::class);
    $FortifyEmailVerificationNotificationController->shouldReceive('store')->once()->with($Request)->andReturn($Responsable);
    expect((new EmailVerificationNotificationController($FortifyEmailVerificationNotificationController))($Request))->toBe($Responsable);

    $EnableTwoFactorAuthentication = typedMock(EnableTwoFactorAuthentication::class);
    $TwoFactorAuthenticationController = typedMock(TwoFactorAuthenticationController::class);
    $TwoFactorAuthenticationController->shouldReceive('store')->once()->with($Request, $EnableTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorEnableController($TwoFactorAuthenticationController))($Request, $EnableTwoFactorAuthentication))->toBe($Responsable);

    $ConfirmTwoFactorAuthentication = typedMock(ConfirmTwoFactorAuthentication::class);
    $ConfirmedTwoFactorAuthenticationController = typedMock(ConfirmedTwoFactorAuthenticationController::class);
    $ConfirmedTwoFactorAuthenticationController->shouldReceive('store')->once()->with($Request, $ConfirmTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorConfirmController($ConfirmedTwoFactorAuthenticationController))($Request, $ConfirmTwoFactorAuthentication))->toBe($Responsable);

    $DisableTwoFactorAuthentication = typedMock(DisableTwoFactorAuthentication::class);
    $TwoFactorAuthenticationController->shouldReceive('destroy')->once()->with($Request, $DisableTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorDisableController($TwoFactorAuthenticationController))($Request, $DisableTwoFactorAuthentication))->toBe($Responsable);

    $GenerateNewRecoveryCodes = typedMock(GenerateNewRecoveryCodes::class);
    $RecoveryCodeController = typedMock(RecoveryCodeController::class);
    $RecoveryCodeController->shouldReceive('store')->once()->with($Request, $GenerateNewRecoveryCodes)->andReturn($Responsable);
    expect((new RecoveryCodesRegenerateController($RecoveryCodeController))($Request, $GenerateNewRecoveryCodes))->toBe($Responsable);

    $Request = typedMock(Request::class);
    $JsonResponse = new JsonResponse;
    $GenerateVerificationOptions = typedMock(GenerateVerificationOptions::class);
    $VerifyPasskey = typedMock(VerifyPasskey::class);
    $PasskeyVerificationRequest = typedMock(PasskeyVerificationRequest::class);

    $LaravelPasskeyLoginController = typedMock(LaravelPasskeyLoginController::class);
    $LaravelPasskeyLoginController->shouldReceive('index')->once()->with($Request, $GenerateVerificationOptions)->andReturn($JsonResponse);
    expect((new PasskeyLoginOptionsController($LaravelPasskeyLoginController))($Request, $GenerateVerificationOptions))->toBe($JsonResponse);

    $PasskeyLoginResponse = typedMock(PasskeyLoginResponse::class);
    $LaravelPasskeyLoginController->shouldReceive('store')->once()->with($PasskeyVerificationRequest, $VerifyPasskey)->andReturn($PasskeyLoginResponse);
    expect((new PasskeyLoginController($LaravelPasskeyLoginController))($PasskeyVerificationRequest, $VerifyPasskey))->toBe($PasskeyLoginResponse);

    $LaravelPasskeyConfirmationController = typedMock(LaravelPasskeyConfirmationController::class);
    $LaravelPasskeyConfirmationController->shouldReceive('index')->once()->with($Request, $GenerateVerificationOptions)->andReturn($JsonResponse);
    expect((new PasskeyConfirmationOptionsController($LaravelPasskeyConfirmationController))($Request, $GenerateVerificationOptions))->toBe($JsonResponse);

    $PasskeyConfirmationResponse = typedMock(PasskeyConfirmationResponse::class);
    $LaravelPasskeyConfirmationController->shouldReceive('store')->once()->with($PasskeyVerificationRequest, $VerifyPasskey)->andReturn($PasskeyConfirmationResponse);
    expect((new PasskeyConfirmationController($LaravelPasskeyConfirmationController))($PasskeyVerificationRequest, $VerifyPasskey))->toBe($PasskeyConfirmationResponse);

    $GenerateRegistrationOptions = typedMock(GenerateRegistrationOptions::class);
    $LaravelPasskeyRegistrationController = typedMock(LaravelPasskeyRegistrationController::class);
    $LaravelPasskeyRegistrationController->shouldReceive('index')->once()->with($Request, $GenerateRegistrationOptions)->andReturn($JsonResponse);
    expect((new PasskeyRegistrationOptionsController($LaravelPasskeyRegistrationController))($Request, $GenerateRegistrationOptions))->toBe($JsonResponse);

    $PasskeyRegistrationRequest = typedMock(PasskeyRegistrationRequest::class);
    $StorePasskey = typedMock(StorePasskey::class);
    $PasskeyRegistrationResponse = typedMock(PasskeyRegistrationResponse::class);
    $LaravelPasskeyRegistrationController->shouldReceive('store')->once()->with($PasskeyRegistrationRequest, $StorePasskey)->andReturn($PasskeyRegistrationResponse);
    expect((new PasskeyRegistrationController($LaravelPasskeyRegistrationController))($PasskeyRegistrationRequest, $StorePasskey))->toBe($PasskeyRegistrationResponse);

    $Passkey = new Passkey;
    $DeletePasskey = typedMock(DeletePasskey::class);
    $PasskeyDeletedResponse = typedMock(PasskeyDeletedResponse::class);
    $LaravelPasskeyRegistrationController->shouldReceive('destroy')->once()->with($Passkey, $DeletePasskey)->andReturn($PasskeyDeletedResponse);
    expect((new PasskeyDestroyController($LaravelPasskeyRegistrationController))($Passkey, $DeletePasskey))->toBe($PasskeyDeletedResponse);
});
