<?php

use App\View\DataModels\AuthCard;
use App\View\DataModels\PageHeader;

test('props override the defaults, and an auth card projects its heading props', function (): void {
    $PageHeader = PageHeader::from([]);

    expect($PageHeader->title)->toBeNull()
        ->and($PageHeader->classname)->toBe('card-title');

    $Overridden = PageHeader::from([
        PageHeader::title => 'Register',
        PageHeader::classname => 'text-lg',
    ]);

    expect($Overridden->title)->toBe('Register')
        ->and($Overridden->classname)->toBe('text-lg')
        ->and(PageHeader::from(AuthCard::from([AuthCard::title => 'Register'])->pageHeader())->title)->toBe('Register')
        ->and(PageHeader::from(AuthCard::from([])->pageHeader())->title)->toBeNull();
});
