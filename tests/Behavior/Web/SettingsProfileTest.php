<?php

use App\Models\Session;
use App\Models\User;
use App\Modules\Settings\Profile\ProfileForm;
use App\Modules\Settings\Sessions\SessionDestroyController;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Sessions;
use App\Sources\Db\App\Users;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth as AuthFacade;

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

test('guests cannot reach the profile or any session route', function (): void {
    $this->get(Auth::settingsProfile->value)->assertRedirect(Web::login->value);
    $this->post(Auth::settingsProfile->value, [ProfileForm::name => 'Jane Doe'])->assertRedirect(Web::login->value);
    $this->get(Auth::settingsSessions->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsSessions->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsSession->url([Auth::sessionParameter => 'session']))->assertRedirect(Web::login->value);
});

test('the settings root redirects to the profile, which renders the immutable fields', function (): void {
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
});

test('a name is squished on the way in and the email is left alone', function (): void {
    $User = User::factory()->createOne([
        Users::name->value => 'John Doe',
        Users::email->value => 'john@example.com',
    ]);

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
});

test('validation refuses a missing or oversized name and keeps the old input', function (): void {
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
});

test('the sessions page lists only the owners sessions, which are revoked one at a time or all at once', function (): void {
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
});

test('revoking the current session signs the user out', function (): void {
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
});
