<?php

use App\Helpers\Theme;
use App\Models\User;
use App\Modules\Settings\Appearance\AppearanceRequest;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Users;

test('guests cannot view or change the theme', function (): void {
    $this->get(Auth::settingsAppearance->value)->assertRedirect(Web::login->value);
    $this->post(Auth::settingsAppearance->value, [AppearanceRequest::theme => Theme::dark->value])
        ->assertRedirect(Web::login->value);
});

test('the page lists every theme, checks the selected one, and toasts after a change', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee('data-theme-option="light"', false)
        ->assertSee('data-theme-option="dark"', false)
        ->assertSee('data-theme-option="auto"', false)
        ->assertSee('onchange="this.form.requestSubmit()"', false)
        ->assertDontSee('>Save</button>', false);

    $selected = (string) $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::dark]))
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->getContent();

    expect($selected)->toMatch('/value="dark"[^>]*checked/')
        ->and($selected)->not->toMatch('/value="light"[^>]*checked/');

    $toast = (string) $this->actingAs(User::factory()->createOne())
        ->from(Auth::settingsAppearance->value)
        ->followingRedirects()
        ->post(Auth::settingsAppearance->value, [AppearanceRequest::theme => Theme::dark->value])
        ->assertOk()
        ->assertSee('Appearance updated.')
        ->getContent();

    expect($toast)->toContain('data-toast')
        ->and($toast)->toContain('data-autodismiss="5000"')
        ->and($toast)->toContain('data-dismiss-toast')
        ->and($toast)->toContain('aria-label="Dismiss"');
});

test('a new account starts on auto and every theme can be selected', function (): void {
    $User = User::factory()->createOne();

    expect($User->theme)->toBe(Theme::auto);

    foreach (Theme::cases() as $Theme) {
        $this->actingAs($User)
            ->from(Auth::settingsAppearance->value)
            ->post(Auth::settingsAppearance->value, [AppearanceRequest::theme => $Theme->value])
            ->assertRedirect(Auth::settingsAppearance->value)
            ->assertSessionHas('status', 'Appearance updated.');

        expect($User->refresh()->theme)->toBe($Theme);
    }
});

test('an unknown or missing theme is refused', function (): void {
    $User = User::factory()->createOne([Users::theme->value => Theme::light]);

    $this->actingAs($User)
        ->from(Auth::settingsAppearance->value)
        ->post(Auth::settingsAppearance->value, [AppearanceRequest::theme => 'solarized'])
        ->assertSessionHasErrors(AppearanceRequest::theme);

    $this->actingAs($User)
        ->from(Auth::settingsAppearance->value)
        ->post(Auth::settingsAppearance->value)
        ->assertSessionHasErrors(AppearanceRequest::theme);

    expect($User->refresh()->theme)->toBe(Theme::light);
});

test('an explicit theme is rendered on the document, and auto leaves the device to decide', function (): void {
    foreach ([
        [Theme::light, '<html lang="en" data-theme="light"'],
        [Theme::dark, '<html lang="en" data-theme="dark"'],
    ] as [$Theme, $expected]) {
        $this->actingAs(User::factory()->createOne([Users::theme->value => $Theme]))
            ->get(Web::home->value)
            ->assertOk()
            ->assertSee($expected, false);
    }

    $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::auto]))
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-theme', false);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-theme', false);
});
