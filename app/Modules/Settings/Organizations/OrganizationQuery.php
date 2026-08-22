<?php

namespace App\Modules\Settings\Organizations;

use App\Models\Organization;
use App\Sources\Db\App\Organizations;

class OrganizationQuery
{
    /** One organization, or nothing at all. */
    public static function find(string $organization): Organization
    {
        $Organization = Organization::query()->whereKey($organization)->first();

        if (! $Organization instanceof Organization) {
            abort(404);
        }

        return $Organization;
    }

    /** @return list<array<string, mixed>> */
    public static function get(): array
    {
        $Organizations = Organization::query()
            ->latest(Organizations::created_at->value)
            ->latest(Organizations::id->value)
            ->get();

        return array_values(array_map(
            /** @return array<string, mixed> */
            static fn (Organization $Organization): array => $Organization->toArray(),
            $Organizations->all(),
        ));
    }
}
