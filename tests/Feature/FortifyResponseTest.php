<?php

use App\Modules\PasswordReset\PasswordResetLinkResponse;
use App\Modules\Verification\EmailVerificationNotificationSentResponse;
use App\Modules\Verification\VerifyEmailResponse;
use Illuminate\Http\Request;

test('fortify response adapters support json requests', function (): void {
    $Request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);

    expect((new PasswordResetLinkResponse)->toResponse($Request)->getStatusCode())->toBe(200)
        ->and((new EmailVerificationNotificationSentResponse)->toResponse($Request)->getStatusCode())->toBe(202)
        ->and((new VerifyEmailResponse)->toResponse($Request)->getStatusCode())->toBe(204);
});
