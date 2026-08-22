<?php

use App\Helpers\OauthProviderId;
use App\Helpers\SessionKey;
use App\Helpers\SocialiteDriver;
use App\Models\OauthProvider;
use App\Models\User;
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

test('the github user model reads a payload, normalises the email and falls back to the login', function (): void {
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
});

test('the redirect sends the browser to github and asks robots to stay away', function (): void {
    Socialite::fake(SocialiteDriver::github->value);

    $Response = $this->get(Web::githubRedirect->value);

    $Response->assertRedirect('https://socialite.fake/github/authorize');
    expect($Response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

test('an oauth provider row records the display name split into given and family names', function (): void {
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
});

test('the callback logs in a new user, records the payload and upserts on the next visit', function (): void {
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
});

test('the callback refuses an email github never verified', function (): void {
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
});

test('the callback turns every socialite failure into a login error', function (): void {
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

    Mockery::close();

    /** @var SocialiteUser&MockInterface $SocialiteUser */
    $SocialiteUser = mock(SocialiteUser::class);
    Socialite::fake(SocialiteDriver::github->value, $SocialiteUser);

    $this->get(Web::githubCallback->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHasErrors(LoginForm::email);

    $this->assertGuest();
});

test('the callback verifies an existing user and honours the intended url', function (): void {
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
});
