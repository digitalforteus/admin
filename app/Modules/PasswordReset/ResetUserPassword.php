<?php

namespace App\Modules\PasswordReset;

use App\Models\User;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

readonly class ResetUserPassword implements ResetsUserPasswords
{
    /** @param array<string, mixed> $input */
    public function reset(User $User, array $input): void
    {
        $validated = Validator::make($input, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ])->validate();

        $User->forceFill([
            Users::password->value => $validated['password'],
        ])->save();
    }
}
