<?php

use App\Helpers\SessionKey;
use App\Models\User;
use App\Modules\Register\RegisterForm;
use App\Modules\Register\RegisterFormFactory;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Hash;

test('the page renders and a registration signs the user in with a hashed password', function (): void {
    $this->get(Web::register->value)->assertOk();

    $RegisterForm = RegisterFormFactory::factory()->make();

    // The address still has to be confirmed, so the notice outranks any intended url.
    session(['url.intended' => Web::home->value]);

    $this->post(Web::register->value, $RegisterForm->toArray())
        ->assertRedirect(Auth::verificationNotice->value);

    $this->assertAuthenticated();
    expect(session(SessionKey::sign_up_method->value))->toBe('Email');
    $this->assertDatabaseHas((new User)->getTable(), [
        Users::name->value => $RegisterForm->name,
        Users::email->value => $RegisterForm->email,
        Users::phone->value => $RegisterForm->phone,
        Users::email_verified_at->value => null,
    ]);

    $User = User::query()->where(Users::email->value, $RegisterForm->email)->firstOrFail();

    expect($User->password)->not->toBe($RegisterForm->password)
        ->and(Hash::check($RegisterForm->password, $User->password))->toBeTrue();
});

test('every invalid field is refused, and the password is never flashed back', function (): void {
    $this->post(Web::register->value)
        ->assertSessionHasErrors([
            RegisterForm::name,
            RegisterForm::email,
            RegisterForm::phone,
            RegisterForm::password,
        ]);

    foreach ([
        RegisterForm::name => [RegisterForm::name => ''],
        RegisterForm::email => [RegisterForm::email => ''],
        RegisterForm::phone => [RegisterForm::phone => ''],
        RegisterForm::password => [RegisterForm::password_confirmation => 'mismatch'],
    ] as $field => $overrides) {
        $this->post(
            Web::register->value,
            RegisterFormFactory::factory()->set($overrides)->context()
        )->assertSessionHasErrors($field);
    }

    User::factory()->createOne([Users::email->value => 'taken@example.com']);

    $this->post(
        Web::register->value,
        RegisterFormFactory::factory()->set([RegisterForm::email => 'taken@example.com'])->context()
    )->assertSessionHasErrors(RegisterForm::email);

    $Invalid = RegisterFormFactory::factory()
        ->set([RegisterForm::email => 'invalid-email'])
        ->make();

    $this->post(Web::register->value, $Invalid->toArray())
        ->assertSessionHasInput($Invalid->name)
        ->assertSessionMissing($Invalid->password);

    $this->assertGuest();

    $this->from(Web::register->value)
        ->followingRedirects()
        ->post(
            Web::register->value,
            RegisterFormFactory::factory()->set([RegisterForm::name => ''])->context()
        )
        ->assertOk()
        ->assertSee('The name field is required.');
});
