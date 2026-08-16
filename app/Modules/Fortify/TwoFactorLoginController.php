<?php

namespace App\Modules\Fortify;

use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

readonly class TwoFactorLoginController
{
    public function __construct(private TwoFactorAuthenticatedSessionController $TwoFactorAuthenticatedSessionController) {}

    public function __invoke(TwoFactorLoginRequest $TwoFactorLoginRequest): mixed
    {
        return $this->TwoFactorAuthenticatedSessionController->store($TwoFactorLoginRequest);
    }
}
