<?php

namespace App\Helpers;

use App\Routes\ContextRoute;

readonly class DepthNavExtra
{
    public function __construct(
        public string $label,
        public SvgName $icon,
        public ContextRoute $route,
    ) {}
}
