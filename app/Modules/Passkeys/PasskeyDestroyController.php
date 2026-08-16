<?php

namespace App\Modules\Passkeys;

use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;
use Laravel\Passkeys\Passkey;

readonly class PasskeyDestroyController
{
    public function __construct(private PasskeyRegistrationController $PasskeyRegistrationController) {}

    public function __invoke(Passkey $Passkey, DeletePasskey $DeletePasskey): PasskeyDeletedResponse
    {
        return $this->PasskeyRegistrationController->destroy($Passkey, $DeletePasskey);
    }
}
