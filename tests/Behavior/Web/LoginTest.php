<?php

use App\Helpers\OauthProviderId;
use App\Helpers\SessionKey;
use App\Helpers\SocialiteDriver;
use App\Models\OauthProvider;
use App\Models\User;
use App\Modules\Login\GoogleCredential;
use App\Modules\Login\GoogleUser as OneTapGoogleUser;
use App\Modules\Login\LoginForm;
use App\Modules\Login\LoginFormFactory;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery\MockInterface;

test('the login page offers each configured provider and preserves the remember choice', function (): void {
    config()->set('services.github.client_id', 'github-client-id');

    $this->get(Web::login->value)
        ->assertOk()
        ->assertSee(Web::googleRedirect->value)
        ->assertSee('data-google-login', false)
        ->assertSee('data-github-login', false)
        ->assertSee('data-remember-login', false)
        ->assertSeeHtml('name="'.LoginForm::remember_token.'"');

    config()->set('services.github.client_id', '');

    $this->get(Web::login->value)
        ->assertOk()
        ->assertDontSee('data-github-login', false)
        ->assertSee('data-google-login', false);

    $Response = $this->from(Web::login->value)
        ->followingRedirects()
        ->post(Web::login->value, [
            ...LoginFormFactory::factory()->set([LoginForm::email => ''])->context(),
            LoginForm::remember_token => true,
        ]);

    $Response->assertOk();
    expect($Response->getContent())->toMatch('/<input(?=[^>]*name="remember_token")(?=[^>]*checked)[^>]*>/');
});

test('the form shows validation and authentication failures', function (): void {
    $this->from(Web::login->value)
        ->followingRedirects()
        ->post(
            Web::login->value,
            LoginFormFactory::factory()->set([LoginForm::email => ''])->context()
        )
        ->assertOk()
        ->assertSee('The email field is required.');

    $User = User::factory()->createOne();

    $this->from(Web::login->value)
        ->followingRedirects()
        ->post(Web::login->value, LoginFormFactory::factory()
            ->set([LoginForm::email => $User->email])
            ->set([LoginForm::password => 'wrong-password'])
            ->make()
            ->toArray())
        ->assertOk()
        ->assertSee('These credentials do not match our records.');
});

test('every invalid login is refused and leaves the password out of the old input', function (): void {
    $this->post(Web::login->value)
        ->assertSessionHasErrors([LoginForm::email, LoginForm::password]);

    $this->post(
        Web::login->value,
        LoginFormFactory::factory()->set([LoginForm::email => ''])->context()
    )->assertSessionHasErrors(LoginForm::email);

    $this->post(
        Web::login->value,
        LoginFormFactory::factory()->set([LoginForm::password => ''])->context()
    )->assertSessionHasErrors(LoginForm::password);

    $this->post(
        Web::login->value,
        LoginFormFactory::factory()->set([LoginForm::email => 'nonexistent@example.com'])->make()->toArray()
    )
        ->assertSessionHasErrors(LoginForm::email)
        ->assertSessionHasInput(LoginForm::email)
        ->assertSessionMissing(LoginForm::password);

    $User = User::factory()->createOne();

    $this->post(
        Web::login->value,
        LoginFormFactory::factory()
            ->set([LoginForm::email => $User->email])
            ->set([LoginForm::password => 'wrong-password'])
            ->make()
            ->toArray()
    )->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
});

test('a login succeeds, honours the intended url and sanitises the email', function (): void {
    $User = User::factory([Users::password->value => Users::password->value])->createOne();
    $LoginForm = LoginFormFactory::factory()
        ->set([LoginForm::email => $User->email])
        ->set([LoginForm::password => Users::password->value])
        ->make();

    $this->post(Web::login->value, $LoginForm->toArray())->assertRedirect(Web::home->value);
    $this->assertAuthenticatedAs($User);

    // A second submission while already signed in lands on the same page.
    $this->post(Web::login->value, $LoginForm->toArray())->assertRedirect(Web::home->value);

    $this->get(Web::logout->value);

    session(['url.intended' => Web::home->value]);
    $this->post(Web::login->value, $LoginForm->toArray())->assertRedirect(Web::home->value);
    $this->assertAuthenticated();

    $this->get(Web::logout->value);

    User::factory()->createOne([Users::email->value => 'test@example.com']);

    $this->post(
        Web::login->value,
        LoginFormFactory::factory()->set([LoginForm::email => ' TEST@EXAMPLE.COM '])->make()->toArray()
    )->assertRedirect(Web::home->value);

    $this->assertAuthenticated();
});

