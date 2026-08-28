<?php

namespace App\Routes;

use App\Helpers\Depth;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ContextRouteFor
{
    public function __construct(
        public Depth $depth,
        public ContextRouteRole $role,
    ) {}
}
