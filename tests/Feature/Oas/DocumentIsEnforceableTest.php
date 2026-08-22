<?php

use App\Modules\Api\Support\ApiResponse;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\OasDocument;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;
use ZeroToProd\SchemaValidator\SchemaValidator;
use ZeroToProd\SchemaValidator\UnsupportedKeyword;

// Layer 1: keyword and format expressibility. `make()` builds the rules eagerly,
// so an empty payload is enough to ask whether the request validator can express
// what the document says, without asserting anything about a value.
test('every body the document publishes is expressible as rules, and the walk reaches all of them', function (): void {
    $unenforceable = [];

    foreach (OasDocument::generated()->bodySchemas() as $operation => $schema) {
        try {
            SchemaValidator::make([], $schema);
        } catch (UnsupportedKeyword $UnsupportedKeyword) {
            $unenforceable[] = $operation.': '.$UnsupportedKeyword->getMessage();
        }
    }

    expect($unenforceable)->toBeEmpty(
        "The document publishes what the request validator cannot express:\n  - ".implode("\n  - ", $unenforceable)
    );

    $operations = array_keys(OasDocument::generated()->bodySchemas());

    expect($operations)->toContain('get /api/user response 200')
        // The error envelopes are published as `$ref`, so reaching these is what
        // says the references were resolved rather than skipped.
        ->toContain('get /api/user response 401')
        ->toContain('get /api/user response 403');

    // Pinned against a synthetic schema so that proving the guard works never
    // means publishing a keyword a real endpoint does not use.
    expect(static fn () => SchemaValidator::make([], [
        Schema::type => Schema::object,
        Schema::properties => ['a' => ['allOf' => [[Property::type => Property::string]]]],
    ]))->toThrow(UnsupportedKeyword::class, 'Unsupported OpenAPI keyword `allOf` at `a`.');
});

// Layer 2: value level agreement. What `date-time` was: league admits RFC 3339, and a
// mapping of `date_format:Y-m-d\TH:i:sP` did not, so the API documented and emitted a
// timestamp its own request validator would have rejected. A space for the separator is
// a value league accepts as a `date-time` to this day.
test('a body the two validators disagree on is reported, and one they agree on is not', function (): void {
    $schema = [
        Schema::type => Schema::object,
        Schema::required => [ApiResponse::data],
        Schema::properties => [
            ApiResponse::data => [Property::type => Property::string, Property::format => Property::date_time],
        ],
    ];

    expect(fn () => $this->assertBodyMatchesRules(
        $schema,
        [ApiResponse::data => '2026-08-10 12:00:00'],
        'GET /synthetic 200',
    ))->toThrow(AssertionFailedError::class, 'refuses it under the request validator');

    // What Model::serializeDate() publishes, which is the value the defect
    // was found on.
    $this->assertBodyMatchesRules(
        $schema,
        [ApiResponse::data => '2026-08-10T12:00:00.000000Z'],
        'GET /synthetic 200',
    );
});
