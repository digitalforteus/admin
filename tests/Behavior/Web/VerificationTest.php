<?php

use App\Helpers\HttpHeader;
use App\Models\User;
use App\Modules\Register\RegisterFormFactory;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('the notice is for unverified users only', function (): void {
    $this->get(Auth::verificationNotice->value)
        ->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->unverified()->createOne())
        ->get(Auth::verificationNotice->value)
        ->assertOk();

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::verificationNotice->value)
        ->assertRedirect(Web::home->value);
});

test('an unverified user is blocked from every authenticated route, in every environment and over htmx', function (): void {
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

});

test('a valid signed link verifies the user whoever is signed in', function (): void {
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
});

test('an invalid hash or an unsigned link is rejected', function (): void {
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
});

test('a verification notification is sent on request and on registration', function (): void {
    Notification::fake();
    $User = User::factory()->unverified()->createOne();

    $this->actingAs($User)
        ->post(Auth::verificationSend->value)
        ->assertRedirect()
        ->assertSessionHas('status', 'Verification link sent!');

    Notification::assertSentTo($User, VerifyEmail::class);

    $this->get(Web::logout->value);

    $RegisterForm = RegisterFormFactory::factory()->make();

    $this->post(Web::register->value, $RegisterForm->toArray())
        ->assertRedirect(Auth::verificationNotice->value);

    $Registered = User::query()->where(Users::email->value, $RegisterForm->email)->firstOrFail();

    Notification::assertSentTo($Registered, VerifyEmail::class);
    expect($Registered->hasVerifiedEmail())->toBeFalse();
});
