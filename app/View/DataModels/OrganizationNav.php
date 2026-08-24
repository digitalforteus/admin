<?php

namespace App\View\DataModels;

use App\Helpers\OrganizationRole;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Organizations\MembershipQuery;
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
                NavItem::label => 'Projects',
                NavItem::icon => SvgName::folder,
                NavItem::route => OrganizationRoute::projects,
                NavItem::parameters => $parameters,
                NavItem::nested => true,
            ]),
            ...self::inProject($Organization),
            NavItem::from([
                NavItem::label => 'Members',
                NavItem::icon => SvgName::user,
                NavItem::route => OrganizationRoute::members,
                NavItem::parameters => $parameters,
                NavItem::nested => true,
            ]),
            ...self::owned($Organization),
            ...self::plugin(),
        ];
    }

    /** @return list<NavItem> */
    private static function owned(Organization $Organization): array
    {
        $User = request()->user();

        if (! $User instanceof User || MembershipQuery::role($Organization, $User) !== OrganizationRole::owner) {
            return [];
        }

        return [
            NavItem::from([
                NavItem::label => 'Settings',
                NavItem::icon => SvgName::gear,
                NavItem::route => OrganizationRoute::settings,
                NavItem::parameters => [OrganizationRoute::organizationParameter => $Organization->slug],
            ]),
        ];
    }

    /**
     * The rail a project adds while the address is inside one.
     *
     * A depth that is not being visited contributes nothing, so the rail is the
     * depths the address actually reached and never a fixed list: an entry offered
     * for a depth with no context resolved would have no url to point at.
     *
     * @return list<NavItem>
     */
    private static function inProject(Organization $Organization): array
    {
        $Project = OrganizationContext::project();

        if (! $Project instanceof Project) {
            return [];
        }

        return [
            NavItem::from([
                NavItem::label => 'Connections',
                NavItem::icon => SvgName::link,
                NavItem::route => OrganizationRoute::connections,
                NavItem::parameters => [
                    OrganizationRoute::organizationParameter => $Organization->slug,
                    OrganizationRoute::projectParameter => $Project->slug,
                ],
                NavItem::nested => true,
            ]),
        ];
    }

    /** @return list<NavItem> */
    private static function plugin(): array
    {
        $Project = OrganizationContext::project();
        $Connection = OrganizationContext::connection();

        if (! $Project instanceof Project || ! $Connection instanceof Connection) {
            return [];
        }

        return ConnectionProvider::pluginFor($Connection->provider)?->navItems($Project, $Connection) ?? [];
    }
}
