<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ThemeDescription
{
    public function __construct(public string $description) {}
}
