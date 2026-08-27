<?php

namespace App\Modules\Settings\Security;

use App\Helpers\HasEnumAttributes;
use Laravel\Fortify\Fortify;

enum SecurityStatus: string
{
    use HasEnumAttributes;

    #[StatusMessage('Two-factor authentication setup started.')]
    case two_factor_authentication_enabled = Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED;

    #[StatusMessage('Two-factor authentication enabled.')]
    case two_factor_authentication_confirmed = Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED;

    #[StatusMessage('Two-factor authentication disabled.')]
    case two_factor_authentication_disabled = Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED;

    #[StatusMessage('New recovery codes generated.')]
    case recovery_codes_generated = Fortify::RECOVERY_CODES_GENERATED;

    #[StatusMessage('Passkey registered.')]
    case passkey_registered = 'passkey-registered';

    #[StatusMessage('Passkey deleted.')]
    case passkey_deleted = 'passkey-deleted';

    public static function messageFor(mixed $status): ?string
    {
        if (! is_string($status)) {
            return null;
        }

        return self::tryFrom($status)?->message() ?? $status;
    }

    public function message(): string
    {
        return $this->enumAttribute(StatusMessage::class)->message;
    }
}
