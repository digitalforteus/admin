<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OrganizationRole;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\Auth;
use App\Routes\EnterpriseRoute;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to login', function (): void {
    $this->get(Auth::settingsOrganizations->value)
        ->assertRedirect(Web::login->value);
});

test('guests cannot create an organization', function (): void {
    $Enterprise = Enterprise::factory()->createOne();

    $this->post(
        EnterpriseRoute::organizationCreate->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug]),
        [OrganizationForm::name => 'Acme Inc.'],
    )->assertRedirect(Web::login->value);

    $this->assertDatabaseMissing(Organizations::table(), [
        Organizations::name->value => 'Acme Inc.',
    ]);
});

test('the page renders the nav, the empty state and the way to a first enterprise', function (): void {
    // An account holding nothing is offered an enterprise, not an organization: an
    // organization is only ever created inside one, so there is nowhere to put it yet.
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('data-page-header', false)
        ->assertSee(Auth::settingsOrganizations->value)
        ->assertSee('data-organizations-empty', false)
        ->assertSee('data-enterprise-add', false)
        ->assertSee(EnterpriseRoute::create->value)
        ->assertDontSee('data-organization-add', false)
        ->assertDontSee('data-enterprise-link', false)
        ->assertDontSee('data-organization-create', false);

    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $this->forgetCredentials()
        ->actingAs($User)
        ->get(Auth::settingsOrganizations->value)
        ->assertOk()
        ->assertSee('data-enterprise-link', false)
        ->assertSee($Organization->enterprise->name)
        ->assertSee(EnterpriseRoute::index->url([
            EnterpriseRoute::enterpriseParameter => $Organization->enterprise->slug,
        ]));
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
