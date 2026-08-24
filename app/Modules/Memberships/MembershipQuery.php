<?php

namespace App\Modules\Memberships;

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Sources\Db\App\Memberships;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use App\Sources\Db\App\Users;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

readonly class MembershipQuery
{
    public const string role = 'membership_role';

    public static function grant(Depth $Depth, Model $Model, User $User, MemberRole $MemberRole): void
    {
        Membership::query()->upsert([[
            Memberships::subject_type->value => $Depth->value,
            Memberships::subject_id->value => $Model->getKey(),
            Memberships::user_id->value => $User->id,
            Memberships::role->value => $MemberRole->value,
            Memberships::created_at->value => now(),
            Memberships::updated_at->value => now(),
        ]], [
            Memberships::subject_type->value,
            Memberships::subject_id->value,
            Memberships::user_id->value,
        ], [
            Memberships::role->value,
            Memberships::updated_at->value,
        ]);
    }

    public static function revoke(Depth $Depth, Model $Model, User $User): void
    {
        self::rows($Depth, $Model)->where(Memberships::user_id->value, $User->id)->delete();
    }

    /**
     * A subject reachable at all keeps a member at the top of it.
     *
     * The last standing at the top is what every other standing on the subject was
     * granted by, so removing or lowering it leaves the subject reachable by nobody and
     * changeable by nobody — a state no later write can undo, because the write that
     * would undo it is the one nobody may make.
     */
    public static function change(Depth $Depth, Model $Model, User $User, MemberRole $MemberRole): void
    {
        if ($MemberRole !== MemberRole::owner && self::lastOwner($Depth, $Model, $User)) {
            throw new LastOwnerException($Depth->label().' must keep at least one owner.');
        }

        self::grant($Depth, $Model, $User, $MemberRole);
    }

    public static function remove(Depth $Depth, Model $Model, User $User): void
    {
        if (self::lastOwner($Depth, $Model, $User)) {
            throw new LastOwnerException($Depth->label().' must keep at least one owner.');
        }

        self::revoke($Depth, $Model, $User);
    }

    private static function lastOwner(Depth $Depth, Model $Model, User $User): bool
    {
        return self::held($Depth, $Model, $User) === MemberRole::owner
            && self::owners($Depth, $Model) <= 1;
    }

    public static function purge(Depth $Depth, Model $Model): void
    {
        self::rows($Depth, $Model)->delete();
    }

    /** @param  list<string>  $ids */
    public static function purgeMany(Depth $Depth, array $ids): void
    {
        $Builder = Membership::query();

        $Builder->where(Memberships::subject_type->value, $Depth->value);
        $Builder->whereIn(Memberships::subject_id->value, $ids);
        $Builder->delete();
    }

    public static function held(Depth $Depth, Model $Model, ?User $User): ?MemberRole
    {
        if (! $User instanceof User) {
            return null;
        }

        $role = self::rows($Depth, $Model)
            ->where(Memberships::user_id->value, $User->id)
            ->value(Memberships::role->value);

        return is_string($role) ? MemberRole::tryFrom($role) : null;
    }

    public static function effective(Depth $Depth, Model $Model, ?User $User): ?MemberRole
    {
        if (! $User instanceof User) {
            return null;
        }

        $ancestry = self::ancestry($Depth, $Model);

        if ($ancestry === []) {
            return null;
        }
        $Builder = Membership::query();

        $Builder->where(Memberships::user_id->value, $User->id);
        $Builder->where(static function (Builder $Scoped) use ($ancestry): void {
            foreach ($ancestry as $type => $id) {
                $Scoped->orWhere(static function (Builder $Each) use ($type, $id): void {
                    $Each->where(Memberships::subject_type->value, $type)
                        ->where(Memberships::subject_id->value, $id);
                });
            }
        });

        $rows = $Builder->pluck(Memberships::role->value, Memberships::subject_type->value);

        foreach (array_keys($ancestry) as $type) {
            $role = $rows[$type] ?? null;

            if (is_string($role)) {
                return MemberRole::tryFrom($role);
            }
        }

        return null;
    }

    public static function carried(User $User): ?MemberRole
    {
        $role = $User->getAttribute(self::role);

        return is_string($role) ? MemberRole::tryFrom($role) : null;
    }

    /** @return Collection<int, User> */
    public static function members(Depth $Depth, Model $Model): Collection
    {
        $held = self::rows($Depth, $Model)->pluck(
            Memberships::role->value,
            Memberships::user_id->value,
        )->all();

        $Builder = User::query();

        $Builder->whereIn(Users::id->value, array_keys($held));
        $Builder->orderBy(Users::name->value);

        $Users = $Builder->get();

        foreach ($Users as $User) {
            $role = $held[$User->id] ?? null;

            $User->setAttribute(self::role, is_string($role) ? $role : null);
        }

        return $Users;
    }

    public static function owners(Depth $Depth, Model $Model): int
    {
        return self::rows($Depth, $Model)
            ->where(Memberships::role->value, MemberRole::owner->value)
            ->count();
    }

    /** @param  Builder<covariant Model>  $Builder */
    public static function scope(Depth $Depth, Builder $Builder, User $User): void
    {
        $table = $Builder->getModel()->getTable();

        $Builder->where(static function (Builder $Scoped) use ($Depth, $table, $User): void {
            $Scoped->whereExists(self::at($Depth, $table.'.id', $User));

            if ($Depth === Depth::enterprise) {
                self::beneathEnterprise($Scoped, $table, $User);
            }

            if ($Depth === Depth::organization) {
                self::aroundOrganization($Scoped, $table, $User);
            }

            if ($Depth === Depth::project) {
                self::aboveProject($Scoped, $table, $User);
            }
        });
    }

    /** @param  Builder<covariant Model>  $Builder */
    private static function beneathEnterprise(Builder $Builder, string $table, User $User): void
    {
        $Builder->orWhereExists(static function (QueryBuilder $QueryBuilder) use ($table, $User): void {
            $QueryBuilder->selectRaw('1')
                ->from(Organizations::table())
                ->whereColumn(
                    Organizations::table().'.'.Organizations::enterprise_id->value,
                    $table.'.id',
                )
                ->where(static function (QueryBuilder $Inner) use ($User): void {
                    $Inner->whereExists(self::at(
                        Depth::organization,
                        Organizations::table().'.'.Organizations::id->value,
                        $User,
                    ))->orWhereExists(static function (QueryBuilder $Deeper) use ($User): void {
                        $Deeper->selectRaw('1')
                            ->from(Projects::table())
                            ->whereColumn(
                                Projects::table().'.'.Projects::organization_id->value,
                                Organizations::table().'.'.Organizations::id->value,
                            )
                            ->whereExists(self::at(
                                Depth::project,
                                Projects::table().'.'.Projects::id->value,
                                $User,
                            ));
                    });
                });
        });
    }

    /** @param  Builder<covariant Model>  $Builder */
    private static function aroundOrganization(Builder $Builder, string $table, User $User): void
    {
        $Builder->orWhereExists(self::at(
            Depth::enterprise,
            $table.'.'.Organizations::enterprise_id->value,
            $User,
        ))->orWhereExists(static function (QueryBuilder $QueryBuilder) use ($table, $User): void {
            $QueryBuilder->selectRaw('1')
                ->from(Projects::table())
                ->whereColumn(Projects::table().'.'.Projects::organization_id->value, $table.'.id')
                ->whereExists(self::at(
                    Depth::project,
                    Projects::table().'.'.Projects::id->value,
                    $User,
                ));
        });
    }

    /** @param  Builder<covariant Model>  $Builder */
    private static function aboveProject(Builder $Builder, string $table, User $User): void
    {
        $Builder->orWhereExists(self::at(
            Depth::organization,
            $table.'.'.Projects::organization_id->value,
            $User,
        ))->orWhereExists(static function (QueryBuilder $QueryBuilder) use ($table, $User): void {
            $QueryBuilder->selectRaw('1')
                ->from(Organizations::table())
                ->whereColumn(
                    Organizations::table().'.'.Organizations::id->value,
                    $table.'.'.Projects::organization_id->value,
                )
                ->whereExists(self::at(
                    Depth::enterprise,
                    Organizations::table().'.'.Organizations::enterprise_id->value,
                    $User,
                ));
        });
    }

    private static function at(Depth $Depth, string $column, User $User): Closure
    {
        return static function (QueryBuilder $QueryBuilder) use ($Depth, $column, $User): void {
            $QueryBuilder->selectRaw('1')
                ->from(Memberships::table())
                ->whereColumn(Memberships::table().'.'.Memberships::subject_id->value, $column)
                ->where(Memberships::table().'.'.Memberships::subject_type->value, $Depth->value)
                ->where(Memberships::table().'.'.Memberships::user_id->value, $User->id);
        };
    }

    /** @return array<string, string> */
    private static function ancestry(Depth $Depth, Model $Model): array
    {
        $id = $Model->getKey();

        if (! is_string($id)) {
            return [];
        }

        return match (true) {
            $Model instanceof Project => [
                Depth::project->value => $id,
                Depth::organization->value => $Model->organization_id,
                Depth::enterprise->value => $Model->organization->enterprise_id,
            ],
            $Model instanceof Organization => [
                Depth::organization->value => $id,
                Depth::enterprise->value => $Model->enterprise_id,
            ],
            default => [$Depth->value => $id],
        };
    }

    /** @return Builder<Membership> */
    private static function rows(Depth $Depth, Model $Model): Builder
    {
        $Builder = Membership::query();

        $Builder->where(Memberships::subject_type->value, $Depth->value);
        $Builder->where(Memberships::subject_id->value, $Model->getKey());

        return $Builder;
    }
}
