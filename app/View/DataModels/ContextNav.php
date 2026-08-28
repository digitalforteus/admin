<?php

namespace App\View\DataModels;

use App\Helpers\Depth;
use App\Helpers\DepthNavExtra;
use App\Helpers\MemberRole;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Project;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Contexts\Context;
use App\Routes\ContextRoute;

readonly class ContextNav implements DescribesNav
{
    public static function visible(): bool
    {
        return Context::deepest() instanceof Depth;
    }

    public static function label(): string
    {
        return Context::deepest()?->label() ?? 'Context';
    }

    /** @return list<NavItem> */
    public static function items(): array
    {
        $Deepest = Context::deepest();

        if (! $Deepest instanceof Depth) {
            return [];
        }

        $Nav = $Deepest->nav();

        /** @var list<NavItem> $items */
        $items = [];

        if ($Nav->overview) {
            $items[] = self::overview($Deepest);
        }

        if ($Nav->extra instanceof DepthNavExtra) {
            $items[] = self::item($Nav->extra->label, $Nav->extra->icon, $Nav->extra->route);
        }

        return [...$items, ...$Nav->trailingIsPlugin ? self::plugin() : self::settings($Deepest)];
    }

    private static function overview(Depth $Depth): NavItem
    {
        return self::item('Overview', SvgName::home, ContextRoute::of($Depth));
    }

    /** @return list<NavItem> */
    private static function settings(Depth $Depth): array
    {
        $Held = Context::role($Depth);
        $Needs = $Depth === Depth::project ? MemberRole::admin : MemberRole::owner;

        if (! $Held instanceof MemberRole || ! $Held->atLeast($Needs)) {
            return [];
        }

        return [self::item('Settings', SvgName::gear, ContextRoute::settings($Depth))];
    }

    /** @return list<NavItem> */
    private static function plugin(): array
    {
        $Project = Context::project();
        $Connection = Context::connection();

        if (! $Project instanceof Project || ! $Connection instanceof Connection) {
            return [];
        }

        return ConnectionProvider::pluginFor($Connection->provider)?->navItems($Project, $Connection) ?? [];
    }

    private static function item(string $label, SvgName $SvgName, ContextRoute $ContextRoute): NavItem
    {
        return NavItem::from([
            NavItem::label => $label,
            NavItem::icon => $SvgName,
            NavItem::route => $ContextRoute,
            NavItem::parameters => ContextRoute::parameters(),
            NavItem::nested => true,
        ]);
    }
}
