<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;

readonly class RecoveryCodesRegenerateController
{
    public function __construct(private RecoveryCodeController $RecoveryCodeController) {}

    public function __invoke(Request $Request, GenerateNewRecoveryCodes $GenerateNewRecoveryCodes): Responsable
    {
        return $this->RecoveryCodeController->store($Request, $GenerateNewRecoveryCodes);
    }
}
