<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class BrandUrl
{
    public function __construct(
        public string $path = '',
        public ?string $config = null,
        public string $scheme = '',
        public bool $attribution = false,
    ) {}
}
