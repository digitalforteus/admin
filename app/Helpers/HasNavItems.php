<?php

namespace App\Helpers;

use App\View\DataModels\NavItem;

trait HasNavItems
{
    use HasEnumAttributes;

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
        return $this->enumAttribute(NavItem::class);
    }
}
