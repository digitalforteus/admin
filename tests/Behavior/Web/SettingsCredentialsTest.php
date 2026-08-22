<?php

use App\Helpers\HttpVerb;
use App\Models\User;
use App\Modules\Settings\Credentials\TokenForm;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\PersonalAccessTokens;
use App\View\DataModels\CredentialsTable;

test('guests cannot list, create or revoke tokens', function (): void {
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
});

test('the page renders the empty state, then the owners tokens and when they were last used', function (): void {
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
});

test('a token is created with public GET abilities, a squished name and an optional expiry', function (): void {
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
});

test('an administrator token is created with only GET abilities across every api', function (): void {
    $User = adminUser();

    $this->actingAs($User)
        ->post(Auth::settingsCredentials->value, [TokenForm::name => 'Admin CLI'])
        ->assertSessionHasNoErrors();

    $abilities = $User->tokens()->sole()->abilities ?? [];

    expect($abilities)
        ->toContain(HttpVerb::get->ability(ApiRoute::user->value))
        ->toContain(HttpVerb::get->ability(Admin::api_users->value))
        ->and(array_filter($abilities, static fn (string $ability): bool => str_starts_with($ability, HttpVerb::get->value.HttpVerb::separator)))->toBe($abilities);
});

test('validation refuses a missing name or a past expiry and keeps the old input', function (): void {
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
});

test('a token is revoked, and one belonging to somebody else is not found', function (): void {
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
});
