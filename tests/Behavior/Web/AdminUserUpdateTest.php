<?php

use App\Helpers\Gravatar;
use App\Helpers\Role;
use App\Helpers\Theme;
use App\Models\User;
use App\Modules\Admin\Users\Update\UsersUpdateRequest;
use App\Routes\Admin;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Hash;

function editUrl(User $User): string
{
    return Admin::user->url([Admin::userParameter => $User->id]);
}

/**
 * @param  array<string, string|bool>  $overrides
 * @return array<string, string|bool>
 */
function payload(User $User, array $overrides = []): array
{
    return [
        UsersUpdateRequest::name => $User->name,
        UsersUpdateRequest::email => $User->email,
        UsersUpdateRequest::verified => $User->email_verified_at !== null,
        UsersUpdateRequest::admin => $User->hasRole(Role::admin->value),
        UsersUpdateRequest::theme => $User->theme->value,
        ...$overrides,
    ];
}

test('guests and users without the admin role are refused the page and the form', function (): void {
    $User = User::factory()->createOne();

    $this->get(editUrl($User))->assertRedirect(Web::login->value);
    $this->post(editUrl($User), payload($User))->assertRedirect(Web::login->value);

    $this->actingAs($User)->get(editUrl($User))->assertForbidden();
    $this->actingAs($User)->post(editUrl($User), payload($User))->assertForbidden();
});

test('the page renders the account it edits and the index links to it', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->get(editUrl($User))
        ->assertOk()
        ->assertSee($User->name)
        ->assertSee('src="'.e(Gravatar::url($User->email)).'"', false)
        ->assertSee('alt="'.e($User->name).'"', false)
        ->assertSee('value="'.$User->email.'"', false)
        ->assertSee('data-user-status', false)
        ->assertSee($User->id)
        ->assertSee('data-record-details', false)
        ->assertSee('data-authentication-providers', false)
        ->assertSee('data-delete-user', false);

    $this->actingAs(adminUser())
        ->get(Admin::users->value)
        ->assertOk()
        ->assertSee('<a href="'.editUrl($User).'" class="link" title="'.$User->email.'">'.$User->email.'</a>', false)
        ->assertDontSee('class="btn btn-ghost btn-xs">Edit</a>', false);
});

test('an unknown user is not found', function (): void {
    $this->actingAs(adminUser())
        ->get(Admin::user->url([Admin::userParameter => 'nobody']))
        ->assertNotFound();

    $this->actingAs(adminUser())
        ->post(Admin::user->url([Admin::userParameter => 'nobody']), [
            UsersUpdateRequest::name => 'Ada Lovelace',
            UsersUpdateRequest::email => 'ada@example.com',
        ])
        ->assertNotFound();
});

test('the name, email, theme and an optional new password are saved', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::name => 'Ada Lovelace',
            UsersUpdateRequest::email => 'ada@example.com',
        ]))
        ->assertRedirect(editUrl($User))
        ->assertSessionHas('status');

    $this->assertDatabaseHas(Users::table(), [
        Users::id->value => $User->getKey(),
        Users::name->value => 'Ada Lovelace',
        Users::email->value => 'ada@example.com',
    ]);

    $this->actingAs(adminUser())
        ->post(editUrl($User), payload($User->refresh(), [
            UsersUpdateRequest::theme => Theme::dark->value,
            UsersUpdateRequest::password => 'new-password-1234',
            UsersUpdateRequest::password_confirmation => 'new-password-1234',
        ]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->theme)->toBe(Theme::dark)
        ->and(Hash::check('new-password-1234', $User->password))->toBeTrue();

    // The address the account already holds is its own, so uniqueness lets it through.
    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::name => 'Grace Hopper']))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->name)->toBe('Grace Hopper');
});

test('invalid input is refused and the form repopulates what was submitted', function (): void {
    $User = User::factory()->createOne();
    $Other = User::factory()->createOne();

    $this->actingAs(adminUser());

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::theme => 'sepia',
            UsersUpdateRequest::password => 'new-password-1234',
            UsersUpdateRequest::password_confirmation => 'mismatch',
        ]))
        ->assertSessionHasErrors([UsersUpdateRequest::theme, UsersUpdateRequest::password]);

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::email => $Other->email]))
        ->assertRedirect(editUrl($User))
        ->assertSessionHasErrors(UsersUpdateRequest::email);

    expect($User->refresh()->email)->not->toBe($Other->email);

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::name => '',
            UsersUpdateRequest::email => 'ada@example.com',
        ]))
        ->assertSessionHasErrors(UsersUpdateRequest::name);

    $this->get(editUrl($User))
        ->assertOk()
        ->assertSee('value="ada@example.com"', false);
});

test('a verification is stamped, cleared, and left where it already stands', function (): void {
    $User = User::factory()->createOne();

    expect($User->email_verified_at)->not->toBeNull();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::verified => false]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->email_verified_at)->toBeNull();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::verified => true]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->email_verified_at)->not->toBeNull();

    $verified = now()->subMonth();
    $Held = User::factory()->createOne([Users::email_verified_at->value => $verified]);

    $this->actingAs(adminUser())
        ->from(editUrl($Held))
        ->post(editUrl($Held), payload($Held, [UsersUpdateRequest::verified => true]))
        ->assertSessionHasNoErrors();

    expect($Held->refresh()->email_verified_at?->toDateTimeString())->toBe($verified->toDateTimeString());
});

// Revoking it from the account making the request is the one change that cannot be
// undone from these pages, because the page that undoes it is behind the role.
test('the admin role is granted and revoked, but never from the requesting account', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::admin => true]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->hasRole(Role::admin->value))->toBeTrue();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::admin => false]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->hasRole(Role::admin->value))->toBeFalse();

    $Admin = adminUser();

    $this->actingAs($Admin)
        ->from(editUrl($Admin))
        ->post(editUrl($Admin), payload($Admin, [UsersUpdateRequest::admin => false]))
        ->assertRedirect(editUrl($Admin))
        ->assertSessionHasErrors(UsersUpdateRequest::admin);

    expect($Admin->refresh()->hasRole(Role::admin->value))->toBeTrue();
});
