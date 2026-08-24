<?php

use App\Helpers\OrganizationRole;
use App\Helpers\Slug;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\User;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Connections\Github\GithubForm;
use App\Modules\Connections\Github\GithubQuery;
use App\Modules\Organizations\Connections\ConnectionFields;
use App\Modules\Organizations\Connections\ConnectionForm;
use App\Modules\Organizations\MembershipQuery;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\ProjectConnection;
use App\Sources\Db\App\Projects;
use App\View\DataModels\Breadcrumb;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** The column as the database holds it, before the cast reads it back. */
function storedCredentials(Connection $Connection): string
{
    $stored = Connection::query()->getConnection()->table(Connections::table())
        ->where(Connections::id->value, $Connection->id)
        ->value(Connections::credentials->value);

    return is_string($stored) ? $stored : '';
}

test('a project opts into its enterprise connections, keeps the ones nothing answers for, and drops a caller off one it has switched off', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [Organizations::slug->value => 'acme']);
    $Project = memberProject($Organization, [
        Projects::name->value => 'Website Redesign',
        Projects::slug->value => 'alpha',
    ]);

    $Enabled = projectConnection($Project, attributes: [
        Connections::name->value => 'Primary Repo',
        Connections::slug->value => 'primary-repo',
        Connections::provider->value => ConnectionProvider::github->name,
    ]);
    $Disabled = projectConnection($Project, enabled: false, attributes: [
        Connections::name->value => 'Spare Repo',
        Connections::slug->value => 'spare-repo',
        Connections::provider->value => ConnectionProvider::github->name,
    ]);

    // A key no case answers for is stored, displayed and inert. This is the whole
    // design: dropping a provider costs a case, not a migration.
    $Unavailable = projectConnection($Project, attributes: [
        Connections::name->value => 'Retired Provider',
        Connections::slug->value => 'retired',
        Connections::provider->value => 'stripe',
    ]);

    $parameters = [
        OrganizationRoute::organizationParameter => 'acme',
        OrganizationRoute::projectParameter => 'alpha',
    ];
    $connections = OrganizationRoute::connections->url($parameters);

    expect(ConnectionProvider::tryFromKey('stripe'))->toBeNull()
        ->and(collect(ConnectionQuery::enabledFor($Project))->pluck(Connections::slug->value)->all())
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

    // A depth the address does not reach is left out of the trail rather than
    // emptied, so an organization page stops at the organization.
    $this->actingAs($User)
        ->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'acme']))
        ->assertOk()
        ->assertSee('data-breadcrumb', false);

    $trail = (Breadcrumb::current() ?? throw new RuntimeException('No trail inside an organization.'))->trail();

    expect($trail)->toHaveCount(2)
        ->and($trail[0]->label)->toBe($Organization->enterprise->name)
        ->and($trail[1]->label)->toBe($Organization->name);

    // Naming a project reaches the third depth, and naming one of its connections
    // reaches the fourth: every containment the addresses express, and no more.
    $this->actingAs($User)
        ->get(OrganizationRoute::project->url($parameters))
        ->assertOk();

    $Held = Breadcrumb::current();

    expect($Held)->not->toBeNull()
        ->and($Held->trail())->toHaveCount(3);

    $this->actingAs($User)
        ->get(OrganizationRoute::connection->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'primary-repo',
        ]))
        ->assertOk();

    $Named = Breadcrumb::current();

    expect($Named)->not->toBeNull();

    $trail = $Named->trail();

    expect($trail)->toHaveCount(4)
        ->and($trail[0]->label)->toBe($Organization->enterprise->name)
        ->and($trail[1]->label)->toBe($Organization->name)
        ->and($trail[2]->label)->toBe('Website Redesign')
        ->and($trail[3]->label)->toBe('Primary Repo')
        ->and($trail[3]->entries())->toBeEmpty()
        ->and($trail[3]->settingsUrl)->toBe(OrganizationRoute::connectionManage->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'primary-repo',
        ]))
        ->and($trail[3]->createUrl)->toBe(OrganizationRoute::connectionCreate->url($parameters));

    // Enabling and disabling is the pivot, and it is the caller's standing that
    // decides whether it may be written.
    $this->actingAs($User)
        ->from($connections)
        ->post(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertRedirect($connections)
        ->assertSessionHas('status', 'Connection enabled.');

    $this->assertDatabaseHas(ProjectConnection::table(), [
        ProjectConnection::project_id->value => $Project->id,
        ProjectConnection::connection_id->value => $Disabled->id,
    ]);

    $this->actingAs($User)
        ->from($connections)
        ->delete(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertSessionHas('status', 'Connection disabled.');

    $this->assertDatabaseMissing(ProjectConnection::table(), [
        ProjectConnection::project_id->value => $Project->id,
        ProjectConnection::connection_id->value => $Disabled->id,
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
        ->post(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'spare-repo',
        ]))
        ->assertForbidden();

    // The credentials are the owner's, so the depth that reaches them offers a
    // member neither door: both would answer with a refusal.
    $this->actingAs($Member)
        ->get(OrganizationRoute::connection->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'primary-repo',
        ]))
        ->assertOk();

    $Reading = Breadcrumb::current();

    expect($Reading)->not->toBeNull();

    $held = $Reading->trail();

    expect($held)->toHaveCount(4)
        ->and($held[3]->settingsUrl)->toBeNull()
        ->and($held[3]->createUrl)->toBeNull();

    // Existence is not public here either.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($connections)
        ->assertNotFound();

    // A connection of another enterprise is not this project's to attach, and the
    // pivot cannot say so — only the query can.
    $Foreign = Connection::factory()->createOne([
        Connections::enterprise_id->value => Enterprise::factory(),
        Connections::slug->value => 'foreign',
    ]);

    expect(static fn () => ConnectionQuery::enable($Project, $Foreign))
        ->toThrow(RuntimeException::class)
        ->and(static fn () => ConnectionQuery::disable($Project, $Foreign))
        ->toThrow(RuntimeException::class);

    $this->forgetCredentials()
        ->actingAs($User)
        ->post(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'foreign',
        ]))
        ->assertNotFound();

    // A caller sitting on a connection page when it stops being served is sent to
    // the project, not shown a failure.
    $page = OrganizationRoute::connection->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'primary-repo',
    ]);
    $index = OrganizationRoute::project->url($parameters);

    ConnectionQuery::disable($Project, $Enabled);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    ConnectionQuery::enable($Project, $Enabled);
    $Enabled->update([Connections::provider->value => 'stripe']);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    $this->actingAs($User)
        ->get(OrganizationRoute::connection->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => $Unavailable->slug,
        ]))
        ->assertRedirect($index);
});

