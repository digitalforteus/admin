<?php

namespace App\Modules\Fortify;

use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

readonly class PasswordResetLinkController
{
    public function __construct(private FortifyPasswordResetLinkController $FortifyPasswordResetLinkController) {}

    public function __invoke(SendPasswordResetLinkRequest $SendPasswordResetLinkRequest): Responsable
    {
        return $this->FortifyPasswordResetLinkController->store($SendPasswordResetLinkRequest);
    }
}
