<?php

use App\View\DataModels\Fieldset;
use App\View\DataModels\TextInput;

test('props override the defaults, and a text input projects its fieldset props against the error key', function (): void {
    $Fieldset = Fieldset::from([]);

    expect($Fieldset->bag)->toBe('default')
        ->and($Fieldset->required)->toBeFalse()
        ->and($Fieldset->legend)->toBeNull()
        ->and($Fieldset->name)->toBeNull()
        ->and($Fieldset->title)->toBeNull();

    $Overridden = Fieldset::from([
        Fieldset::legend => 'Email',
        Fieldset::name => 'email',
        Fieldset::bag => 'register_form',
        Fieldset::required => true,
        Fieldset::title => 'User email address',
    ]);

    expect($Overridden->legend)->toBe('Email')
        ->and($Overridden->name)->toBe('email')
        ->and($Overridden->bag)->toBe('register_form')
        ->and($Overridden->required)->toBeTrue()
        ->and($Overridden->title)->toBe('User email address');

    $Projected = Fieldset::from(
        TextInput::from([
            TextInput::name => 'email',
            TextInput::error => 'custom',
            TextInput::legend => 'Email',
            TextInput::required => true,
        ])->fieldset()
    );

    expect($Projected->name)->toBe('custom')
        ->and($Projected->legend)->toBe('Email')
        ->and($Projected->required)->toBeTrue()
        ->and($Projected->bag)->toBe('default');
});
