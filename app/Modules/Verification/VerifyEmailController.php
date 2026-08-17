<?php

namespace App\Modules\Verification;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

readonly class VerifyEmailController
{
    public function __invoke(Request $Request, string $id, string $hash): VerifyEmailResponse
    {
        $User = User::query()->findOrFail($id);

        $User->getEmailForVerification()
            |> sha1(...)
            |> (static fn ($x) => hash_equals($x, $hash))
            |> (static fn ($x) => abort_unless($x, 403));

        if (! $User->hasVerifiedEmail() && $User->markEmailAsVerified()) {
            event(new Verified($User));
        }

        Auth::login($User);
        $Request->session()->regenerate();

        return app(VerifyEmailResponse::class);
    }
}
