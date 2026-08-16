<?php

namespace App\Modules\Passkeys;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;

readonly class PasskeyLoginOptionsController
{
    public function __construct(private PasskeyLoginController $PasskeyLoginController) {}

    public function __invoke(Request $Request, GenerateVerificationOptions $GenerateVerificationOptions): JsonResponse
    {
        return $this->PasskeyLoginController->index($Request, $GenerateVerificationOptions);
    }
}
