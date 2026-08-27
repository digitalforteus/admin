<?php

namespace App\Mcp\Endpoint;

use App\Helpers\HasEnumAttributes;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;

enum EndpointFieldType: string
{
    use HasEnumAttributes;

    #[EndpointType(Property::string, "'example'")]
    case text = 'string';

    #[EndpointType(Property::integer, '1')]
    case integer = 'int';

    #[EndpointType(Property::number, '1.0')]
    case number = 'float';

    #[EndpointType(Property::boolean, 'true')]
    case boolean = 'bool';

    #[EndpointType(Schema::array, '[]', items: true)]
    case items = 'array';

    public static function fromName(string $type): self
    {
        return self::tryFrom($type) ?? self::text;
    }

    public function placeholder(): string
    {
        return $this->type()->placeholder;
    }

    public function literal(): string
    {
        return $this->type()->literal();
    }

    private function type(): EndpointType
    {
        return $this->enumAttribute(EndpointType::class);
    }
}
