<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\HttpVerb;
use App\Helpers\OauthProviderId;
use App\Helpers\Picture;
use App\Helpers\ProfilePicture;
use App\Helpers\Theme;
use App\Models\Session;
use App\Models\User;
use App\Modules\Settings\Appearance\AppearanceRequest;
use App\Modules\Settings\Authentication\PasswordForm;
use App\Modules\Settings\Credentials\TokenForm;
use App\Modules\Settings\Credentials\TokenUpdateRequest;
use App\Modules\Settings\Profile\ProfileForm;
use App\Modules\Settings\Profile\ProfilePictureRequest;
use App\Modules\Settings\Sessions\SessionDestroyController;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\PersonalAccessTokens;
use App\Sources\Db\App\Sessions;
use App\Sources\Db\App\Users;
use App\View\DataModels\AbilityTable;
use App\View\DataModels\CredentialsTable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

function profileSession(User $User, string $id, int|float|string $lastActivity, string $ip = '127.0.0.1'): void
{
    Session::query()->create([
        Sessions::id->value => $id,
        Sessions::user_id->value => $User->id,
        Sessions::ip_address->value => $ip,
        Sessions::user_agent->value => 'Example Browser',
        Sessions::payload->value => 'private payload',
        Sessions::last_activity->value => $lastActivity,
    ]);
}

function oauthPicture(User $User, string $picture): void
{
    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => 'picture-'.$User->id,
        OauthProviders::name->value => $User->name,
        OauthProviders::given_name->value => 'Given',
        OauthProviders::family_name->value => 'Family',
        OauthProviders::picture->value => $picture,
        OauthProviders::email->value => $User->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::id->value => 'picture-'.$User->id,
        OauthProviders::verified_email->value => true,
    ]);
}

/** @return array<string, string> */
function passwordForm(string $current = 'password', string $new = 'new-password-1234'): array
{
    return [
        PasswordForm::current_password => $current,
        PasswordForm::password => $new,
        PasswordForm::password_confirmation => $new,
    ];
}

/** The management page of a token the given account owns. */
function credentialUrl(User $User, string $name = 'Ability Grid'): string
{
    return Auth::settingsCredential->url([
        Auth::credentialParameter => issuedToken($User, $User->createToken($name))->id,
    ]);
}

