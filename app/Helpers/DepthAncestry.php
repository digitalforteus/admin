<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class DepthAncestry
{
    /** @param  array<string, string>  $paths Ancestor depth value => dot path to its id on a model at this depth. */
    public function __construct(public array $paths = []) {}
}
