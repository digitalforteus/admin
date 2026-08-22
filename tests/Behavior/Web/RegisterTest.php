<?php

use App\Helpers\HttpHeader;
use App\Helpers\SessionKey;
use App\Http\Middleware\RateLimitHeaders;
use App\Models\User;
use App\Modules\Register\RegisterForm;
use App\Modules\Register\RegisterFormFactory;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

test('a registration is throttled, refused field by field, and signs the user in behind an address they still have to verify', function (): void {
    // Every route this test walks is throttled per address, and walking them all
    // in one visit is more than one caller is allowed in a minute. The ceiling a
    // registration keeps for itself is a different one, and is left standing.
    $this->withoutMiddleware(RateLimitHeaders::class);

    $RegisterForm = RegisterFormFactory::factory()->make();

    $key = 'register:'.$RegisterForm->email;
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key);
    }

    $this->post(Web::register->value, $RegisterForm->toArray())
        ->assertSessionHasErrors(Users::email->value);

    $this->assertGuest();
    $this->assertDatabaseMissing((new User)->getTable(), [
        Users::email->value => $RegisterForm->email,
    ]);

    // The ceiling is per address and the factory always offers the same one, so
    // the attempts this spent are returned before the address is used again.
    RateLimiter::clear($key);

    $this->forgetCredentials();

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

    $this->get(Web::logout->value);

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

    $this->forgetCredentials();

    $this->get(Auth::verificationNotice->value)
        ->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->unverified()->createOne())
        ->get(Auth::verificationNotice->value)
        ->assertOk();

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::verificationNotice->value)
        ->assertRedirect(Web::home->value);

    $User = User::factory()->unverified()->createOne();
    $credential = str_repeat('0', 26);
    $credentialUrl = Auth::settingsCredential->url([
        Auth::credentialParameter => $credential,
    ]);

    foreach ([
        Auth::dashboard->value,
        Auth::confirmPassword->value,
        Auth::settings->value,
        Auth::settingsProfile->value,
        Auth::settingsSecurity->value,
        Auth::settingsCredentials->value,
        $credentialUrl,
        Auth::settingsAppearance->value,
    ] as $url) {
        $this->actingAs($User)
            ->get($url)
            ->assertRedirect(Auth::verificationNotice->value);
    }

    foreach ([
        Auth::confirmPassword->value,
        Auth::settingsProfile->value,
        Auth::settingsSecurity->value,
        Auth::settingsCredentials->value,
        $credentialUrl,
        Auth::settingsAppearance->value,
    ] as $url) {
        $this->actingAs($User)
            ->post($url)
            ->assertRedirect(Auth::verificationNotice->value);
    }

    $this->actingAs($User)
        ->delete($credentialUrl)
        ->assertRedirect(Auth::verificationNotice->value);

    config(['app.env' => 'production']);

    $this->actingAs($User)
        ->get(Auth::dashboard->value)
        ->assertRedirect(Auth::verificationNotice->value);

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::dashboard->value)
        ->assertNoContent();

    // The header stays on every later request, so htmx is asserted last.
    $this->actingAs($User)
        ->withHeader(HttpHeader::HxRequest->value, 'true')
        ->get(Auth::dashboard->value)
        ->assertNoContent(403)
        ->assertHeader(HttpHeader::HxRedirect->value, Auth::verificationNotice->value);

    Event::fake([Verified::class]);

    $signedFor = static fn (User $User): string => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $User->getKey(),
        'hash' => sha1($User->getEmailForVerification()),
    ]);

    $Self = User::factory()->unverified()->createOne();

    $this->actingAs($Self)
        ->get($signedFor($Self))
        ->assertRedirect(Web::home->value)
        ->assertSessionHas('status', 'Email verified successfully.');

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('Email verified successfully.');

    expect($Self->refresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);

    $Guest = User::factory()->unverified()->createOne();

    $this->get(Web::logout->value);
    $this->get($signedFor($Guest))->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($Guest);
    expect($Guest->refresh()->hasVerifiedEmail())->toBeTrue();

    $Switched = User::factory()->unverified()->createOne();

    $this->actingAs(User::factory()->createOne())
        ->get($signedFor($Switched))
        ->assertRedirect(Web::home->value);

    $this->assertAuthenticatedAs($Switched);
    expect($Switched->refresh()->hasVerifiedEmail())->toBeTrue();

    $User = User::factory()->unverified()->createOne();

    $this->actingAs($User)
        ->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $User->getKey(),
            'hash' => sha1('not-the-right-email'),
        ]))
        ->assertForbidden();

    $this->actingAs($User)
        ->get(route('verification.verify', [
            'id' => $User->getKey(),
            'hash' => sha1($User->getEmailForVerification()),
        ]))
        ->assertForbidden();

    expect($User->refresh()->hasVerifiedEmail())->toBeFalse();

    Notification::fake();
    $User = User::factory()->unverified()->createOne();

    $this->actingAs($User)
        ->post(Auth::verificationSend->value)
        ->assertRedirect()
        ->assertSessionHas('status', 'Verification link sent!');

    Notification::assertSentTo($User, VerifyEmail::class);

    $this->get(Web::logout->value);

    // An address of its own, so this registration is a first one rather than a
    // second attempt at the address every earlier segment has been spending.
    $RegisterForm = RegisterFormFactory::factory()
        ->set([RegisterForm::email => 'notified-on-registration@example.com'])
        ->make();

    $this->post(Web::register->value, $RegisterForm->toArray())
        ->assertRedirect(Auth::verificationNotice->value);

    $Registered = User::query()->where(Users::email->value, $RegisterForm->email)->firstOrFail();

    Notification::assertSentTo($Registered, VerifyEmail::class);
    expect($Registered->hasVerifiedEmail())->toBeFalse();
});
