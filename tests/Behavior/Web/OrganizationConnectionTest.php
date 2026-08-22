<?php

use App\Helpers\OrganizationRole;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\User;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\OrganizationConnection;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\ConnectionBreadcrumb;

test('an organization opts into its enterprise connections, keeps the ones nothing answers for, and drops a caller off one it has switched off', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [Organizations::slug->value => 'acme']);

    $Enabled = organizationConnection($Organization, attributes: [
        Connections::name->value => 'Primary Repo',
        Connections::slug->value => 'primary-repo',
        Connections::provider->value => ConnectionProvider::github->name,
    ]);
    $Disabled = organizationConnection($Organization, enabled: false, attributes: [
        Connections::name->value => 'Spare Repo',
        Connections::slug->value => 'spare-repo',
        Connections::provider->value => ConnectionProvider::github->name,
    ]);

    // A key no case answers for is stored, displayed and inert. This is the whole
    // design: dropping a provider costs a case, not a migration.
    $Unavailable = organizationConnection($Organization, attributes: [
        Connections::name->value => 'Retired Provider',
        Connections::slug->value => 'retired',
        Connections::provider->value => 'stripe',
    ]);

    $parameters = [OrganizationRoute::organizationParameter => 'acme'];
    $connections = OrganizationRoute::connections->url($parameters);

    expect(ConnectionProvider::tryFromKey('stripe'))->toBeNull()
        ->and(collect(ConnectionQuery::enabledFor($Organization))->pluck(Connections::slug->value)->all())
        ->toBe(['primary-repo']);

    $this->actingAs($User)
        ->get($connections)
        ->assertOk()
        ->assertSee('Primary Repo')
        ->assertSee('Spare Repo')
        ->assertSee('Retired Provider')
        ->assertSee('data-connection-unavailable', false)
        ->assertSee('GitHub')
        ->assertSee('Enabled')
        ->assertSee('Disabled')
        ->assertSee('Unavailable');

    // On an organization page the trail is the organization alone, and the only
    // destinations it would offer are the ones enabled and still answerable.
    $this->actingAs($User)
        ->get(OrganizationRoute::index->url($parameters))
        ->assertOk()
        ->assertSee('1 enabled')
        ->assertSee('data-connection-breadcrumb', false)
        ->assertDontSee('data-connection-switcher', false);

    $Breadcrumb = ConnectionBreadcrumb::current() ?? throw new RuntimeException('No trail inside an organization.');

    expect($Breadcrumb->active)->toBeNull()
        ->and($Breadcrumb->organization)->toBe($Organization->name)
        ->and(collect($Breadcrumb->items())->pluck('label')->all())->toBe(['Primary Repo']);

    // Enabling and disabling is the pivot, and it is the caller's standing that
    // decides whether it may be written.
    $this->actingAs($User)
        ->from($connections)
        ->post(OrganizationRoute::connectionToggle->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertRedirect($connections)
        ->assertSessionHas('status', 'Connection enabled.');

    $this->assertDatabaseHas(OrganizationConnection::table(), [
        OrganizationConnection::organization_id->value => $Organization->id,
        OrganizationConnection::connection_id->value => $Disabled->id,
    ]);

    $this->actingAs($User)
        ->from($connections)
        ->post(OrganizationRoute::connectionToggle->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertSessionHas('status', 'Connection disabled.');

    $this->assertDatabaseMissing(OrganizationConnection::table(), [
        OrganizationConnection::organization_id->value => $Organization->id,
        OrganizationConnection::connection_id->value => $Disabled->id,
    ]);

    // A plain member reads the page and is refused the write.
    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($connections)
        ->assertOk()
        ->assertDontSee('data-connection-toggle', false);

    $this->actingAs($Member)
        ->post(OrganizationRoute::connectionToggle->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertForbidden();

    // Existence is not public here either.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($connections)
        ->assertNotFound();

    // A connection of another enterprise is not this organization's to attach,
    // and the pivot cannot say so — only the query can.
    $Foreign = Connection::factory()->createOne([
        Connections::enterprise_id->value => Enterprise::factory(),
        Connections::slug->value => 'foreign',
    ]);

    expect(static fn () => ConnectionQuery::enable($Organization, $Foreign))
        ->toThrow(RuntimeException::class)
        ->and(static fn () => ConnectionQuery::disable($Organization, $Foreign))
        ->toThrow(RuntimeException::class);

    $this->forgetCredentials()
        ->actingAs($User)
        ->post(OrganizationRoute::connectionToggle->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'foreign',
        ]))
        ->assertNotFound();

    // A caller sitting on a connection page when it stops being served is sent to
    // the organization, not shown a failure.
    $page = OrganizationRoute::connection->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'primary-repo',
    ]);
    $index = OrganizationRoute::index->url($parameters);

    ConnectionQuery::disable($Organization, $Enabled);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    ConnectionQuery::enable($Organization, $Enabled);
    $Enabled->update([Connections::provider->value => 'stripe']);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    $this->actingAs($User)
        ->get(OrganizationRoute::connection->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => $Unavailable->slug,
        ]))
        ->assertRedirect($index);
});
