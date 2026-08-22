<?php

namespace App\View\DataModels;

use App\Helpers\HasNavItems;
use App\Helpers\SvgName;
use App\Routes\Auth;
use App\Routes\Web;

enum LeftNav implements DescribesNav
{
    use HasNavItems;

    #[NavItem([NavItem::label => 'Home', NavItem::icon => SvgName::home, NavItem::route => Web::home])]
    case home;

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
