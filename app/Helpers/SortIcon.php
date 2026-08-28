<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class SortIcon
{
    public function __construct(public SvgName $icon) {}
}
