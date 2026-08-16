<?php

namespace App\Modules\Passkeys;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;

readonly class PasskeyConfirmationOptionsController
{
    public function __construct(private PasskeyConfirmationController $PasskeyConfirmationController) {}

    public function __invoke(Request $Request, GenerateVerificationOptions $GenerateVerificationOptions): JsonResponse
    {
        return $this->PasskeyConfirmationController->index($Request, $GenerateVerificationOptions);
    }
}