test('every settings page is the owners alone, and edits the profile, picture, theme, password and tokens the account holds', function (): void {
    $this->get(Auth::settingsProfile->value)->assertRedirect(Web::login->value);
    $this->post(Auth::settingsProfile->value, [ProfileForm::name => 'Jane Doe'])->assertRedirect(Web::login->value);
    $this->get(Auth::settingsSessions->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsSessions->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsSession->url([Auth::sessionParameter => 'session']))->assertRedirect(Web::login->value);

    $User = User::factory()->createOne([
        Users::name->value => 'John Doe',
        Users::email->value => 'john@example.com',
    ]);

    $this->actingAs($User)
        ->get(Auth::settings->value)
        ->assertRedirect(Auth::settingsProfile->value);

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-profile-form', false)
        ->assertSee(Auth::settingsSecurity->value)
        ->assertSee('John Doe')
        ->assertSee('john@example.com')
        ->assertSee('data-email-verified', false)
        ->assertSee('name="email"', false)
        ->assertSee('readonly', false);

    $this->actingAs(User::factory()->unverified()->createOne())
        ->get(Auth::settingsProfile->value)
        ->assertRedirect(Auth::verificationNotice->value);

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfile->value, [ProfileForm::name => 'Jane Doe'])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile updated.');

    expect($User->refresh()->name)->toBe('Jane Doe');

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfile->value, [ProfileForm::name => '  Jane   Doe  ']);

    expect($User->refresh()->name)->toBe('Jane Doe');

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfile->value, [
            ProfileForm::name => 'John Doe',
            Users::email->value => 'jane@example.com',
        ])
        ->assertRedirect(Auth::settingsProfile->value);

    expect($User->refresh()->email)->toBe('john@example.com')
        ->and($User->name)->toBe('John Doe');

    $User = User::factory()->createOne([Users::name->value => 'John Doe']);

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfile->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfileForm::name);

    expect($User->refresh()->name)->toBe('John Doe');

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfile->value, [ProfileForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(ProfileForm::name)
        ->assertSessionHasInput(ProfileForm::name, str_repeat('a', 256));

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->followingRedirects()
        ->post(Auth::settingsProfile->value, [ProfileForm::name => ''])
        ->assertOk()
        ->assertSee('The name field is required.');

    $User = User::factory()->createOne([Users::remember_token->value => 'remembered']);
    $Other = User::factory()->createOne();
    $lastActivity = now()->subHour()->startOfSecond();
    profileSession($User, 'owned-profile-session', $lastActivity->timestamp);
    profileSession($User, 'second-profile-session', now()->subMinute()->timestamp);
    profileSession($Other, 'other-profile-session', now()->timestamp, '192.0.2.1');

    $this->actingAs($User)
        ->get(Auth::settingsSessions->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee('data-session-row', false)
        ->assertSee('owned-profile-session')
        ->assertSee($lastActivity->toDayDateTimeString())
        ->assertSee('127.0.0.1')
        ->assertSee('Example Browser')
        ->assertDontSee('other-profile-session')
        ->assertDontSee('192.0.2.1')
        ->assertDontSee('private payload');

    $this->actingAs($User)
        ->delete(Auth::settingsSession->url([Auth::sessionParameter => 'owned-profile-session']))
        ->assertRedirect(Auth::settingsSessions->value)
        ->assertSessionHas('status', 'Session revoked.');

    $this->assertDatabaseMissing(Sessions::table(), [Sessions::id->value => 'owned-profile-session']);
    expect($User->fresh()?->remember_token)->toBeNull();
    $this->assertAuthenticatedAs($User);

    $this->actingAs($User)
        ->delete(Auth::settingsSession->url([Auth::sessionParameter => 'other-profile-session']))
        ->assertNotFound();

    $this->assertDatabaseHas(Sessions::table(), [Sessions::id->value => 'other-profile-session']);

    $User->forceFill([Users::remember_token->value => 'remembered'])->save();

    $this->actingAs($User)
        ->delete(Auth::settingsSessions->value)
        ->assertRedirect(Web::login->value)
        ->assertSessionHas('status', 'All sessions cleared.');

    $this->assertDatabaseMissing(Sessions::table(), [Sessions::user_id->value => $User->id]);
    $this->assertDatabaseHas(Sessions::table(), [Sessions::id->value => 'other-profile-session']);
    expect($User->fresh()?->remember_token)->toBeNull();
    $this->assertGuest();

    $User = User::factory()->createOne();
    AuthFacade::login($User);
    $sessionId = str_repeat('a', 40);
    $Session = new Store('test', new ArraySessionHandler(120));
    $Session->setId($sessionId);
    $Request = Request::create(Auth::settingsSessions->value, 'DELETE');
    $Request->setLaravelSession($Session);
    $Request->setUserResolver(static fn (): User => $User);
    profileSession($User, $sessionId, now()->timestamp);

    $Response = app(SessionDestroyController::class)($Request, $sessionId);

    expect($Response->getTargetUrl())->toBe(url(Web::login->value));
    $this->assertGuest();

    $this->forgetCredentials();

    $this->post(Auth::settingsProfilePicture->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsProfilePicture->value)->assertRedirect(Web::login->value);

    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    oauthPicture($User, 'https://example.com/provider.jpg');

    expect(ProfilePicture::url($User))->toBe('https://example.com/provider.jpg');

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field', false)
        ->assertSee('Upload a photo...')
        ->assertSee('Remove photo')
        ->assertSee(Auth::settingsProfilePicture->value)
        ->assertSee('https://example.com/provider.jpg', false);

    $User->update([Users::picture->value => Directory::profile_pictures->value.'/uploaded.jpg']);

    expect(ProfilePicture::url($User->refresh()))
        ->toBe(Disk::public->url(Directory::profile_pictures->value.'/uploaded.jpg'));

    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('first.jpg'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture updated.');

    $first = (string) $User->refresh()->picture;

    expect($first)->toStartWith(Directory::profile_pictures->value.'/');
    $Disk->assertExists($first);

    $this->actingAs($User)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('second.jpg'),
        ]);

    $second = (string) $User->refresh()->picture;

    expect($second)->not->toBe($first);
    $Disk->assertMissing($first);
    $Disk->assertExists($second);

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->delete(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture removed.');

    expect($User->refresh()->picture)->toBeNull();
    $Disk->assertMissing($second);

    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    foreach ([
        UploadedFile::fake()->create('resume.pdf', 16, 'application/pdf'),
        UploadedFile::fake()->image('huge.jpg')->size(Picture::kilobytes + 1),
    ] as $File) {
        $this->actingAs($User)
            ->from(Auth::settingsProfile->value)
            ->post(Auth::settingsProfilePicture->value, [
                ProfilePictureRequest::picture => $File,
            ])
            ->assertRedirect(Auth::settingsProfile->value)
            ->assertSessionHasErrors(ProfilePictureRequest::picture);

        expect($User->refresh()->picture)->toBeNull();
    }

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull();

    $Disk = Storage::fake(Disk::public->value);
    app()->instance('env', 'production');
    Config::set('filesystems.default', Disk::ephemeral);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('menu-disabled', false)
        ->assertDontSee('data-picture-field-form', false);

    $this->actingAs($User)
        ->withoutMiddleware(PreventRequestForgery::class)
        ->from(Auth::settingsProfile->value)
        ->post('https://localhost'.Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull()
        ->and($Disk->allFiles())->toBeEmpty();

    Config::set('filesystems.default', 's3');

    $this->actingAs($User)
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field-form', false);

    $this->forgetCredentials();

    $this->get(Auth::settingsAppearance->value)->assertRedirect(Web::login->value);
    $this->post(Auth::settingsAppearance->value, [AppearanceRequest::theme => Theme::dark->value])
        ->assertRedirect(Web::login->value);

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

    $this->forgetCredentials();

    $this->get(Auth::settingsSecurity->value)->assertRedirect(Web::login->value);
    $this->post(Auth::settingsSecurity->value, passwordForm())->assertRedirect(Web::login->value);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Auth::settingsSecurity->value)
        ->assertOk()
        ->assertSee('data-password-settings', false)
        ->assertSee('data-sign-in-methods', false)
        ->assertSee('data-oauth-providers-empty', false)
        ->assertSee('data-two-factor-settings', false)
        ->assertSee('data-passkey-settings', false)
        ->assertSee('data-passkey-confirm-password', false)
        ->assertSee(Auth::settingsProfile->value);

    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => '123456789',
        OauthProviders::name->value => 'Google User',
        OauthProviders::given_name->value => 'Google',
        OauthProviders::family_name->value => 'User',
        OauthProviders::picture->value => 'https://example.com/avatar.jpg',
        OauthProviders::email->value => 'google@example.com',
        OauthProviders::email_verified->value => true,
        OauthProviders::hd->value => 'example.com',
        OauthProviders::id->value => '123456789',
        OauthProviders::verified_email->value => true,
    ]);

    $Passkey = $User->passkeys()->create([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-id',
        'credential' => [],
    ]);

    $this->actingAs($User->refresh())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(Auth::settingsSecurity->value)
        ->assertOk()
        ->assertSee('data-oauth-provider', false)
        ->assertSee('google@example.com')
        ->assertSee('example.com')
        ->assertSee('https://example.com/avatar.jpg')
        ->assertSee('Verified')
        ->assertSee('data-passkey', false)
        ->assertSee('data-passkey-register', escape: false)
        ->assertSee(route('passkey.destroy', $Passkey));

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(Auth::settingsSecurity->value)
        ->post(route('two-factor.enable'))
        ->assertRedirect(Auth::settingsSecurity->value)
        ->assertSessionHas('status', 'two-factor-authentication-enabled');

    $User->refresh();

    expect($User->two_factor_secret)->not->toBeNull()
        ->and($User->two_factor_recovery_codes)->not->toBeNull()
        ->and($User->two_factor_confirmed_at)->toBeNull();

    $this->actingAs($User)
        ->get(Auth::settingsSecurity->value)
        ->assertOk()
        ->assertSee('data-two-factor-setup', false);

    $Confirmed = User::factory()->createOne();
    app(EnableTwoFactorAuthentication::class)($Confirmed);
    $Confirmed->forceFill([Users::two_factor_confirmed_at->value => now()])->save();

    $this->actingAs($Confirmed->refresh())
        ->get(Auth::settingsSecurity->value)
        ->assertOk()
        ->assertSee('data-two-factor-enabled', false)
        ->assertSee('data-recovery-codes', false)
        ->assertSee($Confirmed->recoveryCodes()[0]);

    $User = User::factory()->createOne([
        Users::password->value => Hash::make('current-password'),
    ]);

    $this->actingAs($User)
        ->from(Auth::settingsSecurity->value)
        ->post(Auth::settingsSecurity->value, passwordForm('current-password'))
        ->assertRedirect(Auth::settingsSecurity->value)
        ->assertSessionHas('status', 'Password updated.');

    expect(Hash::check('new-password-1234', $User->refresh()->password))->toBeTrue();

    $User = User::factory()->createOne([
        Users::password->value => Hash::make('current-password'),
    ]);

    $this->actingAs($User)
        ->from(Auth::settingsSecurity->value)
        ->post(Auth::settingsSecurity->value, passwordForm('wrong-password'))
        ->assertSessionHasErrors(PasswordForm::current_password)
        ->assertSessionMissing(PasswordForm::password);

    $this->actingAs($User)
        ->from(Auth::settingsSecurity->value)
        ->post(Auth::settingsSecurity->value, [
            ...passwordForm('current-password'),
            PasswordForm::password_confirmation => 'mismatch',
        ])
        ->assertSessionHasErrors(PasswordForm::password);

    $this->actingAs($User)
        ->from(Auth::settingsSecurity->value)
        ->post(Auth::settingsSecurity->value)
        ->assertSessionHasErrors([
            PasswordForm::current_password,
            PasswordForm::password,
        ]);

    expect(Hash::check('current-password', $User->refresh()->password))->toBeTrue();

    $this->actingAs($User)
        ->from(Auth::settingsSecurity->value)
        ->followingRedirects()
        ->post(Auth::settingsSecurity->value, passwordForm('wrong-password'))
        ->assertOk()
        ->assertSee('The password is incorrect.');

    $this->forgetCredentials();

    $Owner = User::factory()->createOne();
    $Token = issuedToken($Owner, $Owner->createToken('Laptop CLI'));

    $this->get(Auth::settingsCredentials->value)->assertRedirect(Web::login->value);

    $this->post(Auth::settingsCredentials->value, [TokenForm::name => 'Guest CLI'])
        ->assertRedirect(Web::login->value);

    $this->delete(Auth::settingsCredential->url([Auth::credentialParameter => $Token->id]))
        ->assertRedirect(Web::login->value);

    $this->assertDatabaseMissing(PersonalAccessTokens::table(), [
        PersonalAccessTokens::name->value => 'Guest CLI',
    ]);
    expect($Owner->tokens()->count())->toBe(1);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Auth::settingsCredentials->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee(Auth::settingsCredentials->value)
        ->assertSee('data-credentials-empty', false);

    $lastUsedAt = now()->subDay();
    $Token = issuedToken($User, $User->createToken('Mine'));
    $Token->forceFill([PersonalAccessTokens::last_used_at->value => $lastUsedAt])->save();
    User::factory()->createOne()->createToken('Theirs');

    $this->actingAs($User)
        ->get(Auth::settingsCredentials->value)
        ->assertOk()
        ->assertDontSee('data-credentials-empty', false)
        ->assertSee('Mine')
        ->assertDontSee('Theirs')
        ->assertSee('Last Used')
        ->assertSee($lastUsedAt->toFormattedDateString());

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->post(Auth::settingsCredentials->value, [TokenForm::name => '  Laptop   CLI  '])
        ->assertRedirect(Auth::settingsCredentials->value)
        ->assertSessionHas('status', 'Token created.')
        ->assertSessionHas(CredentialsTable::sessionKey);

    $Token = $User->tokens()->sole();

    expect($Token->name)->toBe('Laptop CLI')
        ->and($Token->abilities)->toBe([HttpVerb::get->ability(ApiRoute::user->value)])
        ->and($Token->expires_at)->toBeNull();

    // The plain text secret is the token id, a separator, then the part that is only
    // ever hashed — so the id and separator are enough to find it on the page.
    $secret = $Token->id.'|';

    $this->get(Auth::settingsCredentials->value)
        ->assertOk()
        ->assertSee('data-token-issued', false)
        ->assertSee('data-token-dialog', false)
        ->assertSee('data-copy-link-trigger', false)
        ->assertSee($secret);

    $this->get(Auth::settingsCredentials->value)
        ->assertOk()
        ->assertDontSee($secret);

    $expiry = now()->addMonth()->toDateString();

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->post(Auth::settingsCredentials->value, [
            TokenForm::name => 'Expiring CLI',
            TokenForm::expires_at => $expiry,
        ])
        ->assertSessionHasNoErrors();

    expect($User->tokens()->where(PersonalAccessTokens::name->value, 'Expiring CLI')->sole()->expires_at?->toDateString())
        ->toBe($expiry);

    $User = adminUser();

    $this->actingAs($User)
        ->post(Auth::settingsCredentials->value, [TokenForm::name => 'Admin CLI'])
        ->assertSessionHasNoErrors();

    $abilities = $User->tokens()->sole()->abilities ?? [];

    expect($abilities)
        ->toContain(HttpVerb::get->ability(ApiRoute::user->value))
        ->toContain(HttpVerb::get->ability(Admin::api_users->value))
        ->and(array_filter($abilities, static fn (string $ability): bool => str_starts_with($ability, HttpVerb::get->value.HttpVerb::separator)))->toBe($abilities);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->post(Auth::settingsCredentials->value)
        ->assertRedirect(Auth::settingsCredentials->value)
        ->assertSessionHasErrors(TokenForm::name);

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->post(Auth::settingsCredentials->value, [
            TokenForm::name => 'Laptop CLI',
            TokenForm::expires_at => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors(TokenForm::expires_at);

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->post(Auth::settingsCredentials->value, [TokenForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(TokenForm::name)
        ->assertSessionHasInput(TokenForm::name, str_repeat('a', 256));

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->followingRedirects()
        ->post(Auth::settingsCredentials->value, [TokenForm::name => ''])
        ->assertOk()
        ->assertSee('The name field is required.');

    expect($User->tokens()->count())->toBe(0);

    $User = User::factory()->createOne();
    $Token = issuedToken($User, $User->createToken('Laptop CLI'));
    $Owner = User::factory()->createOne();
    $Theirs = issuedToken($Owner, $Owner->createToken('Theirs'));

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->delete(Auth::settingsCredential->url([Auth::credentialParameter => $Token->id]))
        ->assertRedirect(Auth::settingsCredentials->value)
        ->assertSessionHas('status', 'Token revoked.');

    expect($User->tokens()->count())->toBe(0);

    $this->actingAs($User)
        ->from(Auth::settingsCredentials->value)
        ->delete(Auth::settingsCredential->url([Auth::credentialParameter => $Theirs->id]))
        ->assertNotFound();

    expect($Owner->tokens()->count())->toBe(1);

    $this->forgetCredentials();

    $Owner = User::factory()->createOne();
    $url = credentialUrl($Owner);

    $this->get($url)->assertRedirect(Web::login->value);
    $this->post($url)->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->createOne())->get($url)->assertNotFound();
    $this->actingAs(User::factory()->createOne())->post($url)->assertNotFound();

    $User = User::factory()->createOne();

    $TestResponse = $this->actingAs($User)->get(credentialUrl($User))->assertOk();

    $TestResponse->assertSee('data-api-group="public"', false)
        ->assertDontSee('data-api-group="admin"', false)
        ->assertSee('data-mcp-connection', false)
        ->assertSee(url(Config::string('openapi.schemas.public.route.uri')))
        ->assertSee('Authorization:Bearer &lt;token&gt;', false)
        ->assertSee('<details', false)
        ->assertSee('href="'.url(Web::llms->value).'"', false)
        ->assertDontSee('npx -y @ivotoby/openapi-mcp-server')
        ->assertSee('data-endpoint-column', false)
        ->assertSee(ApiRoute::user->value)
        // A token the ui issues starts out holding everything, and says so.
        ->assertSee('data-every-ability', false)
        ->assertSee(HttpVerb::get->ability(ApiRoute::user->value))
        ->assertDontSee(HttpVerb::put->ability(ApiRoute::user->value));

    foreach (HttpVerb::cases() as $HttpVerb) {
        $TestResponse->assertSee($HttpVerb->value)
            ->assertSee('data-ability-column="'.$HttpVerb->value.'"', false)
            ->assertSee('aria-label="Toggle all '.$HttpVerb->value.' abilities"', false);
    }

    $User = adminUser();

    $this->actingAs($User)
        ->get(credentialUrl($User))
        ->assertOk()
        ->assertSee('data-api-group="admin"', false)
        ->assertSee(Admin::api_users->value)
        ->assertSee(HttpVerb::get->ability(Admin::api_users->value));

    $User = User::factory()->createOne();
    $url = credentialUrl($User);
    $granted = [HttpVerb::get->ability(ApiRoute::user->value)];

    $this->actingAs($User)
        ->from($url)
        ->post($url, [TokenUpdateRequest::abilities => $granted])
        ->assertRedirect($url)
        ->assertSessionHas('status', 'Abilities updated.');

    expect($User->tokens()->sole()->abilities)->toBe($granted);

    $this->actingAs($User)
        ->get($url)
        ->assertOk()
        ->assertDontSee('data-every-ability', false)
        ->assertSee(AbilityTable::field);

    $User = User::factory()->createOne();
    $url = credentialUrl($User);

    $this->actingAs($User)->post($url)->assertSessionHasNoErrors();

    expect($User->tokens()->sole()->abilities)->toBe([]);

    foreach ([
        [HttpVerb::every, 'DELETE'.HttpVerb::separator.'/api/nowhere'],
        [HttpVerb::get->ability(Admin::api_users->value)],
    ] as $abilities) {
        $this->actingAs($User)->post($url, [TokenUpdateRequest::abilities => $abilities]);

        expect($User->tokens()->sole()->abilities)->toBe([]);
    }
});
