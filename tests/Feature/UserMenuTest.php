<?php

use App\Helpers\Role;
use App\Helpers\SessionKey;
use App\Models\User;
use App\Routes\Admin;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use App\View\DataModels\UserMenu;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('initials are taken from the first and last word of the name', function (): void {
    foreach ([
        ['John Doe', 'JD'],
        ['John Quincy Doe', 'JD'],
        ['Prince', 'P'],
        ['  john   doe  ', 'JD'],
        ['', '?'],
        ['   ', '?'],
    ] as [$name, $initials]) {
        expect(UserMenu::from([UserMenu::name => $name])->initials())->toBe($initials);
    }
});

test('the menu links to settings and logout, and an administrator to the admin pages', function (): void {
    expect(UserMenu::items())->toHaveCount(2)
        ->and(UserMenu::items()[0]->route)->toBe(Auth::settingsProfile)
        ->and(UserMenu::items()[1]->route)->toBe(Web::logout);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee(Admin::index->value);

    $User->assignRole(Role::admin->value);
    $this->actingAs($User);

    expect(UserMenu::items())->toHaveCount(3)
        ->and(UserMenu::items()[0]->route)->toBe(Admin::index);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee(Admin::index->value);
});

test('the topnav shows the account dropdown, preferring a cached provider picture', function (): void {
    $User = User::factory()->createOne([
        Users::name->value => 'John Doe',
        Users::email->value => 'john@example.com',
    ]);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('data:image/jpeg;base64,'.base64_encode('gravatar'))
        ->assertSee('John Doe')
        ->assertSee('john@example.com')
        ->assertSee(Auth::settingsProfile->value)
        ->assertSee(Web::logout->value);

    $this->actingAs($User)
        ->withSession([SessionKey::user_picture->value => 'https://example.com/avatar.jpg'])
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('https://example.com/avatar.jpg')
        ->assertDontSee('JD');
});

test('gravatar is fetched once and cached for the authentication session', function (): void {
    $User = User::factory()->createOne([
        Users::email->value => 'MyEmailAddress@example.com',
    ]);

    $this->actingAs($User)->get(Web::home->value)->assertOk();
    $this->get(Web::home->value)->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($Request): bool => $Request->url() === 'https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g');
    expect(session(SessionKey::user_picture->value))->toBe('data:image/jpeg;base64,'.base64_encode('gravatar'));
});

test('the topnav offers google one tap to a guest only', function (): void {
    Config::set('services.google.client_id', 'client-id.apps.googleusercontent.com');

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee(Web::login->value)
        ->assertSee(Web::googleOneTap->value)
        ->assertSee('data-google-one-tap', false)
        ->assertSee('client-id.apps.googleusercontent.com')
        ->assertDontSee(Web::logout->value);

    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-google-one-tap', false);
});
