<?php

namespace App\Helpers;

use App\View\DataModels\NavItem;
use ReflectionEnumUnitCase;

trait HasNavItems
{
    /** @return list<NavItem> */
    public static function items(): array
    {
        $items = [];

        foreach (self::cases() as $Case) {
            $items[] = $Case->item();
        }

        return $items;
    }

    public function item(): NavItem
    {
        $attributes = new ReflectionEnumUnitCase(self::class, $this->name)->getAttributes(NavItem::class);

        return $attributes[0]->newInstance();
    }
}
