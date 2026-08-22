<?php

use App\View\DataModels\AdminNav;
use App\View\DataModels\LeftNav;
use App\View\DataModels\SettingsNav;
use App\View\DataModels\Topnav;

test('the dropdown mirrors whichever rail is standing, and no rail means no dropdown', function (): void {
    $None = Topnav::from([]);

    expect($None->leftNav)->toBeFalse()
        ->and($None->adminNav)->toBeFalse()
        ->and($None->settingsNav)->toBeFalse()
        ->and($None->nav())->toBeFalse();

    $Left = Topnav::from([Topnav::leftNav => true]);

    expect($Left->nav())->toBeTrue()
        ->and($Left->items())->toEqual(LeftNav::items());

    // The admin rail wins wherever both are standing.
    expect(Topnav::from([Topnav::leftNav => true, Topnav::adminNav => true])->items())
        ->toEqual(AdminNav::items());

    $Settings = Topnav::from([Topnav::settingsNav => true]);

    expect($Settings->nav())->toBeTrue()
        ->and($Settings->items())->toEqual(SettingsNav::items());
});
