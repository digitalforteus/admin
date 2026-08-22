<?php

use App\Helpers\OauthProviderId;
use App\Helpers\SessionKey;
use App\Helpers\SocialiteDriver;
use App\Http\Middleware\RateLimitHeaders;
use App\Models\OauthProvider;
use App\Models\User;
use App\Modules\Login\GitHubUser;
use App\Modules\Login\GoogleCredential;
use App\Modules\Login\GoogleUser as OneTapGoogleUser;
use App\Modules\Login\LoginForm;
use App\Modules\Login\LoginFormFactory;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubSocialiteUser;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery\MockInterface;

test('a login is refused unless the credentials match, every provider signs the same account in, and a logout ends the session', function (): void {
    // Every sign-in path this test walks is throttled per address, and walking
    // them all in one visit is more than one caller is allowed in a minute.
    $this->withoutMiddleware(RateLimitHeaders::class);

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

    $this->get(Web::logout->value);

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

    // The redirect flow signs up an account of its own, so the one the credential
    // flow left behind is removed rather than found.
    $this->get(Web::logout->value);
    $this->flushSession();
    $User->delete();

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

    // Verifying an account the callback finds is a different path from creating
    // one, so the account the sign-up left is cleared and stood up unverified.
    $this->get(Web::logout->value);
    $this->flushSession();
    $User->delete();

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

    // An address google never verified reaches no account at all, so there is
    // none for it to be mistaken for.
    $this->get(Web::logout->value);
    $this->flushSession();
    $User->delete();

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

    $this->forgetCredentials();

    // Every sign-in path this test walks is throttled per address, and walking
    // them all in one visit is more than one caller is allowed in a minute.
    $this->withoutMiddleware(RateLimitHeaders::class);

    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'The Octocat',
        GitHubUser::email => '  OCTOCAT@GITHUB.COM  ',
        GitHubUser::avatar_url => 'https://avatars.githubusercontent.com/u/1?v=4',
        GitHubUser::bio => 'There once was...',
        GitHubUser::blog => 'https://github.blog',
        GitHubUser::company => 'GitHub',
        GitHubUser::location => 'San Francisco',
    ]);

    expect($GitHubUser->id)->toBe('123456')
        ->and($GitHubUser->login)->toBe('octocat')
        ->and($GitHubUser->name)->toBe('The Octocat')
        ->and($GitHubUser->email)->toBe('octocat@github.com')
        ->and($GitHubUser->avatar_url)->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and($GitHubUser->bio)->toBe('There once was...')
        ->and($GitHubUser->blog)->toBe('https://github.blog')
        ->and($GitHubUser->company)->toBe('GitHub')
        ->and($GitHubUser->location)->toBe('San Francisco')
        ->and($GitHubUser->hasVerifiedEmail())->toBeTrue()
        ->and($GitHubUser->getDisplayName())->toBe('The Octocat');

    $Bare = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
    ]);

    expect($Bare->name)->toBeNull()
        ->and($Bare->email)->toBeNull()
        ->and($Bare->avatar_url)->toBeNull()
        ->and($Bare->bio)->toBeNull()
        ->and($Bare->blog)->toBeNull()
        ->and($Bare->company)->toBeNull()
        ->and($Bare->location)->toBeNull()
        ->and($Bare->hasVerifiedEmail())->toBeFalse()
        ->and($Bare->getDisplayName())->toBe('octocat');

    $Empty = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => '',
        GitHubUser::email => 'not-an-email',
    ]);

    expect($Empty->getDisplayName())->toBe('octocat')
        ->and($Empty->hasVerifiedEmail())->toBeFalse();

    $Null = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => null,
        GitHubUser::email => null,
    ]);

    expect($Null->getDisplayName())->toBe('octocat')
        ->and($Null->email)->toBeNull();

    // Mocking the factory and faking a driver are different swaps of the same
    // facade, and the mock answers to the fake, so it is stood down first.
    Socialite::swap(app(SocialiteManager::class));
    $this->forgetCredentials();

    Socialite::fake(SocialiteDriver::github->value);

    $Response = $this->get(Web::githubRedirect->value);

    $Response->assertRedirect('https://socialite.fake/github/authorize');
    expect($Response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');

    $rows = [
        ['The Octocat', 'The', 'Octocat'],
        ['The Great Octocat Smith', 'The', 'Smith'],
        ['Octocat', 'Octocat', ''],
    ];

    foreach ($rows as $index => [$name, $given, $family]) {
        $User = User::factory()->createOne([
            Users::email->value => 'octocat'.$index.'@github.com',
        ]);

        $GitHubUser = GitHubUser::from([
            GitHubUser::id => '12345'.$index,
            GitHubUser::login => 'octocat',
            GitHubUser::name => $name,
            GitHubUser::email => 'octocat'.$index.'@github.com',
            GitHubUser::avatar_url => 'https://avatars.githubusercontent.com/u/1?v=4',
        ]);

        $User->oauthProviders()->updateOrCreate(
            [
                OauthProviders::provider_id->value => OauthProviderId::github->value,
                OauthProviders::sub->value => $GitHubUser->id,
            ],
            [
                OauthProviders::name->value => $GitHubUser->getDisplayName(),
                OauthProviders::given_name->value => Str::before($GitHubUser->getDisplayName(), ' '),
                OauthProviders::family_name->value => Str::contains($GitHubUser->getDisplayName(), ' ')
                    ? Str::afterLast($GitHubUser->getDisplayName(), ' ')
                    : '',
                OauthProviders::picture->value => $GitHubUser->avatar_url ?? '',
                OauthProviders::email->value => $GitHubUser->email ?? '',
                OauthProviders::email_verified->value => $GitHubUser->hasVerifiedEmail(),
                OauthProviders::hd->value => null,
                OauthProviders::id->value => $GitHubUser->id,
                OauthProviders::verified_email->value => $GitHubUser->hasVerifiedEmail(),
                OauthProviders::link->value => null,
            ],
        );

        $OauthProvider = $User->oauthProviders()->sole();

        expect($OauthProvider->sub)->toBe('12345'.$index)
            ->and($OauthProvider->name)->toBe($name)
            ->and($OauthProvider->given_name)->toBe($given)
            ->and($OauthProvider->family_name)->toBe($family)
            ->and($OauthProvider->picture)->toBe('https://avatars.githubusercontent.com/u/1?v=4')
            ->and($OauthProvider->email)->toBe('octocat'.$index.'@github.com');
    }

    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $User = User::query()->where(Users::email->value, 'octocat@github.com')->sole();

    $this->assertAuthenticatedAs($User);
    expect($User->name)->toBe('The Octocat')
        ->and($User->hasVerifiedEmail())->toBeTrue()
        ->and(session(SessionKey::user_picture->value))->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and(session(SessionKey::sign_up_method->value))->toBe('GitHub');

    $OauthProvider = $User->oauthProviders()->sole();

    expect($OauthProvider->sub)->toBe('123456')
        ->and($OauthProvider->payload)->not->toBeNull();
    assert($OauthProvider->payload !== null);

    expect($OauthProvider->payload['id'])->toBe('123456')
        ->and($OauthProvider->payload['login'])->toBe('octocat')
        ->and($OauthProvider->payload['email'])->toBe('octocat@github.com');

    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Second Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/2?v=4',
    ]));

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $Upserted = OauthProvider::query()->where(OauthProviders::sub->value, '123456')->sole();

    $this->assertAuthenticatedAs($User);
    expect($Upserted->name)->toBe('The Second Octocat')
        ->and($Upserted->email)->toBe('octocat@github.com')
        ->and($Upserted->picture)->toBe('https://avatars.githubusercontent.com/u/2?v=4')
        ->and(OauthProvider::query()->where(OauthProviders::sub->value, '123456')->count())->toBe(1)
        ->and(User::query()->where(Users::email->value, 'octocat@github.com')->count())->toBe(1);

    // Refusing the callback is refusing to create an account, so the one the
    // successful callback left is removed before the unusable addresses arrive.
    $this->get(Web::logout->value);
    $User->delete();

    foreach ([null, 'not-a-valid-email'] as $email) {
        Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
            'id' => '123456',
            'login' => 'octocat',
            'name' => 'The Octocat',
            'email' => $email,
            'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
        ]));

        $this->get(Web::githubCallback->value)
            ->assertRedirect(Web::login->value)
            ->assertSessionHasErrors(LoginForm::email);

        $this->assertGuest();
        expect(User::query()->where(Users::email->value, 'octocat@github.com')->doesntExist())->toBeTrue();
    }

    /** @var SocialiteUser&MockInterface $SocialiteUser */
    $SocialiteUser = mock(SocialiteUser::class);
    Socialite::fake(SocialiteDriver::github->value, $SocialiteUser);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();

    $User = User::factory()->unverified()->createOne([
        Users::email->value => 'octocat@github.com',
    ]);

    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    session(['url.intended' => Web::home->value]);

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($User);
    expect($User->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(User::query()->where(Users::email->value, 'octocat@github.com')->count())->toBe(1)
        ->and(session()->missing(SessionKey::sign_up_method->value))->toBeTrue();

    Auth::forgetGuards();
    $this->flushSession();

    $throwing = static fn (Throwable $Throwable): SocialiteProvider => new class($Throwable) implements SocialiteProvider
    {
        public function __construct(private readonly Throwable $Throwable) {}

        public function redirect(): never
        {
            throw new LogicException('Not used by this test.');
        }

        public function user(): never
        {
            throw $this->Throwable;
        }
    };

    $failures = [
        [new InvalidStateException, 'Your GitHub sign-in session expired. Please try again.'],
        [
            new ClientException(
                'Unauthorized',
                new Psr7Request('GET', 'https://api.github.com/user'),
                new Psr7Response(401)
            ),
            'GitHub credentials are invalid. Please check your GitHub app configuration.',
        ],
        [
            new ClientException(
                'Bad Request',
                new Psr7Request('GET', 'https://api.github.com/user'),
                new Psr7Response(400)
            ),
            'GitHub authentication failed. Please try again.',
        ],
        [new RuntimeException('Unexpected error'), 'An unexpected error occurred during GitHub sign-in. Please try again.'],
    ];

    Socialite::shouldReceive('driver')
        ->times(count($failures))
        ->with(SocialiteDriver::github->value)
        ->andReturn(...array_map(
            static fn (array $failure): SocialiteProvider => $throwing($failure[0]),
            $failures,
        ));

    foreach ($failures as [, $message]) {
        $this->get(Web::githubCallback->value)
            ->assertRedirect(Web::login->value)
            ->assertSessionHasErrors([LoginForm::email => $message]);

        $this->assertGuest();
    }

    $this->forgetCredentials();

    $User = User::factory()->createOne();
    $this->actingAs($User);

    $sessionId = session()->getId();
    $token = session()->token();

    $this->get(Web::logout->value)
        ->assertRedirect(Web::home->value);

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId)
        ->and(session()->token())->not->toBe($token);

    $this->get(Web::logout->value)
        ->assertRedirect(Web::home->value);

    $this->assertGuest();
});
