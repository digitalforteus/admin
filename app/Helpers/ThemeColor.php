<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ThemeColor
{
    public function __construct(public string $color) {}
}
