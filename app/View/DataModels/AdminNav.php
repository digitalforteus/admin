<?php

namespace App\View\DataModels;

use App\Helpers\HasNavItems;
use App\Helpers\SvgName;
use App\Routes\Admin;

enum AdminNav implements DescribesNav
{
    use HasNavItems;

    #[NavItem([NavItem::label => 'Dashboard', NavItem::icon => SvgName::home, NavItem::route => Admin::index])]
    case dashboard;

    #[NavItem([NavItem::label => 'Users', NavItem::icon => SvgName::user, NavItem::route => Admin::users])]
    case users;

    #[NavItem([NavItem::label => 'Sessions', NavItem::icon => SvgName::desktop, NavItem::route => Admin::sessions])]
    case sessions;

    #[NavItem([NavItem::label => 'Content', NavItem::icon => SvgName::document, NavItem::route => Admin::content])]
    case content;

    #[NavItem([NavItem::label => 'Links', NavItem::icon => SvgName::document, NavItem::route => Admin::links])]
    case links;

    public static function label(): string
    {
        return 'Admin';
    }

    public static function visible(): bool
    {
        return Admin::index->isActive(request());
    }
}
