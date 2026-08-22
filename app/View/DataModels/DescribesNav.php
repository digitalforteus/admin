<?php

namespace App\View\DataModels;

interface DescribesNav
{
    /** @return list<NavItem> */
    public static function items(): array;

    public static function visible(): bool;

    public static function label(): string;
}
