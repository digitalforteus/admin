<?php

namespace App\Mcp\Endpoint;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class RoutePrefix
{
    public function __construct(public string $prefix) {}
}
