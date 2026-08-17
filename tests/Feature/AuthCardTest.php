<?php

use App\View\DataModels\AuthCard;

test('defaults are resolved from the props array', function (): void {
    $AuthCard = AuthCard::from();

    expect($AuthCard->title)->toBeNull()
        ->and($AuthCard->maxWidth)->toBe('lg:max-w-sm')
        ->and($AuthCard->classname)->toBeEmpty()
        ->and($AuthCard->classes())->toBe('card lg:m-auto lg:mt-24 lg:max-w-sm');
});

test('props override defaults and compose into the card classes', function (): void {
    $AuthCard = AuthCard::from([
        AuthCard::title => 'Register',
        AuthCard::maxWidth => 'lg:max-w-lg',
        AuthCard::classname => 'card-compact',
    ]);

    expect($AuthCard->title)->toBe('Register')
        ->and($AuthCard->classes())->toBe('card lg:m-auto lg:mt-24 lg:max-w-lg card-compact');
});
