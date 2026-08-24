<?php

use App\Helpers\OrganizationRole;
use App\Helpers\Role;
use App\Models\Connection;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\MembershipQuery;
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
function memberOrganization(
    User $User,
    OrganizationRole $OrganizationRole = OrganizationRole::owner,
    array $attributes = [],
): Organization {
    $Organization = Organization::factory()->createOne($attributes);

    MembershipQuery::add($Organization, $User, $OrganizationRole);

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
