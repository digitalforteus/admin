<?php

use App\Modules\Api\Support\ObjectSchema;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;

test('required flags are hoisted onto the parent, an empty list omitted, and a bare object is just a type', function (): void {
    expect(ObjectSchema::make([
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
        ->and(ObjectSchema::make([]))->toBe([Schema::type => Schema::object]);
});
