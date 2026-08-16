<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;

readonly class NewPasswordController
{
    public function __construct(private FortifyNewPasswordController $FortifyNewPasswordController) {}

    public function __invoke(Request $Request): Responsable
    {
        return $this->FortifyNewPasswordController->store($Request);
    }
}
