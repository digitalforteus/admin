<?php

use App\Helpers\OauthProviderId;
use App\Helpers\SessionKey;
use App\Helpers\SocialiteDriver;
use App\Models\OauthProvider;
use App\Models\User;
use App\Modules\Login\GitHubCallbackController;
use App\Modules\Login\GitHubLogin;
use App\Modules\Login\GitHubRedirectController;
use App\Modules\Login\GitHubUser;
use App\Modules\Login\LoginForm;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubSocialiteUser;
use Mockery\MockInterface;

// ==================== GitHubUser Tests ====================

test('GitHubUser can be created from raw data', function (): void {
    $data = [
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'The Octocat',
        GitHubUser::email => '  OCTOCAT@GITHUB.COM  ',
        GitHubUser::avatar_url => 'https://avatars.githubusercontent.com/u/1?v=4',
        GitHubUser::bio => 'There once was...',
        GitHubUser::blog => 'https://github.blog',
        GitHubUser::company => 'GitHub',
        GitHubUser::location => 'San Francisco',
    ];

    $GitHubUser = GitHubUser::from($data);

    expect($GitHubUser->id)->toBe('123456')
        ->and($GitHubUser->login)->toBe('octocat')
        ->and($GitHubUser->name)->toBe('The Octocat')
        ->and($GitHubUser->email)->toBe('octocat@github.com')
        ->and($GitHubUser->avatar_url)->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and($GitHubUser->bio)->toBe('There once was...')
        ->and($GitHubUser->blog)->toBe('https://github.blog')
        ->and($GitHubUser->company)->toBe('GitHub')
        ->and($GitHubUser->location)->toBe('San Francisco');
});

test('GitHubUser casts email to lowercase and trimmed', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::email => '  OCTOCAT@GITHUB.COM  ',
    ]);

    expect($GitHubUser->email)->toBe('octocat@github.com');
});

test('GitHubUser handles null email', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::email => null,
    ]);

    expect($GitHubUser->email)->toBeNull();
});

test('GitHubUser hasVerifiedEmail returns true for valid email', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::email => 'octocat@github.com',
    ]);

    expect($GitHubUser->hasVerifiedEmail())->toBeTrue();
});

test('GitHubUser hasVerifiedEmail returns false for null email', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::email => null,
    ]);

    expect($GitHubUser->hasVerifiedEmail())->toBeFalse();
});

test('GitHubUser hasVerifiedEmail returns false for invalid email', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::email => 'not-an-email',
    ]);

    expect($GitHubUser->hasVerifiedEmail())->toBeFalse();
});

test('GitHubUser getDisplayName returns name when present', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'The Octocat',
    ]);

    expect($GitHubUser->getDisplayName())->toBe('The Octocat');
});

test('GitHubUser getDisplayName returns login when name is null', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => null,
    ]);

    expect($GitHubUser->getDisplayName())->toBe('octocat');
});

test('GitHubUser getDisplayName returns login when name is empty string', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => '',
    ]);

    expect($GitHubUser->getDisplayName())->toBe('octocat');
});

test('GitHubUser handles missing optional fields', function (): void {
    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
    ]);

    expect($GitHubUser->name)->toBeNull()
        ->and($GitHubUser->email)->toBeNull()
        ->and($GitHubUser->avatar_url)->toBeNull()
        ->and($GitHubUser->bio)->toBeNull()
        ->and($GitHubUser->blog)->toBeNull()
        ->and($GitHubUser->company)->toBeNull()
        ->and($GitHubUser->location)->toBeNull();
});

// ==================== GitHubRedirectController Tests ====================

test('github redirect controller redirects to github', function (): void {
    Socialite::fake(SocialiteDriver::github->value);

    $this->get(Web::githubRedirect->value)
        ->assertRedirect('https://socialite.fake/github/authorize');
});

