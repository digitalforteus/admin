<?php

use App\Models\User;
use App\Routes\Auth;
use App\Routes\Web;
use App\View\DataModels\NavItem;
use App\View\DataModels\SettingsNav;
use App\View\ViewDirectory;
use Illuminate\Http\Request;

test('every case describes one named navigation item, and every settings section is listed with an icon', function (): void {
    $items = SettingsNav::items();

    expect($items[0]->label)->toBe('Profile')
        ->and($items[0]->route)->toBe(Auth::settingsProfile)
        ->and(collect($items)->pluck('route')->all())
        ->toBe([
            Auth::settingsProfile,
            Auth::settingsAppearance,
            Auth::settingsSecurity,
            Auth::settingsCredentials,
            Auth::settingsSessions,
        ]);

    foreach (SettingsNav::cases() as $SettingsNav) {
        expect($SettingsNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }

    foreach ([
        [null, 'Settings navigation cases must describe a navigation item.'],
        [[Auth::settingsProfile], 'Settings navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(SettingsNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }
});

test('the rail is shown on a settings page, marking the section, and is absent everywhere else', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('lg:pl-56')
        ->assertSee('aria-label="Settings"', false);

    $this->actingAs($User)
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->assertSee('menu-active');

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Settings"', false);
});

test('a section stays active below its own path', function (): void {
    foreach ([
        'Credentials' => Auth::settingsCredential->url([Auth::credentialParameter => 'abc']),
        'Sessions' => Auth::settingsSession->url([Auth::sessionParameter => 'abc']),
    ] as $label => $url) {
        app()->instance('request', Request::create($url));

        $active = [];

        foreach (SettingsNav::items() as $NavItem) {
            $active[$NavItem->label] = $NavItem->active();
        }

        expect($active[$label])->toBeTrue()
            ->and($active['Profile'])->toBeFalse();
    }
});
