<?php

namespace App\Modules\Organizations;

use App\Helpers\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Sources\Db\App\OrganizationUser;
use App\Sources\Db\App\Users;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MembershipQuery
{
    /** The role a user holds in an organization, or nothing if they hold none. */
    public static function role(Organization $Organization, ?User $User): ?OrganizationRole
    {
        if (! $User instanceof User) {
            return null;
        }

        $Member = $Organization->users()->whereKey($User->id)->first();

        return $Member instanceof User ? self::held($Member) : null;
    }

    /** @return Collection<int, User> */
    public static function members(Organization $Organization): Collection
    {
        $Relation = $Organization->users();

        $Relation->orderBy(Users::name->value);

        return $Relation->get();
    }

    /** The role carried on a membership row this query already loaded. */
    public static function held(User $User): ?OrganizationRole
    {
        $Pivot = $User->getRelationValue('pivot');

        if (! $Pivot instanceof Pivot) {
            return null;
        }

        $role = $Pivot->getAttribute(OrganizationUser::role->value);

        return is_string($role) ? OrganizationRole::tryFrom($role) : null;
    }

    public static function add(Organization $Organization, User $User, OrganizationRole $OrganizationRole): void
    {
        $Organization->users()->syncWithoutDetaching([
            $User->id => [OrganizationUser::role->value => $OrganizationRole->value],
        ]);
    }

    public static function changeRole(Organization $Organization, User $User, OrganizationRole $OrganizationRole): void
    {
        if ($OrganizationRole !== OrganizationRole::owner && self::lastOwner($Organization, $User)) {
            throw new LastOwnerException('An organization must keep at least one owner.');
        }

        $Organization->users()->updateExistingPivot($User->id, [
            OrganizationUser::role->value => $OrganizationRole->value,
        ]);
    }

    public static function remove(Organization $Organization, User $User): void
    {
        if (self::lastOwner($Organization, $User)) {
            throw new LastOwnerException('An organization must keep at least one owner.');
        }

        $Organization->users()->detach($User->id);
    }

    public static function owners(Organization $Organization): int
    {
        $Relation = $Organization->users();

        $Relation->wherePivot(OrganizationUser::role->value, OrganizationRole::owner->value);

        return $Relation->count();
    }

    private static function lastOwner(Organization $Organization, User $User): bool
    {
        return self::role($Organization, $User) === OrganizationRole::owner
            && self::owners($Organization) <= 1;
    }
}
