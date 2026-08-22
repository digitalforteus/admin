<?php

namespace App\View\DataModels;

use App\Helpers\HasNavItems;
use App\Helpers\SvgName;
use App\Routes\Auth;

enum SettingsNav implements DescribesNav
{
    use HasNavItems;

    #[NavItem([NavItem::label => 'Profile', NavItem::icon => SvgName::user, NavItem::route => Auth::settingsProfile])]
    case profile;

    #[NavItem([NavItem::label => 'Appearance', NavItem::icon => SvgName::swatch, NavItem::route => Auth::settingsAppearance])]
    case appearance;

    #[NavItem([NavItem::label => 'Security', NavItem::icon => SvgName::key, NavItem::route => Auth::settingsSecurity])]
    case security;

    #[NavItem([NavItem::label => 'Credentials', NavItem::icon => SvgName::command_line, NavItem::route => Auth::settingsCredentials, NavItem::nested => true])]
    case credentials;

    #[NavItem([NavItem::label => 'Sessions', NavItem::icon => SvgName::desktop, NavItem::route => Auth::settingsSessions, NavItem::nested => true])]
    case sessions;

    public static function label(): string
    {
        return 'Settings';
    }

    public static function visible(): bool
    {
        return Auth::settings->isActive(request());
    }
}
