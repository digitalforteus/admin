<?php

namespace App\Modules\Connections;

use App\Models\Connection;
use App\Models\Organization;
use App\Models\Project;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\ProjectConnection;
use App\Sources\Db\App\Projects;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * The credentials an enterprise holds, and which of its projects opted into them.
 *
 * Two scopes meet here and neither may be mistaken for the other: a connection
 * belongs to the enterprise, so the same credential is held once and listed for
 * every project beneath it, while opting in belongs to one project, so enabling it
 * in one grants nothing anywhere else. Attaching across enterprises is refused here
 * rather than left to the pivot, which has no column that could express the rule and
 * would record the row happily. A provider this application no longer serves is
 * filtered out of what is offered rather than removed, because the row is a
 * credential somebody stored and losing it silently is worse than hiding it.
 */
readonly class ConnectionQuery
{
    /** @return list<Connection> */
    public static function enabledFor(Project $Project): array
    {
        return array_values(array_filter(
            self::enabled($Project)->all(),
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

    /** @return Collection<int, Project> */
    public static function projects(Connection $Connection): Collection
    {
        $Relation = $Connection->projects();

        $Relation->whereNotNull(ProjectConnection::table().'.'.ProjectConnection::enabled_at->value);
        $Relation->orderBy(Projects::name->value);

        return $Relation->get();
    }

    public static function bySlug(Project $Project, string $slug): ?Connection
    {
        foreach (self::enabledFor($Project) as $Connection) {
            if ($Connection->slug === $slug) {
                return $Connection;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function enabledIds(Project $Project): array
    {
        return array_values(array_map(
            static fn (Connection $Connection): string => $Connection->id,
            self::enabled($Project)->all(),
        ));
    }

    public static function enable(Project $Project, Connection $Connection): void
    {
        self::sameEnterprise($Project, $Connection);

        $Project->connections()->syncWithoutDetaching([
            $Connection->id => [ProjectConnection::enabled_at->value => now()],
        ]);
    }

    public static function disable(Project $Project, Connection $Connection): void
    {
        self::sameEnterprise($Project, $Connection);

        $Project->connections()->detach($Connection->id);
    }

    private static function sameEnterprise(Project $Project, Connection $Connection): void
    {
        if ($Connection->enterprise_id !== $Project->organization->enterprise_id) {
            throw new RuntimeException('A connection may only be attached to a project of its own enterprise.');
        }
    }

    /** @return Collection<int, Connection> */
    public static function enabled(Project $Project): Collection
    {
        $Relation = $Project->connections();

        $Relation->whereNotNull(ProjectConnection::table().'.'.ProjectConnection::enabled_at->value);
        $Relation->orderBy(Connections::name->value);

        return $Relation->get();
    }
}
