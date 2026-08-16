<?php

namespace App\Modules\Passkeys;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

readonly class PasskeyRegistrationOptionsController
{
    public function __construct(private PasskeyRegistrationController $PasskeyRegistrationController) {}

    public function __invoke(Request $Request, GenerateRegistrationOptions $GenerateRegistrationOptions): JsonResponse
    {
        return $this->PasskeyRegistrationController->index($Request, $GenerateRegistrationOptions);
    }
}
