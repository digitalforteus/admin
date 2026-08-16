<?php

namespace App\Modules\Passkeys;

use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController as LaravelPasskeyConfirmationController;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;

readonly class PasskeyConfirmationController
{
    public function __construct(private LaravelPasskeyConfirmationController $LaravelPasskeyConfirmationController) {}

    public function __invoke(PasskeyVerificationRequest $PasskeyVerificationRequest, VerifyPasskey $VerifyPasskey): PasskeyConfirmationResponse
    {
        return $this->LaravelPasskeyConfirmationController->store($PasskeyVerificationRequest, $VerifyPasskey);
    }
}
