<?php

use App\View\DataModels\AuthCard;

test('props override the defaults and compose into the card classes', function (): void {
    $AuthCard = AuthCard::from();

    expect($AuthCard->title)->toBeNull()
        ->and($AuthCard->maxWidth)->toBe('max-w-sm')
        ->and($AuthCard->classname)->toBeEmpty()
        ->and($AuthCard->classes())->toBe('card mx-auto mt-6 lg:mt-24 max-w-sm');

    $Overridden = AuthCard::from([
        AuthCard::title => 'Register',
        AuthCard::maxWidth => 'lg:max-w-lg',
        AuthCard::classname => 'card-compact',
    ]);

    expect($Overridden->title)->toBe('Register')
        ->and($Overridden->classes())->toBe('card mx-auto mt-6 lg:mt-24 lg:max-w-lg card-compact');
});
