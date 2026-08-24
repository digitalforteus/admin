<?php

use App\Helpers\Depth;
use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Memberships\MembershipQuery;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Modules\Organizations\Organizations\OrganizationIconRequest;
use App\Routes\ContextRoute;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an organization is addressed inside its enterprise, configured by the standing held there, and takes its projects with it', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [
        Organizations::name->value => 'Acme Inc.',
        Organizations::slug->value => 'acme',
    ]);
    $parameters = atOrganization($Organization);
    $organization = ContextRoute::organization->url($parameters);
    $settings = ContextRoute::organizationSettings->url($parameters);
    $icon = ContextRoute::organizationIcon->url($parameters);

    $this->get($organization)->assertRedirect(Web::login->value);
    $this->post($settings, [OrganizationForm::name => 'Theirs'])->assertRedirect(Web::login->value);
    $this->delete($settings)->assertRedirect(Web::login->value);
    $this->post($icon)->assertRedirect(Web::login->value);

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->get($organization)
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertSee('data-projects-empty', false)
        ->assertSee('data-project-add', false)
        ->assertSee('data-organization-settings', false)
        ->assertSee(ContextRoute::members->url($parameters))
        ->assertSee(ContextRoute::projectCreate->url($parameters));

    // The enterprise is named by the address, so switching organization never crosses
    // one silently: the segment above it says which one is being read.
    expect($organization)->toContain($Organization->enterprise->slug, 'acme');

    // A second enterprise may hold the same segment, and each is addressed on its own.
    $Elsewhere = memberOrganization($Owner, attributes: [
        Organizations::name->value => 'Acme Elsewhere',
        Organizations::slug->value => 'acme',
    ]);

    expect($Elsewhere->enterprise_id)->not->toBe($Organization->enterprise_id);

    $this->actingAs($Owner)
        ->get(ContextRoute::organization->url(atOrganization($Elsewhere)))
        ->assertOk()
        ->assertSee('Acme Elsewhere')
        ->assertDontSee('Acme Inc.');

    // The name and the icon answer to the standing at this depth.
    $this->actingAs($Owner)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-organization-form', false)
        ->assertSee('data-organization-delete', false)
        ->assertSee('data-organization-member', false)
        ->assertSee('data-organization-projects-empty', false)
        ->assertSee($Owner->name);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($settings, [OrganizationForm::name => '  Acme   Incorporated  '])
        ->assertSessionHas('status', 'Organization updated.');

    expect($Organization->refresh()->name)->toBe('Acme Incorporated')
        ->and($Organization->slug)->toBe('acme');

    $this->actingAs($Owner)
        ->from($settings)
        ->post($settings, [OrganizationForm::name => ''])
        ->assertSessionHasErrors(OrganizationForm::name);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [OrganizationIconRequest::icon => UploadedFile::fake()->image('first.jpg')])
        ->assertSessionHas('status', 'Organization icon updated.');

    $first = (string) $Organization->refresh()->icon;
    $Disk->assertExists($first);

    expect($Organization->iconUrl())->toBe(Disk::public->url($first))
        ->and(Picture::of($Organization, Organizations::icon, Directory::organization_icons)->url())
        ->toBe(Disk::public->url($first));

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [OrganizationIconRequest::icon => UploadedFile::fake()->image('second.jpg')]);

    $Disk->assertMissing($first);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [OrganizationIconRequest::icon => UploadedFile::fake()->create('notes.txt', 10)])
        ->assertSessionHasErrors(OrganizationIconRequest::icon);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [
            OrganizationIconRequest::icon => UploadedFile::fake()->image('huge.jpg')->size(Picture::kilobytes + 1),
        ])
        ->assertSessionHasErrors(OrganizationIconRequest::icon);

    $second = (string) $Organization->refresh()->icon;

    $this->actingAs($Owner)
        ->from($settings)
        ->delete($icon)
        ->assertSessionHas('status', 'Organization icon removed.');

    $Disk->assertMissing($second);

    expect($Organization->refresh()->icon)->toBeNull()
        ->and($Organization->iconUrl())->toBeNull();

    // Holding a membership is not holding the organization: an administrator of it is
    // refused the writes along with a plain member.
    $Admin = User::factory()->createOne();
    MembershipQuery::grant(Depth::organization, $Organization, $Admin, MemberRole::admin);

    $Member = User::factory()->createOne();
    MembershipQuery::grant(Depth::organization, $Organization, $Member, MemberRole::member);

    foreach ([$Admin, $Member] as $Other) {
        $this->forgetCredentials()->actingAs($Other);

        $this->get($organization)->assertOk()->assertDontSee('data-organization-settings', false);
        $this->get($settings)->assertForbidden();
        $this->post($settings, [OrganizationForm::name => 'Theirs'])->assertForbidden();
        $this->post($icon)->assertForbidden();
        $this->delete($icon)->assertForbidden();
        $this->delete($settings)->assertForbidden();
    }

    expect($Organization->refresh()->name)->toBe('Acme Incorporated');

    // Existence is not public here either.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($organization)
        ->assertNotFound();

    $this->actingAs($Owner)
        ->get(ContextRoute::organization->url([
            ...atEnterprise($Organization->enterprise),
            ContextRoute::organizationParameter => 'missing',
        ]))
        ->assertNotFound();

    // What deletion takes is shown before it is pressed, and the memberships of what
    // it contains go with it: nothing else would remove them.
    $Project = memberProject($Organization, [Projects::name->value => 'Website Redesign']);
    MembershipQuery::grant(Depth::project, $Project, $Member, MemberRole::owner);

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-organization-project', false)
        ->assertSee('Website Redesign');

    $this->actingAs($Owner)
        ->from($settings)
        ->delete($settings)
        ->assertRedirect(ContextRoute::enterprise->url(atEnterprise($Organization->enterprise)))
        ->assertSessionHas('status', 'Organization deleted.');

    $this->assertDatabaseMissing(Organizations::table(), [Organizations::id->value => $Organization->id]);
    $this->assertDatabaseMissing(Projects::table(), [Projects::id->value => $Project->id]);

    expect(MembershipQuery::held(Depth::project, $Project, $Member))->toBeNull()
        ->and(MembershipQuery::members(Depth::organization, $Organization))->toBeEmpty();

    // Deleting an organization discards the icon it held.
    $Iconed = memberOrganization($Owner, attributes: [
        Organizations::icon->value => Directory::organization_icons->value.'/icon.jpg',
    ]);
    $Disk->put((string) $Iconed->icon, 'contents');

    $this->actingAs($Owner)->delete(ContextRoute::organizationSettings->url(atOrganization($Iconed)));

    $Disk->assertMissing(Directory::organization_icons->value.'/icon.jpg');

    expect(Organization::query()->whereKey($Iconed->id)->exists())->toBeFalse();
});
