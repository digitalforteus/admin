<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;

readonly class TwoFactorEnableController
{
    public function __construct(private TwoFactorAuthenticationController $TwoFactorAuthenticationController) {}

    public function __invoke(Request $Request, EnableTwoFactorAuthentication $EnableTwoFactorAuthentication): Responsable
    {
        return $this->TwoFactorAuthenticationController->store($Request, $EnableTwoFactorAuthentication);
    }
}
