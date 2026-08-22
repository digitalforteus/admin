<?php

use App\Helpers\Theme;
use App\Models\User;
use App\Sources\Db\App\Users;
use App\View\DataModels\Main;

test('a guest gets no theme, no classnames and no rail, and any rail widens the content', function (): void {
    $Main = Main::from([]);

    expect($Main->classnames)->toBeNull()
        ->and($Main->theme)->toBeNull()
        ->and($Main->leftNav)->toBeFalse()
        ->and($Main->adminNav)->toBeFalse()
        ->and($Main->settingsNav)->toBeFalse()
        ->and($Main->nav())->toBeFalse()
        ->and(Main::from([Main::classnames => 'bg-base-200'])->classnames)->toBe('bg-base-200')
        ->and(Main::from([Main::adminNav => true])->nav())->toBeTrue()
        ->and(Main::from([Main::settingsNav => true])->nav())->toBeTrue();
});

test('the theme is the authenticated user attribute, and auto renders none', function (): void {
    $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::dark]));

    expect(Main::from([])->theme)->toBe(Theme::dark->value);

    $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::auto]));

    expect(Main::from([])->theme)->toBeNull();
});
