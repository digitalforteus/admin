<?php

namespace App\View\DataModels;

use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Organization;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\OrganizationRoute;

readonly class OrganizationNav implements DescribesNav
{
    public static function visible(): bool
    {
        return OrganizationContext::active();
    }

    public static function label(): string
    {
        return 'Organization';
    }

    /** @return list<NavItem> */
    public static function items(): array
    {
        $Organization = OrganizationContext::organization();

        if (! $Organization instanceof Organization) {
            return [];
        }

        $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];

        return [
            NavItem::from([
                NavItem::label => 'Overview',
                NavItem::icon => SvgName::home,
                NavItem::route => OrganizationRoute::index,
                NavItem::parameters => $parameters,
            ]),
            NavItem::from([
                NavItem::label => 'Connections',
                NavItem::icon => SvgName::link,
                NavItem::route => OrganizationRoute::connections,
                NavItem::parameters => $parameters,
                NavItem::nested => true,
            ]),
            NavItem::from([
                NavItem::label => 'Members',
                NavItem::icon => SvgName::user,
                NavItem::route => OrganizationRoute::members,
                NavItem::parameters => $parameters,
                NavItem::nested => true,
            ]),
            ...self::plugin($Organization),
        ];
    }

    /** @return list<NavItem> */
    private static function plugin(Organization $Organization): array
    {
        $Connection = OrganizationContext::connection();

        if (! $Connection instanceof Connection) {
            return [];
        }

        return ConnectionProvider::pluginFor($Connection->provider)?->navItems($Organization, $Connection) ?? [];
    }
}