test('remember me restores the user after the session is emptied', function (): void {
    $User = User::factory()->createOne();
    $LoginForm = LoginFormFactory::factory()
        ->set([LoginForm::email => $User->email])
        ->set([LoginForm::remember_token => true])
        ->make();

    $this->post(Web::login->value, $LoginForm->toArray())->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($User);
    expect($User->refresh()->remember_token)->not->toBeNull();

    $this->withSession([]);

    $this->get(Web::home->value);

    $this->assertAuthenticatedAs($User);
});

test('login challenges a user with two-factor authentication enabled', function (): void {
    $User = User::factory([Users::password->value => Users::password->value])->createOne();
    app(EnableTwoFactorAuthentication::class)($User);
    $User->forceFill([Users::two_factor_confirmed_at->value => now()])->save();

    $this->post(Web::login->value, [
        LoginForm::email => $User->email,
        LoginForm::password => Users::password->value,
        LoginForm::remember_token => true,
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($User->id)
        ->and(session('login.remember'))->toBeTrue();
});

test('google one tap refuses an unverified credential, then logs in and upserts the provider', function (): void {
    $this->mock(GoogleCredential::class, function (MockInterface $Mock): void {
        $Mock->shouldReceive('user')
            ->once()
            ->with('invalid-google-credential', Mockery::any())
            ->andThrow(new InvalidArgumentException);

        $Mock->shouldReceive('user')
            ->once()
            ->with('verified-google-credential', Mockery::any())
            ->andReturn(OneTapGoogleUser::from([
                OneTapGoogleUser::sub => '123456789',
                OneTapGoogleUser::name => 'Google User',
                OneTapGoogleUser::given_name => 'Google',
                OneTapGoogleUser::family_name => 'User',
                OneTapGoogleUser::picture => 'https://example.com/avatar.jpg',
                OneTapGoogleUser::email => 'google@example.com',
                OneTapGoogleUser::email_verified => true,
                OneTapGoogleUser::id => '123456789',
                OneTapGoogleUser::verified_email => true,
            ]));

        $Mock->shouldReceive('user')
            ->once()
            ->with('verified-google-credential', Mockery::any())
            ->andReturnUsing(function (string $credential, ?array &$rawPayload = null): OneTapGoogleUser {
                $rawPayload = [
                    'sub' => '123456789',
                    'email' => 'google@example.com',
                    'picture' => 'https://example.com/new.jpg',
                ];

                return OneTapGoogleUser::from([
                    OneTapGoogleUser::sub => '123456789',
                    OneTapGoogleUser::name => 'Google User',
                    OneTapGoogleUser::given_name => 'Google',
                    OneTapGoogleUser::family_name => 'User',
                    OneTapGoogleUser::picture => 'https://example.com/new.jpg',
                    OneTapGoogleUser::email => 'google@example.com',
                    OneTapGoogleUser::email_verified => true,
                    OneTapGoogleUser::id => '123456789',
                    OneTapGoogleUser::verified_email => true,
                ]);
            });
    });

    $this->postJson(Web::googleOneTap->value, [
        'credential' => 'invalid-google-credential',
    ])->assertUnprocessable()->assertExactJson([
        'message' => 'Google sign-in could not be verified.',
    ]);

    $this->assertGuest();

    $this->postJson(Web::googleOneTap->value, [
        'credential' => 'verified-google-credential',
    ])->assertOk()->assertExactJson([
        'redirect' => url(Web::home->value),
    ]);

    $this->assertAuthenticated();
    $User = User::query()->where(Users::email->value, 'google@example.com')->sole();

    expect($User->oauthProviders()->sole()->picture)->toBe('https://example.com/avatar.jpg')
        ->and(session(SessionKey::user_picture->value))->toBe('https://example.com/avatar.jpg');

    // The endpoint is for guests, so the upsert path is reached by signing out first.
    $this->get(Web::logout->value);

    $this->postJson(Web::googleOneTap->value, [
        'credential' => 'verified-google-credential',
    ])->assertOk();

    $this->assertAuthenticatedAs($User);

    $OauthProvider = OauthProvider::query()->where(OauthProviders::sub->value, '123456789')->sole();

    expect($OauthProvider->picture)->toBe('https://example.com/new.jpg')
        ->and(OauthProvider::query()->where(OauthProviders::sub->value, '123456789')->count())->toBe(1)
        ->and($OauthProvider->payload)->not->toBeNull();
    assert($OauthProvider->payload !== null);

    expect($OauthProvider->payload['picture'])->toBe('https://example.com/new.jpg');
});

test('google login creates a verified user, persists the payload and updates the provider', function (): void {
    Socialite::fake(SocialiteDriver::google->value, GoogleUser::fake([
        'sub' => '123456789',
        'name' => 'Google User',
        'given_name' => 'Google',
        'family_name' => 'User',
        'picture' => 'https://example.com/avatar.jpg',
        'email' => 'google@example.com',
        'email_verified' => true,
        'verified_email' => true,
    ]));

    $this->get(Web::googleCallback->value)->assertRedirect(Web::home->value);

    $User = User::query()->where(Users::email->value, 'google@example.com')->sole();

    $this->assertAuthenticatedAs($User);
    expect($User->name)->toBe('Google User')
        ->and($User->hasVerifiedEmail())->toBeTrue()
        ->and($User->oauthProviders()->sole()->sub)->toBe('123456789')
        ->and(session(SessionKey::user_picture->value))->toBe('https://example.com/avatar.jpg')
        ->and(session(SessionKey::sign_up_method->value))->toBe('Google');

    $OauthProvider = OauthProvider::query()->where(OauthProviders::sub->value, '123456789')->sole();

    expect($OauthProvider->payload)->not->toBeNull();
    assert($OauthProvider->payload !== null);

    expect($OauthProvider->payload['sub'])->toBe('123456789')
        ->and($OauthProvider->payload['email'])->toBe('google@example.com')
        ->and($OauthProvider->provider_id)->toBe(OauthProviderId::google);

    Socialite::fake(SocialiteDriver::google->value, GoogleUser::fake([
        'sub' => '123456789',
        'name' => 'New Name',
        'given_name' => 'New',
        'family_name' => 'Name',
        'picture' => 'https://example.com/new.jpg',
        'email' => 'new@example.com',
        'email_verified' => true,
        'verified_email' => true,
    ]));

    $this->get(Web::googleCallback->value)->assertRedirect(Web::home->value);

    $Updated = OauthProvider::query()->where(OauthProviders::sub->value, '123456789')->sole();

    $this->assertAuthenticatedAs($User);
    expect($Updated->name)->toBe('New Name')
        ->and($Updated->email)->toBe('new@example.com')
        ->and($Updated->picture)->toBe('https://example.com/new.jpg')
        ->and(OauthProvider::query()->where(OauthProviders::sub->value, '123456789')->count())->toBe(1);
});

test('google login verifies an existing user', function (): void {
    $User = User::factory()->unverified()->createOne([
        Users::email->value => 'google@example.com',
    ]);
    Socialite::fake(SocialiteDriver::google->value, GoogleUser::fake([
        'sub' => '123456789',
        'given_name' => 'Test',
        'family_name' => 'User',
        'picture' => 'https://example.com/avatar.jpg',
        'email' => 'google@example.com',
        'email_verified' => true,
        'verified_email' => true,
    ]));

    $this->get(Web::googleCallback->value)->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($User);
    expect($User->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(User::query()->where(Users::email->value, 'google@example.com')->count())->toBe(1)
        ->and(session()->missing(SessionKey::sign_up_method->value))->toBeTrue();
});

test('google login redirects to google and refuses every failed callback', function (): void {
    Socialite::fake(SocialiteDriver::google->value);

    $this->get(Web::googleRedirect->value)
        ->assertRedirect('https://socialite.fake/google/authorize');

    Socialite::fake(SocialiteDriver::google->value, GoogleUser::fake([
        'sub' => '123456789',
        'given_name' => 'Test',
        'family_name' => 'User',
        'picture' => 'https://example.com/avatar.jpg',
        'email' => 'google@example.com',
        'email_verified' => false,
        'verified_email' => false,
    ]));

    $this->get(Web::googleCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
    expect(User::query()->where(Users::email->value, 'google@example.com')->doesntExist())->toBeTrue();

    /** @var SocialiteUser&MockInterface $SocialiteUser */
    $SocialiteUser = mock(SocialiteUser::class);
    Socialite::fake(SocialiteDriver::google->value, $SocialiteUser);

    $this->get(Web::googleCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();

    $SocialiteProvider = new class implements SocialiteProvider
    {
        public function redirect(): never
        {
            throw new LogicException('Not used by this test.');
        }

        public function user(): never
        {
            throw new InvalidStateException;
        }
    };
    Socialite::shouldReceive('driver')
        ->once()
        ->with(SocialiteDriver::google->value)
        ->andReturn($SocialiteProvider);

    $this->get(Web::googleCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors([
            LoginForm::email => 'Your Google sign-in session expired. Please try again.',
        ]);

    $this->assertGuest();
});
