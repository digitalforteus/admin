<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ThemeLabel
{
    public function __construct(public string $label) {}
}
