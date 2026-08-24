<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OrganizationRole;
use App\Helpers\Picture;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\OrganizationUser;
use App\Sources\Db\App\Projects;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function organizationUrl(Organization $Organization): string
{
    return Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]);
}

function organizationIconUrl(Organization $Organization): string
{
    return Auth::settingsOrganizationIcon->url([Auth::organizationParameter => $Organization->id]);
}

test('guests are redirected to login', function (): void {
    $Organization = Organization::factory()->createOne();

    $this->get(organizationUrl($Organization))->assertRedirect(Web::login->value);
    $this->post(organizationUrl($Organization))->assertRedirect(Web::login->value);
});

test('the page renders the organization name and icon control', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: ['name' => 'Acme Inc.']);

    $this->actingAs($User)
        ->get(organizationUrl($Organization))
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertSee('data-organization-form', false)
        ->assertSee('data-picture-field', false)
        ->assertSee(organizationIconUrl($Organization));
});

test('an organization that does not exist is not found', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsOrganization->url([Auth::organizationParameter => 'missing']))
        ->assertNotFound();
});

test('a name is updated, and a stranger cannot see the organization at all', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: ['name' => 'Acme Inc.']);

    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get(organizationUrl($Organization))
        ->assertNotFound();

    $this->forgetCredentials()
        ->actingAs($User)
        ->from(organizationUrl($Organization))
        ->post(organizationUrl($Organization), [OrganizationForm::name => 'Globex Corp.'])
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHas('status', 'Organization updated.');

    expect($Organization->refresh()->name)->toBe('Globex Corp.');
});

test('validation fails with a missing name', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: ['name' => 'Acme Inc.']);

    $this->actingAs($User)
        ->from(organizationUrl($Organization))
        ->post(organizationUrl($Organization))
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHasErrors(OrganizationForm::name);

    expect($Organization->refresh()->name)->toBe('Acme Inc.');
});

test('guests cannot upload or remove an organization icon', function (): void {
    $Organization = Organization::factory()->createOne();

    $this->post(organizationIconUrl($Organization))->assertRedirect(Web::login->value);
    $this->delete(organizationIconUrl($Organization))->assertRedirect(Web::login->value);
});

test('an icon is uploaded', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->from(organizationUrl($Organization))
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('icon.jpg'),
        ])
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHas('status', 'Organization icon updated.');

    $path = $Organization->refresh()->icon;

    expect($path)->toStartWith(Directory::organization_icons->value.'/');
    $Disk->assertExists((string) $path);
});

test('uploading an icon discards the one it replaces', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('first.jpg'),
        ]);

    $first = (string) $Organization->refresh()->icon;

    $this->actingAs($User)
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('second.jpg'),
        ]);

    $second = (string) $Organization->refresh()->icon;

    expect($second)->not->toBe($first);
    $Disk->assertMissing($first);
    $Disk->assertExists($second);
});

test('an icon is removed', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('icon.jpg'),
        ]);

    $path = (string) $Organization->refresh()->icon;

    $this->actingAs($User)
        ->from(organizationUrl($Organization))
        ->delete(organizationIconUrl($Organization))
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHas('status', 'Organization icon removed.');

    expect($Organization->refresh()->icon)->toBeNull();
    $Disk->assertMissing($path);
});

test('a file that is not an image is rejected', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->from(organizationUrl($Organization))
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->create('resume.pdf', 16, 'application/pdf'),
        ])
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHasErrors(OrganizationIconRequest::icon);

    expect($Organization->refresh()->icon)->toBeNull();
});

test('an image larger than the limit is rejected', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->actingAs($User)
        ->from(organizationUrl($Organization))
        ->post(organizationIconUrl($Organization), [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('huge.jpg')->size(Picture::kilobytes + 1),
        ])
        ->assertRedirect(organizationUrl($Organization))
        ->assertSessionHasErrors(OrganizationIconRequest::icon);

    expect($Organization->refresh()->icon)->toBeNull();
});

test('only an owner may open or write to the page, and what deletion takes is shown before it is pressed', function (): void {
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [Organizations::name->value => 'Acme Inc.']);
    $Project = memberProject($Organization, [Projects::name->value => 'Website Redesign']);
    $Connection = projectConnection($Project, attributes: [
        Connections::name->value => 'Primary Repo',
        Connections::slug->value => 'primary-repo',
    ]);

    $Admin = User::factory()->createOne();
    MembershipQuery::add($Organization, $Admin, OrganizationRole::admin);

    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    // The page that offers the deletion is the page that states its cost.
    $this->actingAs($Owner)
        ->get(organizationUrl($Organization))
        ->assertOk()
        ->assertSee('data-organization-delete', false)
        ->assertSee('data-organization-member', false)
        ->assertSee('data-organization-project', false)
        ->assertSee($Owner->name)
        ->assertSee($Admin->name)
        ->assertSee($Member->name)
        ->assertSee('Website Redesign');

    // Holding a membership is not holding the organization: every write is the
    // owner's, and an administrator of it is refused along with a plain member.
    foreach ([$Admin, $Member] as $Other) {
        $this->forgetCredentials()->actingAs($Other);

        $this->get(organizationUrl($Organization))->assertForbidden();
        $this->post(organizationUrl($Organization), [OrganizationForm::name => 'Theirs'])->assertForbidden();
        $this->post(organizationIconUrl($Organization))->assertForbidden();
        $this->delete(organizationIconUrl($Organization))->assertForbidden();
        $this->delete(organizationUrl($Organization))->assertForbidden();
    }

    expect($Organization->refresh()->name)->toBe('Acme Inc.');

    // An organization holding nothing says so rather than nothing.
    ConnectionQuery::disable($Project, $Connection);
    $Project->delete();

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->get(organizationUrl($Organization))
        ->assertOk()
        ->assertSee('data-organization-projects-empty', false);

    $this->actingAs($Owner)
        ->from(organizationUrl($Organization))
        ->delete(organizationUrl($Organization))
        ->assertRedirect(Auth::settingsOrganizations->value)
        ->assertSessionHas('status', 'Organization deleted.');

    $this->assertDatabaseMissing(Organizations::table(), [Organizations::id->value => $Organization->id]);
    $this->assertDatabaseMissing(OrganizationUser::table(), [
        OrganizationUser::organization_id->value => $Organization->id,
    ]);
});
