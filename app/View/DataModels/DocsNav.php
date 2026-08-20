<?php

namespace App\View\DataModels;

use App\Helpers\SvgName;
use App\Routes\Web;
use LogicException;
use ReflectionEnumUnitCase;

enum DocsNav
{
    #[NavItem([NavItem::label => 'API', NavItem::icon => SvgName::document, NavItem::route => Web::docsApi])]
    case api;

    #[NavItem([NavItem::label => 'MCP', NavItem::icon => SvgName::code, NavItem::route => Web::docsMcp])]
    case mcp;

    /** @return list<NavItem> */
    public static function items(): array
    {
        return array_map(
            static fn (self $DocsNav): NavItem => $DocsNav->item(),
            self::cases(),
        );
    }

    public function item(): NavItem
    {
        $attributes = new ReflectionEnumUnitCase(self::class, $this->name)->getAttributes(NavItem::class);
        $arguments = $attributes[0]->getArguments();

        return new NavItem(self::attributes($arguments[0] ?? null));
    }

    /** @return array<string, mixed> */
    private static function attributes(mixed $item): array
    {
        if (! is_array($item)) {
            throw new LogicException('Documentation navigation cases must describe a navigation item.');
        }

        $attributes = [];

        foreach ($item as $key => $value) {
            if (! is_string($key)) {
                throw new LogicException('Documentation navigation attributes must be named.');
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    public static function visible(): bool
    {
        $path = request()->getPathInfo();

        return str_starts_with($path, '/docs');
    }
}
