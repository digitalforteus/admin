<?php

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Contexts\DepthQuery;
use App\Modules\Memberships\LastOwnerException;
use App\Modules\Memberships\MembershipQuery;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;

test('a standing reaches every depth beneath the one it is held at, the nearest row wins, and seeing a subject is holding something inside it', function (): void {
    $Enterprise = Enterprise::factory()->createOne([Enterprises::name->value => 'Acme Holdings']);
    $Owner = User::factory()->createOne();

    MembershipQuery::grant(Depth::enterprise, $Enterprise, $Owner, MemberRole::owner);

    $Organization = memberProjectHolder($Enterprise, 'Acme Inc.');
    $Project = memberProject($Organization, [Projects::name->value => 'Website Redesign']);

    // A row at the widest depth answers for everything inside it, so nothing had to be
    // granted twice for the account that made the enterprise to reach its project.
    expect(MembershipQuery::effective(Depth::enterprise, $Enterprise, $Owner))->toBe(MemberRole::owner)
        ->and(MembershipQuery::effective(Depth::organization, $Organization, $Owner))->toBe(MemberRole::owner)
        ->and(MembershipQuery::effective(Depth::project, $Project, $Owner))->toBe(MemberRole::owner)
        ->and(MembershipQuery::held(Depth::organization, $Organization, $Owner))->toBeNull()
        ->and(MembershipQuery::held(Depth::project, $Project, $Owner))->toBeNull();

    // The nearest row wins, so a row closer to the subject narrows what a further one
    // granted rather than being ignored by it.
    $Narrowed = User::factory()->createOne();

    MembershipQuery::grant(Depth::enterprise, $Enterprise, $Narrowed, MemberRole::owner);
    MembershipQuery::grant(Depth::organization, $Organization, $Narrowed, MemberRole::member);

    expect(MembershipQuery::effective(Depth::enterprise, $Enterprise, $Narrowed))->toBe(MemberRole::owner)
        ->and(MembershipQuery::effective(Depth::organization, $Organization, $Narrowed))->toBe(MemberRole::member)
        ->and(MembershipQuery::effective(Depth::project, $Project, $Narrowed))->toBe(MemberRole::member);

    // A standing never reaches upwards: an account admitted to one project sees the
    // enterprise around it, and may change nothing there.
    $Inside = User::factory()->createOne();

    MembershipQuery::grant(Depth::project, $Project, $Inside, MemberRole::owner);

    expect(MembershipQuery::effective(Depth::project, $Project, $Inside))->toBe(MemberRole::owner)
        ->and(MembershipQuery::effective(Depth::organization, $Organization, $Inside))->toBeNull()
        ->and(MembershipQuery::effective(Depth::enterprise, $Enterprise, $Inside))->toBeNull();

    // Seeing a subject is holding something at it or inside it, which is what lets a
    // reader admitted to one project address the enterprise that contains it.
    expect(collect(DepthQuery::children(Depth::enterprise, null, $Inside))->pluck('id')->all())
        ->toBe([$Enterprise->id])
        ->and(collect(DepthQuery::children(Depth::organization, $Enterprise, $Inside))->pluck('id')->all())
        ->toBe([$Organization->id])
        ->and(collect(DepthQuery::children(Depth::project, $Organization, $Inside))->pluck('id')->all())
        ->toBe([$Project->id]);

    // A stranger sees none of it, at any depth.
    $Stranger = User::factory()->createOne();

    expect(DepthQuery::children(Depth::enterprise, null, $Stranger))->toBeEmpty()
        ->and(DepthQuery::children(Depth::organization, $Enterprise, $Stranger))->toBeEmpty()
        ->and(DepthQuery::children(Depth::project, $Organization, $Stranger))->toBeEmpty()
        ->and(DepthQuery::resolve(Depth::enterprise, null, $Enterprise->slug, $Stranger))->toBeNull()
        ->and(DepthQuery::resolve(Depth::enterprise, null, $Enterprise->slug, $Inside)?->getKey())
        ->toBe($Enterprise->id)
        ->and(DepthQuery::resolve(Depth::organization, $Enterprise, 'missing', $Owner))->toBeNull();

    // What a subject lists is its own rows and never the ones it inherits.
    expect(collect(MembershipQuery::members(Depth::enterprise, $Enterprise))->pluck('id')->all())
        ->toEqualCanonicalizing([$Owner->id, $Narrowed->id])
        ->and(collect(MembershipQuery::members(Depth::project, $Project))->pluck('id')->all())
        ->toBe([$Inside->id])
        ->and(MembershipQuery::carried(MembershipQuery::members(Depth::project, $Project)->sole()))
        ->toBe(MemberRole::owner);

    // A subject reachable at all keeps a member at the top of it, and the standing
    // that would leave it unreachable is the one that may not be written.
    expect(MembershipQuery::owners(Depth::project, $Project))->toBe(1)
        ->and(static fn () => MembershipQuery::change(Depth::project, $Project, $Inside, MemberRole::member))
        ->toThrow(LastOwnerException::class)
        ->and(static fn () => MembershipQuery::remove(Depth::project, $Project, $Inside))
        ->toThrow(LastOwnerException::class);

    MembershipQuery::grant(Depth::project, $Project, $Owner, MemberRole::owner);
    MembershipQuery::change(Depth::project, $Project, $Inside, MemberRole::member);

    expect(MembershipQuery::held(Depth::project, $Project, $Inside))->toBe(MemberRole::member);

    MembershipQuery::remove(Depth::project, $Project, $Inside);

    expect(MembershipQuery::held(Depth::project, $Project, $Inside))->toBeNull()
        ->and(MembershipQuery::effective(Depth::project, $Project, $Inside))->toBeNull();

    // Granting twice is one row, and revoking what was never granted is not an error.
    MembershipQuery::grant(Depth::enterprise, $Enterprise, $Owner, MemberRole::admin);

    expect(MembershipQuery::held(Depth::enterprise, $Enterprise, $Owner))->toBe(MemberRole::admin)
        ->and(MembershipQuery::members(Depth::enterprise, $Enterprise))->toHaveCount(2);

    MembershipQuery::revoke(Depth::enterprise, $Enterprise, $Stranger);
    MembershipQuery::purge(Depth::project, $Project);

    expect(MembershipQuery::members(Depth::project, $Project))->toBeEmpty()
        ->and(MembershipQuery::effective(Depth::project, $Project, null))->toBeNull()
        ->and(MembershipQuery::held(Depth::project, $Project, null))->toBeNull();

    MembershipQuery::purgeMany(Depth::organization, [$Organization->id]);

    expect(MembershipQuery::members(Depth::organization, $Organization))->toBeEmpty();

    // A subject with no identity yet has no ancestry, so it grants nothing, and a
    // listing of the depth a credential is reached by is not narrowed by standing.
    expect(MembershipQuery::effective(Depth::enterprise, Enterprise::factory()->makeOne(), $Owner))
        ->toBeNull();

    $Credentials = Connection::query();

    MembershipQuery::scope(Depth::connection, $Credentials, $Owner);

    expect($Credentials->count())->toBe(0)
        ->and(DepthQuery::children(Depth::connection, null, $Owner))->toBeEmpty()
        ->and(DepthQuery::resolve(Depth::connection, null, 'anything', $Owner))->toBeNull()
        ->and(DepthQuery::resolve(Depth::organization, null, 'anything', $Owner))->toBeNull()
        ->and(DepthQuery::children(Depth::organization, null, $Owner))->toBeEmpty();

    // The chain is the declaration: containment order, and nothing holds standing at
    // the depth a credential is reached by.
    expect(array_column(Depth::chain(), 'value'))
        ->toBe(['enterprise', 'organization', 'project', 'connection'])
        ->and(Depth::enterprise->parent())->toBeNull()
        ->and(Depth::project->parent())->toBe(Depth::organization)
        ->and(Depth::project->child())->toBe(Depth::connection)
        ->and(Depth::connection->child())->toBeNull()
        ->and(Depth::project->ancestry())->toBe([Depth::project, Depth::organization, Depth::enterprise])
        ->and(Depth::connection->holdsMembers())->toBeFalse()
        ->and(Depth::project->holdsMembers())->toBeTrue()
        ->and(Depth::of($Project))->toBe(Depth::project)
        ->and(Depth::of(User::factory()->makeOne()))->toBeNull()
        ->and(Depth::organization->plural())->toBe('Organizations')
        ->and(Depth::organization->foreignKey())->toBe('organization_id')
        ->and(Depth::connection->redirectsWhenAbsent())->toBeTrue()
        ->and(Depth::project->redirectsWhenAbsent())->toBeFalse();
});

function memberProjectHolder(Enterprise $Enterprise, string $name): Organization
{
    return Organization::factory()->createOne([
        Organizations::enterprise_id->value => $Enterprise->id,
        Organizations::name->value => $name,
    ]);
}
