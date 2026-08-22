<?php

namespace App\View\DataModels;

use App\Helpers\HasNavItems;
use App\Helpers\SvgName;
use App\Routes\Web;

enum DocsNav implements DescribesNav
{
    use HasNavItems;

    #[NavItem([NavItem::label => 'API', NavItem::icon => SvgName::document, NavItem::route => Web::docsApi])]
    case api;

    #[NavItem([NavItem::label => 'MCP', NavItem::icon => SvgName::code, NavItem::route => Web::docsMcp])]
    case mcp;

    public static function label(): string
    {
        return 'Documentation';
    }

    public static function visible(): bool
    {
        return Web::docs->isActive(request());
    }
}
