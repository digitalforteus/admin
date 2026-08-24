<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\MembershipQuery;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\OrganizationRow;
use Illuminate\Database\Eloquent\Builder;

readonly class OrganizationQuery
{
    public static function find(User $User, string $organization): Organization
    {
        $Organization = self::scoped($User)->whereKey($organization)->first();

        if (! $Organization instanceof Organization) {
            abort(404);
        }

        return $Organization;
    }

    public static function owned(User $User, string $organization): Organization
    {
        $Organization = self::find($User, $organization);

        if (MembershipQuery::role($Organization, $User) !== OrganizationRole::owner) {
            abort(403);
        }

        return $Organization;
    }

    public static function bySlug(User $User, string $slug): Organization
    {
        $Organization = self::scoped($User)
            ->with('enterprise')
            ->where(Organizations::slug->value, $slug)
            ->first();

        if (! $Organization instanceof Organization) {
            abort(404);
        }

        return $Organization;
    }

    /** @return list<array<string, mixed>> */
    public static function get(User $User): array
    {
        $Organizations = self::scoped($User)
            ->latest(Organizations::created_at->value)
            ->latest(Organizations::id->value)
            ->get();

        return array_values(array_map(
            /** @return array<string, mixed> */
            static fn (Organization $Organization): array => [
                ...$Organization->toArray(),
                OrganizationRow::owns => MembershipQuery::role($Organization, $User) === OrganizationRole::owner,
            ],
            $Organizations->all(),
        ));
    }

    /** @return Builder<Organization> */
    private static function scoped(User $User): Builder
    {
        return Organization::query()->whereHas(
            'users',
            static fn (Builder $Builder): Builder => $Builder->whereKey($User->id),
        );
    }
}
