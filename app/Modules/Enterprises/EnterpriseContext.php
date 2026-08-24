<?php

namespace App\Modules\Enterprises;

use App\Models\Enterprise;
use App\Models\Organization;
use App\Modules\Organizations\OrganizationContext;
use Illuminate\Http\Request;

readonly class EnterpriseContext
{
    private const string key = 'enterprise_context.enterprise';

    public static function bind(Request $Request, Enterprise $Enterprise): void
    {
        $Request->attributes->set(self::key, $Enterprise);
    }

    public static function enterprise(): ?Enterprise
    {
        $Enterprise = request()->attributes->get(self::key);

        if ($Enterprise instanceof Enterprise) {
            return $Enterprise;
        }

        $Organization = OrganizationContext::organization();

        return $Organization instanceof Organization ? $Organization->enterprise : null;
    }

    public static function active(): bool
    {
        return self::enterprise() instanceof Enterprise;
    }
}
