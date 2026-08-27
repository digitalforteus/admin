<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class OppositeDirection
{
    public function __construct(public SortDirection $direction) {}
}
