<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class DepthNav
{
    public function __construct(
        public bool $overview,
        public ?DepthNavExtra $extra = null,
        public bool $trailingIsPlugin = false,
    ) {}
}
