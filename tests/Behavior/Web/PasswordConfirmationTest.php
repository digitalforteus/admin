<?php

use App\Models\User;
use App\Modules\PasswordConfirmation\PasswordConfirmationForm;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

test('guests are refused, and the page renders for a signed in user', function (): void {
    $this->get(Auth::confirmPassword->value)
        ->assertRedirect(Web::login->value);

    $this->post(Auth::confirmPassword->value, [
        PasswordConfirmationForm::password => 'password',
    ])->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::confirmPassword->value)
        ->assertOk()
        ->assertSee('data-password-confirmation-form', false)
        ->assertSee('name="'.PasswordConfirmationForm::password.'"', false)
        ->assertSee('action="'.Auth::confirmPassword->value.'"', false);

    expect(route('password.confirm'))->toContain(Auth::confirmPassword->value);
});

test('a guarded page is withheld until the password is confirmed, then the intended one is served', function (): void {
    Route::get('/password-confirmation-protected', static fn () => response('protected'))
        ->middleware([
            MiddlewareTag::web->value,
            MiddlewareTag::auth->value,
            MiddlewareTag::passwordConfirm->value,
        ]);
    $User = User::factory()->createOne([
        Users::password->value => Hash::make('current-password'),
    ]);

    $this->actingAs($User)
        ->get('/password-confirmation-protected')
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($User)
        ->withSession(['url.intended' => Auth::settingsCredentials->value])
        ->post(Auth::confirmPassword->value, [
            PasswordConfirmationForm::password => 'current-password',
        ])->assertRedirect(Auth::settingsCredentials->value)
        ->assertSessionHas('auth.password_confirmed_at');

    $this->actingAs($User)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/password-confirmation-protected')
        ->assertOk()
        ->assertSee('protected');

    // With nothing intended, home is where a confirmation lands.
    $this->actingAs($User)
        ->post(Auth::confirmPassword->value, [
            PasswordConfirmationForm::password => 'current-password',
        ])->assertRedirect(Web::home->value)
        ->assertSessionHas('auth.password_confirmed_at');
});

test('an incorrect or missing password is rejected', function (): void {
    $User = User::factory()->createOne([
        Users::password->value => Hash::make('current-password'),
    ]);

    $this->actingAs($User)
        ->from(Auth::confirmPassword->value)
        ->post(Auth::confirmPassword->value, [
            PasswordConfirmationForm::password => 'incorrect-password',
        ])->assertRedirect(Auth::confirmPassword->value)
        ->assertSessionHasErrors(PasswordConfirmationForm::password)
        ->assertSessionMissing('auth.password_confirmed_at');

    $this->actingAs($User)
        ->from(Auth::confirmPassword->value)
        ->post(Auth::confirmPassword->value)
        ->assertRedirect(Auth::confirmPassword->value)
        ->assertSessionHasErrors(PasswordConfirmationForm::password)
        ->assertSessionMissing('auth.password_confirmed_at');
});