test('an owner picks a provider, splits one form into two columns, keeps a blank secret, verifies on demand and deletes the row nothing else may touch', function (): void {
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [Organizations::slug->value => 'globex']);
    $Project = memberProject($Organization, [Projects::slug->value => 'alpha']);
    $parameters = [
        OrganizationRoute::organizationParameter => 'globex',
        OrganizationRoute::projectParameter => 'alpha',
    ];
    $connections = OrganizationRoute::connections->url($parameters);
    $create = OrganizationRoute::connectionCreate->url($parameters);
    $GithubConnection = ConnectionProvider::github->plugin();

    // One stub answers for every address the provider is reached at, and what it
    // answers is a variable, because a second registration would not replace it.
    $status = 200;

    Http::fake([
        'www.gravatar.com/avatar/*' => Http::response('gravatar', 200, ['Content-Type' => 'image/jpeg']),
        GithubQuery::url.'/*' => static function () use (&$status): PromiseInterface {
            return Http::response(['total_count' => 0, 'workflow_runs' => []], $status);
        },
    ]);

    // The allow-list is the plugin's declaration, and no plugin may declare the
    // field the host owns — it would be silently dropped from the split.
    foreach (ConnectionProvider::cases() as $Case) {
        expect(ConnectionFields::declared($Case->plugin()))->not->toContain(ConnectionForm::name);
    }

    expect(ConnectionFields::declared($GithubConnection))
        ->toBe([GithubForm::token, GithubForm::owner, GithubForm::repo]);

    $split = ConnectionFields::split($GithubConnection, [
        GithubForm::token => 'ghp_one',
        GithubForm::owner => 'octocat',
        GithubForm::repo => 'hello-world',
        'unsolicited' => 'value',
    ]);

    expect($split[Connections::credentials->value])->toBe([GithubForm::token => 'ghp_one'])
        ->and($split[Connections::config->value])
        ->toBe([GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world'])
        ->and(ConnectionFields::values($split))->not->toHaveKey('unsolicited');

    // The picker is the registry and nothing else.
    $picker = $this->actingAs($Owner)->get($create)->assertOk()->assertSee('GitHub');

    expect(substr_count((string) $picker->getContent(), 'data-connection-provider'))
        ->toBe(count(ConnectionProvider::cases()));

    // A key no case answers for is an ordinary condition, on the page and on the write.
    $this->actingAs($Owner)
        ->get($create.'?'.http_build_query([Connections::provider->value => 'stripe']))
        ->assertOk()
        ->assertSee('data-connection-provider', false)
        ->assertDontSee('data-connection-create', false);

    $this->actingAs($Owner)
        ->post($connections, [Connections::provider->value => 'stripe'])
        ->assertRedirect($create);

    // The chosen provider's fields are rendered under the host's own.
    $this->actingAs($Owner)
        ->get($create.'?'.http_build_query([Connections::provider->value => ConnectionProvider::github->name]))
        ->assertOk()
        ->assertSee('data-connection-create', false)
        ->assertSee('Connection Name')
        ->assertSee('Access Token')
        ->assertSee('Repository')
        ->assertDontSee('A token is configured');

    // Both validators answer into one bag.
    $this->actingAs($Owner)
        ->from($create)
        ->post($connections, [
            Connections::provider->value => ConnectionProvider::github->name,
            ConnectionForm::name => '',
            GithubForm::token => '',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertRedirect($create)
        ->assertSessionHasErrors([ConnectionForm::name, GithubForm::token]);

    $this->assertDatabaseCount(Connections::table(), 0);

    $manage = OrganizationRoute::connectionManage->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'primary-repo',
    ]);

    $this->actingAs($Owner)
        ->from($create)
        ->post($connections, [
            Connections::provider->value => ConnectionProvider::github->name,
            ConnectionForm::name => 'Primary Repo',
            GithubForm::token => 'ghp_one',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
            'unsolicited' => 'value',
        ])
        ->assertRedirect($manage)
        ->assertSessionHas('status', 'Connection created.');

    $Connection = Connection::query()->where(Connections::slug->value, 'primary-repo')->sole();

    // Two columns, one form: the secret is encrypted and the rest is in the clear,
    // and a key the plugin never declared reached neither.
    expect($Connection->credentials)->toBe([GithubForm::token => 'ghp_one'])
        ->and($Connection->config)->toEqualCanonicalizing([GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world'])
        ->and(storedCredentials($Connection))->not->toContain('ghp_one')
        ->and($Connection->enterprise_id)->toBe($Organization->enterprise_id);

    // Supplying the credentials is what enables them where they were supplied, which
    // is one project and not every project of the enterprise that now holds them.
    $this->assertDatabaseHas(ProjectConnection::table(), [
        ProjectConnection::project_id->value => $Project->id,
        ProjectConnection::connection_id->value => $Connection->id,
    ]);

    // The slug is per enterprise, so the second enterprise may hold the same one,
    // and a second row of this enterprise may not.
    $Second = memberOrganization($Owner, attributes: [Organizations::slug->value => 'initech']);
    memberProject($Second, [Projects::slug->value => 'beta']);

    $this->actingAs($Owner)
        ->post(OrganizationRoute::connections->url([
            OrganizationRoute::organizationParameter => 'initech',
            OrganizationRoute::projectParameter => 'beta',
        ]), [
            Connections::provider->value => ConnectionProvider::github->name,
            ConnectionForm::name => 'Primary Repo',
            GithubForm::token => 'ghp_two',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertSessionHas('status', 'Connection created.');

    expect(Connection::query()->where(Connections::slug->value, 'primary-repo')->count())->toBe(2);

    $this->actingAs($Owner)
        ->post($connections, [
            Connections::provider->value => ConnectionProvider::github->name,
            ConnectionForm::name => 'Primary Repo',
            GithubForm::token => 'ghp_three',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertSessionHas('status', 'Connection created.');

    expect(Connection::query()
        ->where(Connections::enterprise_id->value, $Organization->enterprise_id)
        ->pluck(Connections::slug->value)
        ->all())
        ->toEqualCanonicalizing(['primary-repo', 'primary-repo-2']);

    // The create page owns a segment, so no row may be addressed at it.
    $this->actingAs($Owner)
        ->post($connections, [
            Connections::provider->value => ConnectionProvider::github->name,
            ConnectionForm::name => 'New',
            GithubForm::token => 'ghp_four',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertSessionHas('status', 'Connection created.');

    $Reserved = Connection::query()->where(Connections::name->value, 'New')->sole();

    expect($Reserved->slug)->not->toBe('new')
        ->and(Slug::reserved)->toContain('new');

    $this->actingAs($Owner)->get($create)->assertOk()->assertSee('data-connection-provider', false);
    $this->actingAs($Owner)->get(OrganizationRoute::connectionManage->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => $Reserved->slug,
    ]))->assertOk()->assertSee('New');

    // The stored secret is said to exist and never rendered back.
    $this->actingAs($Owner)
        ->get($manage)
        ->assertOk()
        ->assertSee('Primary Repo')
        ->assertSee('A token is configured')
        ->assertSee('octocat')
        ->assertSee('data-connection-verify', false)
        ->assertSee('data-connection-delete', false)
        ->assertSee('data-connection-project', false)
        ->assertSee($Project->name)
        ->assertDontSee('ghp_one');

    // A blank secret means unchanged, and the slug the row was given stays its own.
    $this->actingAs($Owner)
        ->from($manage)
        ->post($manage, [
            ConnectionForm::name => 'Primary Repository',
            GithubForm::token => '',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertRedirect($manage)
        ->assertSessionHas('status', 'Connection updated.');

    $Connection->refresh();

    expect($Connection->name)->toBe('Primary Repository')
        ->and($Connection->slug)->toBe('primary-repo')
        ->and($Connection->credentials)->toBe([GithubForm::token => 'ghp_one'])
        ->and($Connection->config)->toEqualCanonicalizing([GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world']);

    // A blank field the plugin did not call secret is a value, and is kept as one —
    // only what a plugin calls secret is read as unchanged when it arrives empty.
    expect(ConnectionFields::merge($GithubConnection, $Connection, [
        GithubForm::token => '',
        GithubForm::owner => 'octocat',
        GithubForm::repo => '',
    ]))->toBe([
        Connections::credentials->value => [GithubForm::token => 'ghp_one'],
        Connections::config->value => [GithubForm::owner => 'octocat', GithubForm::repo => ''],
    ]);

    // A filled secret replaces the stored one.
    $this->actingAs($Owner)
        ->from($manage)
        ->post($manage, [
            ConnectionForm::name => 'Primary Repository',
            GithubForm::token => 'ghp_rotated',
            GithubForm::owner => 'octocat',
            GithubForm::repo => 'hello-world',
        ])
        ->assertSessionHas('status', 'Connection updated.');

    $Connection->refresh();

    expect($Connection->credentials)->toBe([GithubForm::token => 'ghp_rotated'])
        ->and(ConnectionFields::merge($GithubConnection, $Connection, [GithubForm::token => ''])[Connections::credentials->value])
        ->toBe([GithubForm::token => 'ghp_rotated']);

    $this->actingAs($Owner)
        ->from($manage)
        ->post($manage, [ConnectionForm::name => ''])
        ->assertSessionHasErrors([ConnectionForm::name, GithubForm::owner]);

    // Rendering a page never asks the provider anything.
    Http::assertNotSent(static fn (Request $Request): bool => str_starts_with($Request->url(), GithubQuery::url));

    // Verification is a press, and its answer is the moment's.
    $verify = OrganizationRoute::connectionVerify->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'primary-repo',
    ]);

    $this->actingAs($Owner)
        ->from($manage)
        ->post($verify)
        ->assertRedirect($manage)
        ->assertSessionHas('status', 'Connection verified.');

    $status = 401;

    $this->actingAs($Owner)
        ->from($manage)
        ->post($verify)
        ->assertSessionHas('status', 'Connection could not be verified.');

    $status = 200;

    // A row nothing answers for is still the owner's to rename and to remove.
    $Retired = projectConnection($Project, enabled: false, attributes: [
        Connections::name->value => 'Retired Provider',
        Connections::slug->value => 'retired',
        Connections::provider->value => 'stripe',
        Connections::credentials->value => [GithubForm::token => 'ghp_retired'],
        Connections::config->value => [GithubForm::owner => 'octocat'],
    ]);
    $retired = OrganizationRoute::connectionManage->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'retired',
    ]);

    $this->actingAs($Owner)
        ->get($retired)
        ->assertOk()
        ->assertSee('Retired Provider')
        ->assertSee('data-connection-unavailable', false)
        ->assertSee('data-connection-projects-empty', false)
        ->assertDontSee('data-connection-verify', false)
        ->assertDontSee('Access Token');

    $this->actingAs($Owner)
        ->from($retired)
        ->post($retired, [ConnectionForm::name => 'Retired Renamed', GithubForm::token => 'ghp_hijack'])
        ->assertSessionHas('status', 'Connection updated.');

    $Retired->refresh();

    expect($Retired->name)->toBe('Retired Renamed')
        ->and($Retired->credentials)->toBe([GithubForm::token => 'ghp_retired'])
        ->and($Retired->config)->toBe([GithubForm::owner => 'octocat']);

    $this->actingAs($Owner)
        ->post(OrganizationRoute::connectionVerify->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'retired',
        ]))
        ->assertNotFound();

    // Holding the credentials is not the same standing as opting into them.
    $Admin = User::factory()->createOne();
    MembershipQuery::add($Organization, $Admin, OrganizationRole::admin);

    $this->forgetCredentials()->actingAs($Admin)->get($create)->assertForbidden();
    $this->actingAs($Admin)->get($manage)->assertForbidden();
    $this->actingAs($Admin)->post($connections, [Connections::provider->value => ConnectionProvider::github->name])->assertForbidden();
    $this->actingAs($Admin)->post($manage, [ConnectionForm::name => 'Theirs'])->assertForbidden();
    $this->actingAs($Admin)->post($verify)->assertForbidden();
    $this->actingAs($Admin)->delete($manage)->assertForbidden();

    $this->actingAs($Admin)
        ->from($connections)
        ->delete(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'primary-repo',
        ]))
        ->assertSessionHas('status', 'Connection disabled.');

    $this->actingAs($Admin)
        ->from($connections)
        ->post(OrganizationRoute::connectionEnabled->url([
            ...$parameters,
            OrganizationRoute::connectionParameter => 'primary-repo',
        ]))
        ->assertSessionHas('status', 'Connection enabled.');

    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()->actingAs($Member)->get($create)->assertForbidden();
    $this->actingAs($Member)->post($connections, [Connections::provider->value => ConnectionProvider::github->name])->assertForbidden();
    $this->actingAs($Member)->post($manage, [ConnectionForm::name => 'Theirs'])->assertForbidden();
    $this->actingAs($Member)->post($verify)->assertForbidden();
    $this->actingAs($Member)->delete($manage)->assertForbidden();

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($connections)
        ->assertOk()
        ->assertDontSee('data-connection-add', false)
        ->assertDontSee('data-connection-manage', false);

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->get($connections)
        ->assertOk()
        ->assertSee('data-connection-add', false)
        ->assertSee('data-connection-manage', false);

    // Deleting is felt off this page: the pivot goes with the row, and a caller
    // sitting on the plugin's page is sent to the project on their next request.
    $page = OrganizationRoute::connection->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'primary-repo',
    ]);

    $this->actingAs($Owner)->get($page)->assertOk();

    $this->actingAs($Owner)
        ->from($manage)
        ->delete($manage)
        ->assertRedirect($connections)
        ->assertSessionHas('status', 'Connection deleted.');

    $this->assertDatabaseMissing(Connections::table(), [Connections::id->value => $Connection->id]);
    $this->assertDatabaseMissing(ProjectConnection::table(), [
        ProjectConnection::connection_id->value => $Connection->id,
    ]);

    $this->actingAs($Owner)->get($page)->assertRedirect(OrganizationRoute::project->url($parameters));

    // The row of the other enterprise is untouched, and is not this one's to reach.
    expect(Connection::query()->where(Connections::enterprise_id->value, $Second->enterprise_id)->count())->toBe(1);

    $this->actingAs($Owner)->get($manage)->assertNotFound();
});