test('github redirect controller sets noindex nofollow header', function (): void {
    Socialite::fake(SocialiteDriver::github->value);

    $response = $this->get(Web::githubRedirect->value);

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

// ==================== GitHubLogin Tests ====================

test('github login creates oauth provider with correct data', function (): void {
    $User = User::factory()->createOne([
        Users::email->value => 'octocat@github.com',
    ]);

    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'The Octocat',
        GitHubUser::email => 'octocat@github.com',
        GitHubUser::avatar_url => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]);

    $User->oauthProviders()->updateOrCreate(
        [
            OauthProviders::provider_id->value => OauthProviderId::github->value,
            OauthProviders::sub->value => '123456',
        ],
        [
            OauthProviders::sub->value => $GitHubUser->id,
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

    expect($OauthProvider->sub)->toBe('123456')
        ->and($OauthProvider->name)->toBe('The Octocat')
        ->and($OauthProvider->given_name)->toBe('The')
        ->and($OauthProvider->family_name)->toBe('Octocat')
        ->and($OauthProvider->picture)->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and($OauthProvider->email)->toBe('octocat@github.com');
});

test('github login handles display name with multiple spaces', function (): void {
    $User = User::factory()->createOne([
        Users::email->value => 'test@github.com',
    ]);

    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'The Great Octocat Smith',
        GitHubUser::email => 'test@github.com',
    ]);

    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::github->value,
        OauthProviders::sub->value => '123456',
        OauthProviders::name->value => $GitHubUser->getDisplayName(),
        OauthProviders::given_name->value => Str::before($GitHubUser->getDisplayName(), ' '),
        OauthProviders::family_name->value => Str::contains($GitHubUser->getDisplayName(), ' ')
            ? Str::afterLast($GitHubUser->getDisplayName(), ' ')
            : '',
        OauthProviders::picture->value => '',
        OauthProviders::email->value => 'test@github.com',
        OauthProviders::email_verified->value => false,
        OauthProviders::hd->value => null,
        OauthProviders::id->value => '123456',
        OauthProviders::verified_email->value => false,
        OauthProviders::link->value => null,
    ]);

    $OauthProvider = $User->oauthProviders()->sole();

    expect($OauthProvider->given_name)->toBe('The')
        ->and($OauthProvider->family_name)->toBe('Smith');
});

test('github login handles display name with single word', function (): void {
    $User = User::factory()->createOne([
        Users::email->value => 'test@github.com',
    ]);

    $GitHubUser = GitHubUser::from([
        GitHubUser::id => '123456',
        GitHubUser::login => 'octocat',
        GitHubUser::name => 'Octocat',
        GitHubUser::email => 'test@github.com',
    ]);

    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::github->value,
        OauthProviders::sub->value => '123456',
        OauthProviders::name->value => $GitHubUser->getDisplayName(),
        OauthProviders::given_name->value => Str::before($GitHubUser->getDisplayName(), ' '),
        OauthProviders::family_name->value => Str::contains($GitHubUser->getDisplayName(), ' ')
            ? Str::afterLast($GitHubUser->getDisplayName(), ' ')
            : '',
        OauthProviders::picture->value => '',
        OauthProviders::email->value => 'test@github.com',
        OauthProviders::email_verified->value => false,
        OauthProviders::hd->value => null,
        OauthProviders::id->value => '123456',
        OauthProviders::verified_email->value => false,
        OauthProviders::link->value => null,
    ]);

    $OauthProvider = $User->oauthProviders()->sole();

    expect($OauthProvider->given_name)->toBe('Octocat')
        ->and($OauthProvider->family_name)->toBeEmpty();
});

// ==================== GitHubCallbackController Tests ====================

test('github callback logs in with a verified email', function (): void {
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
        ->and($User->oauthProviders()->sole()->sub)->toBe('123456')
        ->and(session(SessionKey::user_picture->value))->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and(session(SessionKey::sign_up_method->value))->toBe('GitHub');
});

test('github callback persists the raw oauth payload', function (): void {
    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $User = User::query()->where(Users::email->value, 'octocat@github.com')->sole();
    $OauthProvider = $User->oauthProviders()->sole();
    $OauthProvider->refresh();

    expect($OauthProvider->payload)->not->toBeNull();
    assert($OauthProvider->payload !== null);

    expect($OauthProvider->payload['id'])->toBe('123456')
        ->and($OauthProvider->payload['login'])->toBe('octocat')
        ->and($OauthProvider->payload['email'])->toBe('octocat@github.com');
});

