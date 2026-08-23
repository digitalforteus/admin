<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OrganizationRole;
use App\Helpers\Slug;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to login', function (): void {
    $this->get(Auth::settingsOrganizations->value)
        ->assertRedirect(Web::login->value);
});

test('guests cannot create an organization', function (): void {
    $this->post(Auth::settingsOrganizations->value, [OrganizationForm::name => 'Acme Inc.'])
        ->assertRedirect(Web::login->value);

    $this->assertDatabaseMissing(Organizations::table(), [
        Organizations::name->value => 'Acme Inc.',
    ]);
});

test('the page renders the nav, the empty state and the way to a new organization', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee(Auth::settingsOrganizations->value)
        ->assertSee('data-organizations-empty', false)
        ->assertSee('data-organization-add', false)
        ->assertSee(Auth::settingsOrganizationCreate->value);
});

test('the create page carries the form the list no longer holds', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsOrganizationCreate->value)
        ->assertOk()
        ->assertSee('data-organization-create', false)
        ->assertSee('Organization Name');

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertDontSee('data-organization-create', false);
});

test('only an owner is offered the way in to manage one', function (): void {
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [Organizations::name->value => 'Acme Inc.']);

    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::admin);

    $this->actingAs($Owner)
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('data-organization-manage', false);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertDontSee('data-organization-manage', false);
});

test('the page lists only the organizations the caller is a member of', function (): void {
    $User = User::factory()->createOne();

    memberOrganization($User, attributes: [Organizations::name->value => 'Acme Inc.']);
    memberOrganization($User, attributes: [Organizations::name->value => 'Globex Corp.']);
    Organization::factory()->createOne([Organizations::name->value => 'Initech LLC']);

    $this->actingAs($User)
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertSee('Globex Corp.')
        ->assertDontSee('Initech LLC');
});

test('creating an organization makes the creator its owner', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => 'Acme Inc.'])
        ->assertSessionHas('status', 'Organization created.');

    $Organization = Organization::query()->sole();

    // Supplying the name lands on the row it named, not back on an empty form.
    $this->assertDatabaseCount(Organizations::table(), 1);

    expect($Organization->name)->toBe('Acme Inc.')
        ->and($Organization->slug)->toBe('acme-inc')
        ->and($Organization->created_by)->toBe($User->id)
        ->and($Organization->creator?->id)->toBe($User->id)
        ->and(MembershipQuery::role($Organization, $User))->toBe(OrganizationRole::owner)
        ->and($Organization->enterprise->name)->toBe('Acme Inc.');

    // The segment is unique, so a second organization of the same name gets a
    // different one rather than failing the write, and a name that reduces to
    // nothing still gets one.
    $this->actingAs($User)
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => 'Acme Inc.'])
        ->assertSessionHas('status', 'Organization created.');

    $this->actingAs($User)
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => '???'])
        ->assertSessionHas('status', 'Organization created.');

    expect(Organization::query()->pluck(Organizations::slug->value)->all())
        ->toEqualCanonicalizing(['acme-inc', 'acme-inc-2', Slug::fallback]);
});

test('a name is squished before it is stored', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => '  Acme   Inc.  ']);

    expect(Organization::query()->sole()->name)->toBe('Acme Inc.');
});

test('validation fails with a missing name', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value)
        ->assertRedirect(Auth::settingsOrganizationCreate->value)
        ->assertSessionHasErrors(OrganizationForm::name);

    expect(Organization::query()->count())->toBe(0);
});

test('validation errors are displayed on the form', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->from(Auth::settingsOrganizationCreate->value)
        ->followingRedirects()
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => ''])
        ->assertOk()
        ->assertSee('The name field is required.');
});

test('old input is preserved on validation failure', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->from(Auth::settingsOrganizationCreate->value)
        ->post(Auth::settingsOrganizations->value, [OrganizationForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(OrganizationForm::name)
        ->assertSessionHasInput(OrganizationForm::name, str_repeat('a', 256));
});

test('an organization is deleted', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->from(Auth::settingsOrganizations->value)
        ->delete(Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]))
        ->assertRedirect(Auth::settingsOrganizations->value)
        ->assertSessionHas('status', 'Organization deleted.');

    $this->assertDatabaseMissing(Organizations::table(), [Organizations::id->value => $Organization->id]);
});

test('deleting an organization discards its icon', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [
        Organizations::icon->value => Directory::organization_icons->value.'/icon.jpg',
    ]);
    $Disk->put((string) $Organization->icon, 'contents');

    $this->actingAs($User)
        ->delete(Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]));

    $Disk->assertMissing(Directory::organization_icons->value.'/icon.jpg');
});

test('an organization that does not exist is not found', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->delete(Auth::settingsOrganization->url([Auth::organizationParameter => 'missing']))
        ->assertNotFound();
});

test('guests cannot delete an organization', function (): void {
    $Organization = Organization::factory()->createOne();

    $this->delete(Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]))
        ->assertRedirect(Web::login->value);

    $this->assertDatabaseHas(Organizations::table(), [Organizations::id->value => $Organization->id]);
});
