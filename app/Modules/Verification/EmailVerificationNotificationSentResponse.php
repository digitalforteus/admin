<?php

namespace App\Modules\Verification;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;

readonly class EmailVerificationNotificationSentResponse implements EmailVerificationNotificationSentResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(status: 202)
            : back()->with('status', 'Verification link sent!');
    }
}
