<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class RunStatusBadge
{
    public function __construct(public string $badge) {}
}
