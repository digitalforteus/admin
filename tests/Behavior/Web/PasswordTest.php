<?php

use App\Models\User;
use App\Modules\PasswordConfirmation\PasswordConfirmationForm;
use App\Modules\PasswordReset\ForgotPasswordForm;
use App\Modules\PasswordReset\ResetPasswordForm;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

test('a password is confirmed before a guarded page, and reset only by a live token and a replacement strong enough', function (): void {
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

    $this->flushSession();
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

    $this->forgetCredentials();

    $this->get(Web::login->value)
        ->assertOk()
        ->assertSee(Web::forgotPassword->value);

    $this->get(Web::forgotPassword->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee('data-password-reset-request', false);

    $this->get(Web::forgotPasswordSent->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee('data-password-reset-sent', false)
        ->assertSee(Web::login->value)
        ->assertSee(Web::forgotPassword->value);

    $url = Web::resetPassword->url([ResetPasswordForm::token => 'reset-token']);

    $this->get($url.'?email=user%40example.com')
        ->assertOk()
        ->assertSee('data-password-reset-form', false)
        ->assertSee('user@example.com')
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('name="'.ResetPasswordForm::token.'" value="reset-token"', false);

    expect(route('password.reset', [
        ResetPasswordForm::token => 'reset-token',
        ResetPasswordForm::email => 'user@example.com',
    ]))->toContain($url.'?email=user%40example.com');

    $User = User::factory()->createOne();

    foreach ([
        Web::forgotPassword->value,
        Web::resetPassword->url([ResetPasswordForm::token => 'token']),
        Web::forgotPasswordSent->value,
    ] as $guarded) {
        $this->actingAs($User)->get($guarded)->assertRedirect(Web::home->value);
    }

    $this->get(Web::logout->value);

    Notification::fake();
    $User = User::factory()->createOne();

    $this->post(Web::forgotPassword->value, [
        ForgotPasswordForm::email => strtoupper($User->email),
    ])->assertRedirect(Web::forgotPasswordSent->value);

    Notification::assertSentTo($User, ResetPassword::class);

    Notification::fake();

    $this->post(Web::forgotPassword->value, [
        ForgotPasswordForm::email => 'missing@example.com',
    ])->assertRedirect(Web::forgotPasswordSent->value);

    Notification::assertNothingSent();

    $this->from(Web::forgotPassword->value)
        ->post(Web::forgotPassword->value, [ForgotPasswordForm::email => 'not-an-email'])
        ->assertRedirect(Web::forgotPassword->value)
        ->assertSessionHasErrors(ForgotPasswordForm::email);

    Event::fake([PasswordResetEvent::class]);
    $User = User::factory()->createOne([
        Users::password->value => Hash::make('old-password'),
        Users::remember_token->value => 'old-remember-token',
    ]);
    $token = Password::createToken($User);

    $this->post(route('password.update'), [
        ResetPasswordForm::token => $token,
        ResetPasswordForm::email => $User->email,
        ResetPasswordForm::password => 'new-password-1234',
        ResetPasswordForm::password_confirmation => 'new-password-1234',
    ])->assertRedirect(Web::login->value)
        ->assertSessionHas('status', trans(Password::PasswordReset));

    $User->refresh();
    expect(Hash::check('new-password-1234', $User->password))->toBeTrue()
        ->and($User->remember_token)->not->toBe('old-remember-token');
    Event::assertDispatched(PasswordResetEvent::class);

    $User = User::factory()->createOne([
        Users::password->value => Hash::make('old-password'),
    ]);
    $invalidUrl = Web::resetPassword->url([ResetPasswordForm::token => 'invalid-token']);

    $this->from($invalidUrl)->post(route('password.update'), [
        ResetPasswordForm::token => 'invalid-token',
        ResetPasswordForm::email => $User->email,
        ResetPasswordForm::password => 'new-password-1234',
        ResetPasswordForm::password_confirmation => 'new-password-1234',
    ])->assertRedirect($invalidUrl)
        ->assertSessionHasErrors(ResetPasswordForm::email)
        ->assertSessionHasInput(ResetPasswordForm::email, $User->email);

    expect(Hash::check('old-password', $User->refresh()->password))->toBeTrue();

    $url = Web::resetPassword->url([ResetPasswordForm::token => 'token']);

    $this->from($url)->post(route('password.update'), [
        ResetPasswordForm::token => 'token',
        ResetPasswordForm::email => 'invalid',
        ResetPasswordForm::password => 'new-password-1234',
        ResetPasswordForm::password_confirmation => 'new-password-1234',
    ])->assertRedirect($url)
        ->assertSessionHasErrors(ResetPasswordForm::email)
        ->assertSessionMissing(ResetPasswordForm::password);

    $token = Password::createToken($User);
    $tokenUrl = Web::resetPassword->url([ResetPasswordForm::token => $token]);

    $this->from($tokenUrl)->post(route('password.update'), [
        ResetPasswordForm::token => $token,
        ResetPasswordForm::email => $User->email,
        ResetPasswordForm::password => 'short',
        ResetPasswordForm::password_confirmation => 'different',
    ])->assertRedirect($tokenUrl)
        ->assertSessionHasErrors(ResetPasswordForm::password)
        ->assertSessionMissing(ResetPasswordForm::password);
});
