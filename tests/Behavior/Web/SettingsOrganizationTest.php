<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\Picture;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use App\Routes\Web;
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
