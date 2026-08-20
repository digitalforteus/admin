<?php

namespace App\Modules\Login;

use App\Routes\Web;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

readonly class GoogleOneTapController
{
    public function __construct(private GoogleCredential $GoogleCredential) {}

    public function __invoke(Request $Request, GoogleLogin $GoogleLogin): JsonResponse
    {
        $credential = $Request->validate([
            'credential' => ['required', 'string'],
        ])['credential'];

        $rawPayload = null;

        try {
            $GoogleUser = $this->GoogleCredential->user($credential, $rawPayload);
        } catch (Throwable) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 422);
        }

        $GoogleLogin->login($GoogleUser, $rawPayload);

        return response()->json([
            'redirect' => redirect()->intended(Web::home->value)->getTargetUrl(),
        ]);
    }
}
