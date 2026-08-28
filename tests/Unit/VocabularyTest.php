<?php

use App\Helpers\DataModelCast;
use App\Helpers\PhpTypeSchema;
use App\Helpers\Rule;
use App\Modules\Api\Support\ObjectSchema;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;

test('a cast, a rule and a schema object each render one declaration in the vocabulary that reads it', function (): void {
    expect(Rule::max(255))->toBe('max:255')
        ->and(Rule::unique('users'))->toBe('unique:users')
        ->and(Rule::unique('users', 'email'))->toBe('unique:users,email')
        ->and(DataModelCast::sanitize("  a   b \n"))->toBe('a b')
        ->and(DataModelCast::sanitize(null))->toBeEmpty()
        ->and(DataModelCast::sanitizeNullable('  a   b  '))->toBe('a b')
        ->and(DataModelCast::sanitizeNullable('   '))->toBeNull()
        ->and(DataModelCast::sanitizeNullable(null))->toBeNull()
        ->and(DataModelCast::sanitizeEmail('  JOHN@Example.COM '))->toBe('john@example.com')
        ->and(DataModelCast::sanitizeEmail(null))->toBeEmpty()
        ->and(DataModelCast::toIntNullable('5'))->toBe(5)
        ->and(DataModelCast::toIntNullable(null))->toBeNull()
        ->and(DataModelCast::toIntNullable(''))->toBeNull()
        ->and(ObjectSchema::make([
            'a' => ['schema' => [Property::type => Property::string], 'required' => true],
            'b' => ['schema' => [Property::type => Property::integer], 'required' => false],
        ]))->toBe([
            Schema::type => Schema::object,
            Schema::required => ['a'],
            Schema::properties => [
                'a' => [Property::type => Property::string],
                'b' => [Property::type => Property::integer],
            ],
        ])
        ->and(ObjectSchema::make([
            'a' => ['schema' => [Property::type => Property::string], 'required' => false],
        ]))->toBe([
            Schema::type => Schema::object,
            Schema::properties => ['a' => [Property::type => Property::string]],
        ])
        ->and(ObjectSchema::make([]))->toBe([Schema::type => Schema::object])
        ->and(PhpTypeSchema::fromSchemaType('unsupported'))->toBe(PhpTypeSchema::text);
});
