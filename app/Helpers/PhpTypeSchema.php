<?php

namespace App\Helpers;

use ZeroToProd\DbModel\PhpType;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;

enum PhpTypeSchema: string
{
    use HasEnumAttributes;

    #[TypeSchema(Property::string)]
    case text = PhpType::string;

    #[TypeSchema(Property::integer, example: 0)]
    case integer = PhpType::int;

    #[TypeSchema(Property::number, example: 0.0)]
    case number = 'float';

    #[TypeSchema(Property::boolean, example: false)]
    case boolean = 'bool';

    #[TypeSchema(Schema::array, example: [])]
    case items = 'array';

    #[TypeSchema(Property::string, Property::date_time)]
    case date_time = PhpType::DateTimeInterface;

    public static function fromName(string $type): self
    {
        return self::tryFrom($type) ?? self::text;
    }

    public static function fromSchemaType(string $type): self
    {
        foreach (self::cases() as $PhpTypeSchema) {
            if (($PhpTypeSchema->schema()[Property::type] ?? null) === $type) {
                return $PhpTypeSchema;
            }
        }

        return self::text;
    }

    /** @return array<string, string> */
    public function schema(): array
    {
        return $this->typeSchema()->schema();
    }

    public function example(): mixed
    {
        return $this->typeSchema()->example;
    }

    private function typeSchema(): TypeSchema
    {
        return $this->enumAttribute(TypeSchema::class);
    }
}
