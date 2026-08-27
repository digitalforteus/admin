<?php

namespace App\Mcp\Endpoint;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class EndpointType
{
    public function __construct(
        public string $schema,
        public string $placeholder,
        public bool $items = false,
    ) {}

    public function literal(): string
    {
        if ($this->items) {
            return "                Schema::type => Schema::array,\n                Schema::items => [Property::type => Property::string],\n";
        }

        return sprintf("                Property::type => Property::%s,\n", $this->schema);
    }
}
