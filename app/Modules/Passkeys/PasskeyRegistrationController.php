<?php

namespace App\Modules\Passkeys;

use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController as LaravelPasskeyRegistrationController;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;

readonly class PasskeyRegistrationController
{
    public function __construct(private LaravelPasskeyRegistrationController $LaravelPasskeyRegistrationController) {}

    public function __invoke(PasskeyRegistrationRequest $PasskeyRegistrationRequest, StorePasskey $StorePasskey): PasskeyRegistrationResponse
    {
        return $this->LaravelPasskeyRegistrationController->store($PasskeyRegistrationRequest, $StorePasskey);
    }
}
