<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\OasRequestStub;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;

/**
 * A payload the declared schema accepts, so a single field carries the failure.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oasRequest(array $overrides = []): array
{
    return [
        OasRequestStub::email => 'free@example.com',
        OasRequestStub::password => 'secret',
        OasRequestStub::password_confirmation => 'secret',
        OasRequestStub::nickname => 'nick',
        OasRequestStub::broken => 'x',
        ...$overrides,
    ];
}

test('a payload the document refuses is a failure rather than a hydration error or a coercion', function (): void {
    // Validating the raw input keeps hydration off any payload the schema
    // rejects, so an array cannot reach a property typed as a string.
    $array = OasRequestStub::validator(oasRequest([OasRequestStub::email => ['x']]))->errors();

    expect($array->keys())->toBe([OasRequestStub::email])
        ->and($array->first(OasRequestStub::email))->toBe('The email field must be a string.');

    // The cast would have made this "123" and let it pass a `type: string`
    // schema, leaving the runtime laxer than the published document.
    expect(OasRequestStub::validator(oasRequest([OasRequestStub::email => 123]))->errors()->keys())
        ->toBe([OasRequestStub::email]);

    // A required, non-nullable string translates to `required`, which rejects
    // "" without the document having to publish minLength: 1. That keeps the
    // 422 reachable by a request the document accepts.
    $blank = OasRequestStub::validator(oasRequest([OasRequestStub::email => '']))->errors();

    expect($blank->keys())->toBe([OasRequestStub::email])
        ->and($blank->first(OasRequestStub::email))->toBe('The email field is required.')
        ->and(static fn () => OasRequestStub::validator(oasRequest([OasRequestStub::email => 123]))->validate())
        ->toThrow(ValidationException::class);
});

test('a closure description overrides the fragment, a non array schema is dropped, and only the flagged are hoisted', function (): void {
    expect(OasRequestStub::schema()[Schema::properties] ?? [])->toBe([
        OasRequestStub::email => [Property::type => Property::string, Property::minLength => 1],
        OasRequestStub::password => [Property::type => Property::string],
        OasRequestStub::nickname => [
            Property::type => Property::string,
            Property::description => 'The users email',
        ],
        OasRequestStub::expires_at => [
            Property::type => Property::string,
            Property::format => Property::date_time,
        ],
        OasRequestStub::broken => [],
    ])
        ->and(OasRequestStub::schema()[Schema::required] ?? [])
        ->toBe([OasRequestStub::email, OasRequestStub::password]);
});

test('value checks run once the schema passes, and are skipped when it already failed', function (): void {
    User::factory()->createOne(['email' => 'taken@example.com']);

    $errors = OasRequestStub::validator(oasRequest([
        OasRequestStub::email => 'taken@example.com',
        OasRequestStub::password_confirmation => 'mismatch',
    ]))->errors();

    expect($errors->keys())->toBe([OasRequestStub::email, OasRequestStub::password])
        ->and($errors->first(OasRequestStub::email))->toBe('That email is already taken.')
        ->and($errors->first(OasRequestStub::password))->toBe('The confirmation does not match.');

    $skipped = OasRequestStub::validator(oasRequest([OasRequestStub::email => '']))->errors();

    expect($skipped->keys())->toBe([OasRequestStub::email])
        ->and($skipped->first(OasRequestStub::email))->toBe('The email field is required.')
        // A passing value check adds nothing.
        ->and(OasRequestStub::validator(oasRequest())->passes())->toBeTrue();
});

test('an instant that has already passed is refused, and one still ahead of now is accepted', function (): void {
    $errors = OasRequestStub::validator(oasRequest([
        OasRequestStub::expires_at => now()->subDay()->toIso8601String(),
    ]))->errors();

    expect($errors->keys())->toBe([OasRequestStub::expires_at])
        ->and($errors->first(OasRequestStub::expires_at))
        ->toBe('The expires_at field must be a future date.')
        ->and(OasRequestStub::validator(oasRequest([
            OasRequestStub::expires_at => now()->addDay()->toIso8601String(),
        ]))->passes())->toBeTrue();
});
