<?php

use App\Helpers\SvgName;
use App\Routes\Auth;
use App\Routes\Web;
use App\View\DataModels\NavItem;
use App\View\DataModels\Svg;
use Illuminate\Http\Request;
use Zerotoprod\DataModel\PropertyRequiredException;

test('an entry carries its label, icon and route case, requires every one, and projects its icon', function (): void {
    $NavItem = NavItem::from([
        NavItem::label => 'Home',
        NavItem::icon => SvgName::home,
        NavItem::route => Web::home,
    ]);

    expect($NavItem->label)->toBe('Home')
        ->and($NavItem->icon)->toBe(SvgName::home)
        ->and($NavItem->route)->toBe(Web::home)
        ->and($NavItem->url())->toBe(Web::home->url())
        ->and(static fn () => NavItem::from([NavItem::label => 'Home', NavItem::icon => SvgName::home]))
        ->toThrow(PropertyRequiredException::class);

    $Svg = Svg::from($NavItem->svg());

    expect($Svg->name)->toBe(SvgName::home)
        ->and($Svg->classname)->toBe('h-4 w-4 opacity-70');
});

test('an entry is active on its own path, and a nested one stays active below it', function (): void {
    $NavItem = NavItem::from([
        NavItem::label => 'Home',
        NavItem::icon => SvgName::home,
        NavItem::route => Web::home,
    ]);

    $this->get(Web::home->value)->assertOk();

    expect($NavItem->nested)->toBeFalse()
        ->and($NavItem->active())->toBeTrue();

    $this->get(Web::contact->value)->assertOk();

    expect($NavItem->active())->toBeFalse();

    $Nested = NavItem::from([
        NavItem::label => 'Credentials',
        NavItem::icon => SvgName::command_line,
        NavItem::route => Auth::settingsCredentials,
        NavItem::nested => true,
    ]);

    app()->instance('request', Request::create(Auth::settingsCredential->url([Auth::credentialParameter => 'abc'])));

    expect($Nested->active())->toBeTrue();

    app()->instance('request', Request::create(Auth::settingsProfile->value));

    expect($Nested->active())->toBeFalse();
});
