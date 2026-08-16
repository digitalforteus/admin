<?php

namespace App\Modules\PasswordReset;

use App\Routes\Web;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'If an account exists for that email, a password reset link has been sent.',
            ]);
        }

        return redirect(Web::forgotPasswordSent->value);
    }
}
