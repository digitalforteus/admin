<?php

namespace App\Modules\Organizations;

use App\Helpers\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

readonly class Authorize
{
    public static function member(): Organization
    {
        $Organization = OrganizationContext::organization();

        if (! $Organization instanceof Organization) {
            abort(404);
        }

        return $Organization;
    }

    public static function manages(Request $Request): Organization
    {
        return self::atLeast($Request, OrganizationRole::admin);
    }

    public static function owns(Request $Request): Organization
    {
        return self::atLeast($Request, OrganizationRole::owner);
    }

    public static function atLeast(Request $Request, OrganizationRole $OrganizationRole): Organization
    {
        $Organization = self::member();
        $Held = MembershipQuery::role($Organization, User::authenticated($Request));

        if ($Held === null || ! $Held->atLeast($OrganizationRole)) {
            abort(403);
        }

        return $Organization;
    }
}
