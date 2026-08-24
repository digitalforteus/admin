<?php

namespace App\View\DataModels;

use App\Helpers\HasNavItems;
use App\Helpers\SvgName;
use App\Routes\Auth;
use App\Routes\ContextRoute;
use App\Routes\Web;

enum LeftNav implements DescribesNav
{
    use HasNavItems;

    #[NavItem([NavItem::label => 'Home', NavItem::icon => SvgName::home, NavItem::route => Web::home])]
    case home;

    #[NavItem([NavItem::label => 'Enterprises', NavItem::icon => SvgName::city, NavItem::route => ContextRoute::enterpriseIndex, NavItem::nested => true])]
    case enterprises;

    #[NavItem([NavItem::label => 'Documentation', NavItem::icon => SvgName::document, NavItem::route => Web::docs, NavItem::nested => true])]
    case docs;

    #[NavItem([NavItem::label => 'Contact', NavItem::icon => SvgName::mailbox, NavItem::route => Web::contact])]
    case contact;

    public static function label(): string
    {
        return 'Primary';
    }

    public static function visible(): bool
    {
        return Auth::dashboard->isActive(request());
    }
}
