<?php

namespace App\Modules\Settings\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Sources\Db\App\Organizations;
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

    /** @return array<string, list<Organization>> */
    public static function forUser(User $User): array
    {
        $Builder = self::scoped($User)->with('enterprise');

        $Builder->orderBy(Organizations::name->value);

        $Organizations = $Builder->get();

        $grouped = [];

        foreach ($Organizations as $Organization) {
            $grouped[$Organization->enterprise_id][] = $Organization;
        }

        return $grouped;
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
            static fn (Organization $Organization): array => $Organization->toArray(),
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
