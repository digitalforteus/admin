<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;

readonly class TwoFactorConfirmController
{
    public function __construct(private ConfirmedTwoFactorAuthenticationController $ConfirmedTwoFactorAuthenticationController) {}

    public function __invoke(Request $Request, ConfirmTwoFactorAuthentication $ConfirmTwoFactorAuthentication): Responsable
    {
        return $this->ConfirmedTwoFactorAuthenticationController->store($Request, $ConfirmTwoFactorAuthentication);
    }
}
