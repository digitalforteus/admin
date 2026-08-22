<?php

namespace App\Modules\Organizations;

use App\Models\Connection;
use App\Models\Organization;
use Illuminate\Http\Request;

readonly class OrganizationContext
{
    private const string organizationKey = 'organization_context.organization';
    private const string connectionKey = 'organization_context.connection';

    public static function bind(Request $Request, Organization $Organization): void
    {
        $Request->attributes->set(self::organizationKey, $Organization);
    }

    public static function bindConnection(Request $Request, Connection $Connection): void
    {
        $Request->attributes->set(self::connectionKey, $Connection);
    }

    public static function organization(): ?Organization
    {
        $Organization = request()->attributes->get(self::organizationKey);

        return $Organization instanceof Organization ? $Organization : null;
    }

    public static function connection(): ?Connection
    {
        $Connection = request()->attributes->get(self::connectionKey);

        return $Connection instanceof Connection ? $Connection : null;
    }

    public static function active(): bool
    {
        return self::organization() instanceof Organization;
    }
}
