<?php

use App\Models\User;
use App\Routes\Auth;
use App\Routes\Web;
use App\View\DataModels\LeftNav;
use App\View\DataModels\NavItem;

test('every case describes one named navigation item, leading with home and listing contact', function (): void {
    expect(LeftNav::items())->toHaveCount(2)
        ->and(LeftNav::items()[0]->route)->toBe(Web::home)
        ->and(collect(LeftNav::items())->pluck('route')->all())->toContain(Web::contact);

    foreach (LeftNav::cases() as $LeftNav) {
        expect($LeftNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ([
        [null, 'Left navigation cases must describe a navigation item.'],
        [[Web::home], 'Left navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(LeftNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }
});

test('the rail is hidden from a guest, marks the root path, and stands down on the settings pages', function (): void {
    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('lg:pl-56');

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('menu-active');

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee('aria-label="Settings"', false);
});
