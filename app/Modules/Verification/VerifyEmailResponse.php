<?php

namespace App\Modules\Verification;

use App\Routes\Web;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

readonly class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(status: 204)
            : redirect(Web::home->value)->with('status', 'Email verified successfully.');
    }
}
