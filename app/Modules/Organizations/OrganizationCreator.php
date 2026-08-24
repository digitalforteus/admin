<?php

namespace App\Modules\Organizations;

use App\Helpers\OrganizationRole;
use App\Helpers\Slug;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Sources\Db\App\Organizations;

readonly class OrganizationCreator
{
    public static function create(Enterprise $Enterprise, string $name, User $User): Organization
    {
        return Organization::query()->getConnection()->transaction(
            static function () use ($Enterprise, $name, $User): Organization {
                $Organization = Organization::query()->create([
                    Organizations::enterprise_id->value => $Enterprise->id,
                    Organizations::name->value => $name,
                    Organizations::slug->value => Slug::unique(Organization::class, Organizations::slug->value, $name),
                    Organizations::created_by->value => $User->id,
                ]);

                MembershipQuery::add($Organization, $User, OrganizationRole::owner);

                return $Organization;
            },
        );
    }
}
