<?php

namespace App\Modules\Contexts;

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

readonly class Authorize
{
    public static function subject(Depth $Depth): Model
    {
        $Model = Context::of($Depth);

        if (! $Model instanceof Model) {
            abort(404);
        }

        return $Model;
    }

    public static function atLeast(Depth $Depth, MemberRole $MemberRole): Model
    {
        $Model = self::subject($Depth);
        $Held = Context::role($Depth);

        if (! $Held instanceof MemberRole || ! $Held->atLeast($MemberRole)) {
            abort(403);
        }

        return $Model;
    }

    public static function enterprise(MemberRole $MemberRole = MemberRole::member): Enterprise
    {
        $Model = self::atLeast(Depth::enterprise, $MemberRole);

        return $Model instanceof Enterprise ? $Model : abort(404);
    }

    public static function organization(MemberRole $MemberRole = MemberRole::member): Organization
    {
        $Model = self::atLeast(Depth::organization, $MemberRole);

        return $Model instanceof Organization ? $Model : abort(404);
    }

    public static function project(MemberRole $MemberRole = MemberRole::member): Project
    {
        $Model = self::atLeast(Depth::project, $MemberRole);

        return $Model instanceof Project ? $Model : abort(404);
    }
}