test('github login upserts the oauth provider without creating a duplicate row', function (): void {
    $User = User::factory()->createOne([
        Users::email->value => 'octocat@github.com',
    ]);
    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::github->value,
        OauthProviders::sub->value => '123456',
        OauthProviders::name->value => 'Old Name',
        OauthProviders::given_name->value => 'Old',
        OauthProviders::family_name->value => 'Name',
        OauthProviders::picture->value => 'https://example.com/old.jpg',
        OauthProviders::email->value => 'old@github.com',
        OauthProviders::email_verified->value => true,
        OauthProviders::hd->value => null,
        OauthProviders::id->value => '123456',
        OauthProviders::verified_email->value => true,
        OauthProviders::link->value => null,
    ]);

    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $OauthProvider = OauthProvider::query()->where(OauthProviders::sub->value, '123456')->sole();

    $this->assertAuthenticatedAs($User);
    expect($OauthProvider->name)->toBe('The Octocat')
        ->and($OauthProvider->email)->toBe('octocat@github.com')
        ->and($OauthProvider->picture)->toBe('https://avatars.githubusercontent.com/u/1?v=4')
        ->and(OauthProvider::query()->where(OauthProviders::sub->value, '123456')->count())->toBe(1);
});

test('github callback rejects an unverified email', function (): void {
    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => null,
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
    expect(User::query()->where(Users::email->value, 'octocat@github.com')->doesntExist())->toBeTrue();
});

test('github callback redirects stale callbacks back to login', function (): void {
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
        ->with(SocialiteDriver::github->value)
        ->andReturn($SocialiteProvider);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors([
            LoginForm::email => 'Your GitHub sign-in session expired. Please try again.',
        ]);

    $this->assertGuest();
});

test('github callback handles 401 client exception', function (): void {
    $SocialiteProvider = new class implements SocialiteProvider
    {
        public function redirect(): never
        {
            throw new LogicException('Not used by this test.');
        }

        public function user(): never
        {
            throw new ClientException(
                'Unauthorized',
                new Psr7Request('GET', 'https://api.github.com/user'),
                new Psr7Response(401)
            );
        }
    };
    Socialite::shouldReceive('driver')
        ->once()
        ->with(SocialiteDriver::github->value)
        ->andReturn($SocialiteProvider);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors([
            LoginForm::email => 'GitHub credentials are invalid. Please check your GitHub app configuration.',
        ]);

    $this->assertGuest();
});

test('github callback handles non-401 client exception', function (): void {
    $SocialiteProvider = new class implements SocialiteProvider
    {
        public function redirect(): never
        {
            throw new LogicException('Not used by this test.');
        }

        public function user(): never
        {
            throw new ClientException(
                'Bad Request',
                new Psr7Request('GET', 'https://api.github.com/user'),
                new Psr7Response(400)
            );
        }
    };
    Socialite::shouldReceive('driver')
        ->once()
        ->with(SocialiteDriver::github->value)
        ->andReturn($SocialiteProvider);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors([
            LoginForm::email => 'GitHub authentication failed. Please try again.',
        ]);

    $this->assertGuest();
});

test('github callback handles generic throwable exception', function (): void {
    $SocialiteProvider = new class implements SocialiteProvider
    {
        public function redirect(): never
        {
            throw new LogicException('Not used by this test.');
        }

        public function user(): never
        {
            throw new RuntimeException('Unexpected error');
        }
    };
    Socialite::shouldReceive('driver')
        ->once()
        ->with(SocialiteDriver::github->value)
        ->andReturn($SocialiteProvider);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors([
            LoginForm::email => 'An unexpected error occurred during GitHub sign-in. Please try again.',
        ]);

    $this->assertGuest();
});

test('github callback rejects an unexpected socialite user type', function (): void {
    /** @var SocialiteUser&MockInterface $SocialiteUser */
    $SocialiteUser = mock(SocialiteUser::class);
    Socialite::fake(SocialiteDriver::github->value, $SocialiteUser);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
});

test('github callback with invalid email format in response', function (): void {
    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'not-a-valid-email',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
});

test('github callback uses an existing user', function (): void {
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

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($User);
    expect($User->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(User::query()->where(Users::email->value, 'octocat@github.com')->count())->toBe(1)
        ->and(session()->missing(SessionKey::sign_up_method->value))->toBeTrue();
});

test('github callback preserves intended redirect', function (): void {
    Socialite::fake(SocialiteDriver::github->value, GitHubSocialiteUser::fake([
        'id' => '123456',
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
    ]));

    session(['url.intended' => Web::home->value]);

    $this->get(Web::githubCallback->value)->assertRedirect(Web::home->value);

    $this->assertAuthenticated();
});
