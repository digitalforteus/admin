<?php

namespace App\Modules\Fortify;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as FortifyEmailVerificationNotificationController;

readonly class EmailVerificationNotificationController
{
    public function __construct(private FortifyEmailVerificationNotificationController $FortifyEmailVerificationNotificationController) {}

    public function __invoke(Request $Request): mixed
    {
        return $this->FortifyEmailVerificationNotificationController->store($Request);
    }
}
