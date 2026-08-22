<?php

use App\View\DataModels\StatusToast;

test('the message falls back to the flashed session value, and a passed one wins over it', function (): void {
    $StatusToast = StatusToast::from();

    expect($StatusToast->sessionKey)->toBe('status')
        ->and($StatusToast->alert)->toBe('alert-success')
        ->and($StatusToast->message)->toBeNull();

    session()->put('status', 'Verification link sent!');
    session()->put('warning', 'Careful.');

    expect(StatusToast::from([])->message)->toBe('Verification link sent!')
        ->and(StatusToast::from([StatusToast::sessionKey => 'warning'])->message)->toBe('Careful.')
        ->and(StatusToast::from([StatusToast::sessionKey => 'missing'])->message)->toBeNull();

    $Passed = StatusToast::from([
        StatusToast::message => 'Passed.',
        StatusToast::alert => 'alert-error',
    ]);

    expect($Passed->message)->toBe('Passed.')
        ->and($Passed->alert)->toBe('alert-error');
});
