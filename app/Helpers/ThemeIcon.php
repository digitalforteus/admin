<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ThemeIcon
{
    public function __construct(public SvgName $icon) {}
}
