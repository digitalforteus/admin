<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;

readonly class TwoFactorDisableController
{
    public function __construct(private TwoFactorAuthenticationController $TwoFactorAuthenticationController) {}

    public function __invoke(Request $Request, DisableTwoFactorAuthentication $DisableTwoFactorAuthentication): Responsable
    {
        return $this->TwoFactorAuthenticationController->destroy($Request, $DisableTwoFactorAuthentication);
    }
}
