<?php

namespace App\Modules\Passkeys;

use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController as LaravelPasskeyLoginController;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;

readonly class PasskeyLoginController
{
    public function __construct(private LaravelPasskeyLoginController $LaravelPasskeyLoginController) {}

    public function __invoke(PasskeyVerificationRequest $PasskeyVerificationRequest, VerifyPasskey $VerifyPasskey): PasskeyLoginResponse
    {
        return $this->LaravelPasskeyLoginController->store($PasskeyVerificationRequest, $VerifyPasskey);
    }
}
