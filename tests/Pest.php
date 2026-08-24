<?php

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Helpers\Role;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Memberships\MembershipQuery;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\Projects;
use Laravel\Sanctum\NewAccessToken;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Behavior', 'Feature');
pest()->tia()->locally();

function adminUser(): User
{
    $User = User::factory()->createOne();
    $User->assignRole(Role::admin->value);

    return $User;
}

function issuedToken(User $User, NewAccessToken $NewAccessToken): PersonalAccessToken
{
    return $User->tokens()->whereKey($NewAccessToken->accessToken->getKey())->sole();
}

/** @param  array<string, mixed>  $attributes */
function memberEnterprise(User $User, MemberRole $MemberRole = MemberRole::owner, array $attributes = []): Enterprise
{
    $Enterprise = Enterprise::factory()->createOne($attributes);

    MembershipQuery::grant(Depth::enterprise, $Enterprise, $User, $MemberRole);

    return $Enterprise;
}

/** @param  array<string, mixed>  $attributes */
function memberOrganization(
    User $User,
    MemberRole $MemberRole = MemberRole::owner,
    array $attributes = [],
): Organization {
    $Organization = Organization::factory()->createOne($attributes);

    MembershipQuery::grant(Depth::organization, $Organization, $User, $MemberRole);

    return $Organization;
}

/** @param  array<string, mixed>  $attributes */
function memberProject(Organization $Organization, array $attributes = []): Project
{
    return Project::factory()->createOne([
        Projects::organization_id->value => $Organization->id,
        ...$attributes,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function projectConnection(Project $Project, bool $enabled = true, array $attributes = []): Connection
{
    $Connection = Connection::factory()->createOne([
        Connections::enterprise_id->value => $Project->organization->enterprise_id,
        ...$attributes,
    ]);

    if ($enabled) {
        ConnectionQuery::enable($Project, $Connection);
    }

    return $Connection;
}

/** @return array<string, string> The placeholders every path inside this enterprise needs. */
function atEnterprise(Enterprise $Enterprise): array
{
    return [ContextRoute::enterpriseParameter => $Enterprise->slug];
}

/** @return array<string, string> The placeholders every path inside this organization needs. */
function atOrganization(Organization $Organization): array
{
    return [
        ...atEnterprise($Organization->enterprise),
        ContextRoute::organizationParameter => $Organization->slug,
    ];
}

/** @return array<string, string> The placeholders every path inside this project needs. */
function atProject(Project $Project): array
{
    return [
        ...atOrganization($Project->organization),
        ContextRoute::projectParameter => $Project->slug,
    ];
}
