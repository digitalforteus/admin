<?php

namespace App\Modules\Contexts;

use App\Helpers\Depth;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Memberships\MembershipQuery;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

readonly class DepthQuery
{
    /** @return list<Model> What the reader may reach at one depth, inside the subject containing it. */
    public static function children(Depth $Depth, ?Model $Parent, User $User): array
    {
        if ($Depth === Depth::connection) {
            if (! $Parent instanceof Project) {
                return [];
            }

            return ConnectionQuery::enabledFor($Parent);
        }

        $Builder = self::within($Depth, $Parent);

        if (! $Builder instanceof Builder) {
            return [];
        }

        MembershipQuery::scope($Depth, $Builder, $User);

        $Builder->orderBy(self::name($Depth));

        return array_values($Builder->get()->all());
    }

    public static function resolve(Depth $Depth, ?Model $Parent, string $slug, User $User): ?Model
    {
        if ($Depth === Depth::connection) {
            if (! $Parent instanceof Project) {
                return null;
            }

            return ConnectionQuery::bySlug($Parent, $slug);
        }

        $Builder = self::within($Depth, $Parent);

        if (! $Builder instanceof Builder) {
            return null;
        }

        MembershipQuery::scope($Depth, $Builder, $User);

        $Model = $Builder->where(self::slug($Depth), $slug)->first();

        return $Model instanceof Model ? $Model : null;
    }

    /** @return Builder<covariant Model>|null */
    private static function within(Depth $Depth, ?Model $Parent): ?Builder
    {
        $Parents = $Depth->parent();

        if (! $Parents instanceof Depth) {
            return Enterprise::query();
        }

        if (! $Parent instanceof Model) {
            return null;
        }

        if ($Depth === Depth::organization) {
            return Organization::query()->where(Organizations::enterprise_id->value, $Parent->getKey());
        }

        return Project::query()->where(Projects::organization_id->value, $Parent->getKey());
    }

    private static function slug(Depth $Depth): string
    {
        if ($Depth === Depth::enterprise) {
            return Enterprises::slug->value;
        }

        if ($Depth === Depth::organization) {
            return Organizations::slug->value;
        }

        return Projects::slug->value;
    }

    private static function name(Depth $Depth): string
    {
        if ($Depth === Depth::enterprise) {
            return Enterprises::name->value;
        }

        if ($Depth === Depth::organization) {
            return Organizations::name->value;
        }

        return Projects::name->value;
    }
}
