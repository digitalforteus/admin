<?php

namespace App\Helpers;

use Attribute;
use ZeroToProd\SchemaValidator\Property;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class TypeSchema
{
    public function __construct(
        public string $type,
        public ?string $format = null,
        public mixed $example = '',
    ) {}

    /** @return array<string, string> */
    public function schema(): array
    {
        $schema = [Property::type => $this->type];

        if ($this->format !== null) {
            $schema[Property::format] = $this->format;
        }

        return $schema;
    }
}
