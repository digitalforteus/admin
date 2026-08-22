<?php

namespace App\Modules\Connections;

use App\Models\Connection;
use App\Models\Organization;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\OrganizationConnection;
use App\Sources\Db\App\Organizations;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

readonly class ConnectionQuery
{
    /** @return list<Connection> */
    public static function enabledFor(Organization $Organization): array
    {
        return array_values(array_filter(
            self::enabled($Organization)->all(),
            static fn (Connection $Connection): bool => ConnectionProvider::tryFromKey($Connection->provider) !== null,
        ));
    }

    /** @return Collection<int, Connection> */
    public static function ownedBy(Organization $Organization): Collection
    {
        $Builder = Connection::query()
            ->where(Connections::enterprise_id->value, $Organization->enterprise_id);

        $Builder->orderBy(Connections::name->value);

        return $Builder->get();
    }

    public static function find(Organization $Organization, string $slug): Connection
    {
        $Connection = Connection::query()
            ->where(Connections::enterprise_id->value, $Organization->enterprise_id)
            ->where(Connections::slug->value, $slug)
            ->first();

        if (! $Connection instanceof Connection) {
            abort(404);
        }

        return $Connection;
    }

    /** @return Collection<int, Organization> */
    public static function organizations(Connection $Connection): Collection
    {
        $Relation = $Connection->organizations();

        $Relation->whereNotNull(OrganizationConnection::table().'.'.OrganizationConnection::enabled_at->value);
        $Relation->orderBy(Organizations::name->value);

        return $Relation->get();
    }

    public static function bySlug(Organization $Organization, string $slug): ?Connection
    {
        foreach (self::enabledFor($Organization) as $Connection) {
            if ($Connection->slug === $slug) {
                return $Connection;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function enabledIds(Organization $Organization): array
    {
        return array_values(array_map(
            static fn (Connection $Connection): string => $Connection->id,
            self::enabled($Organization)->all(),
        ));
    }

    public static function enable(Organization $Organization, Connection $Connection): void
    {
        self::sameEnterprise($Organization, $Connection);

        $Organization->connections()->syncWithoutDetaching([
            $Connection->id => [OrganizationConnection::enabled_at->value => now()],
        ]);
    }

    public static function disable(Organization $Organization, Connection $Connection): void
    {
        self::sameEnterprise($Organization, $Connection);

        $Organization->connections()->detach($Connection->id);
    }

    private static function sameEnterprise(Organization $Organization, Connection $Connection): void
    {
        if ($Connection->enterprise_id !== $Organization->enterprise_id) {
            throw new RuntimeException('A connection may only be attached to an organization of its own enterprise.');
        }
    }

    /** @return Collection<int, Connection> */
    private static function enabled(Organization $Organization): Collection
    {
        $Relation = $Organization->connections();

        $Relation->whereNotNull(OrganizationConnection::table().'.'.OrganizationConnection::enabled_at->value);
        $Relation->orderBy(Connections::name->value);

        return $Relation->get();
    }
}
