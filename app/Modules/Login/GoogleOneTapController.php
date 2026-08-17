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

        try {
            $GoogleUser = $this->GoogleCredential->user($credential);
        } catch (Throwable) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 422);
        }

        $GoogleLogin->login($GoogleUser);

        return response()->json([
            'redirect' => redirect()->intended(Web::home->value)->getTargetUrl(),
        ]);
    }
}
