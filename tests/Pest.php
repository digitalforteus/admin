<?php

use App\Helpers\OrganizationRole;
use App\Helpers\Role;
use App\Models\Connection;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Sources\Db\App\Connections;
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
function organizationConnection(Organization $Organization, bool $enabled = true, array $attributes = []): Connection
{
    $Connection = Connection::factory()->createOne([
        Connections::enterprise_id->value => $Organization->enterprise_id,
        ...$attributes,
    ]);

    if ($enabled) {
        ConnectionQuery::enable($Organization, $Connection);
    }

    return $Connection;
}
