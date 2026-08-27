<?php

use App\Helpers\PhpTypeSchema;
use App\Models\User;
use App\Modules\Api\Public\Authenticated\AuthenticatedResponse;
use App\Modules\Api\Public\User\Show\UserShowResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Modules\Api\Support\HasResponseSchema;
use App\Modules\Api\Support\PaginationParameters;
use App\Modules\Api\Support\PaginationResponse;
use App\Sources\Db\App\Jobs;
use App\Sources\Db\App\Migrations;
use App\Sources\Db\App\PersonalAccessTokens;
use App\Sources\Db\App\Users;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Fixtures\OasRequestStub;
use Tests\Fixtures\OasResponseStub;
use Tests\Fixtures\UntabledStub;
use Tests\Support\OasDocument;
use ZeroToProd\SchemaValidator\Property;
use ZeroToProd\SchemaValidator\Schema;
use ZeroToProd\SchemaValidator\SchemaValidator;
use ZeroToProd\SchemaValidator\UnsupportedKeyword;

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

// `HasResponseSchema` publishes every declared field as required, so a nullable
// one is `required` plus `nullable: true`: always present, sometimes null.
//
// `DataModel::from()` does not cooperate by default. It reaches a property
// through `isset($context[$key])`, which is false for a key that is absent and
// false for a key that is present and null, so a nullable property is left
// uninitialized either way. `get_object_vars()` then skips it and the field
// never reaches the body. `#[Describe([Describe::nullable => true])]` on the
// class is what turns both cases into an assignment.
//
// Neither validator catches the gap on its own: the response is only wrong on
// the runs where that field happens to be null, so it survives until a test
// produces one. This asks the question of every model directly instead.

/**
 * Every class under `app/Modules/Api` that publishes a response envelope.
 *
 * Found by the trait rather than by a name, so a model that follows the naming
 * convention and a model that does not are both held to this.
 *
 * @return list<class-string>
 */
function responseModels(): array
{
    $base = app_path('Modules/Api');
    $models = [];

    $Directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);

    foreach (new RecursiveIteratorIterator($Directory) as $File) {
        if (! $File instanceof SplFileInfo || $File->getExtension() !== 'php') {
            continue;
        }

        /** @var class-string $class */
        $class = 'App\\Modules\\Api'.str_replace('/', '\\', substr($File->getPathname(), strlen($base), -4));

        if (in_array(HasResponseSchema::class, class_uses_recursive($class), true)) {
            $models[] = $class;
        }
    }

    sort($models);

    return $models;
}

/** A value of the property's own type, so `from()` has something it will accept. */
function placeholderFor(ReflectionProperty $ReflectionProperty): mixed
{
    $Type = $ReflectionProperty->getType();

    return PhpTypeSchema::fromName($Type instanceof ReflectionNamedType ? $Type->getName() : '')->example();
}

// Layer 1: keyword and format expressibility. `make()` builds the rules eagerly,
// so an empty payload is enough to ask whether the request validator can express
// what the document says, without asserting anything about a value.

test('a column, a request, a response and a page each publish an openapi schema the request rules also admit', function (): void {
    expect(Users::email->schema())->toBe([
        Property::type => Property::string,
        Property::maxLength => 255,
        Property::description => 'The users email',
    ])
        ->and(Users::email->schema())->not->toHaveKey('unique')
        ->and(Users::email_verified_at->schema())->toBe([
            Property::type => Property::string,
            Property::format => Property::date_time,
            Property::description => 'When the users email was verified',
            Property::nullable => true,
        ])
        ->and(Jobs::id->schema())->toBe([
            Property::type => Property::integer,
            Property::description => 'The unique identifier of the queued job',
        ])
        ->and(Jobs::id->auto_increment())->toBeTrue();

    // The migrations table is created by the framework rather than by a migration
    // of ours, so it is the one table whose columns carry no comment.
    expect(Migrations::batch->comment())->toBeNull()
        ->and(Migrations::batch->attribute('nonexistent'))->toBeNull()
        ->and(Users::email->length())->toBe(255)
        ->and(Users::email->unique())->toBeTrue()
        ->and(Users::table())->toBe('users')
        ->and(PersonalAccessTokens::table())->toBe('personal_access_tokens')
        ->and(UntabledStub::table())->toBeEmpty();

    $PaginationResponse = PaginationResponse::of(new LengthAwarePaginator([1, 2], 5, 2, 2));

    expect($PaginationResponse->page)->toBe(2)
        ->and($PaginationResponse->per_page)->toBe(2)
        ->and($PaginationResponse->total)->toBe(5)
        ->and($PaginationResponse->last_page)->toBe(3)
        ->and(PaginationResponse::data())->toBe([
            Schema::type => Schema::object,
            Schema::required => [
                PaginationResponse::page,
                PaginationResponse::per_page,
                PaginationResponse::total,
                PaginationResponse::last_page,
            ],
            Schema::properties => [
                PaginationResponse::page => [
                    Property::type => Property::integer,
                    Property::description => 'The page this body carries, counting from 1.',
                ],
                PaginationResponse::per_page => [
                    Property::type => Property::integer,
                    Property::description => 'How many entries a full page carries.',
                ],
                PaginationResponse::total => [
                    Property::type => Property::integer,
                    Property::description => 'How many entries there are across every page.',
                ],
                PaginationResponse::last_page => [
                    Property::type => Property::integer,
                    Property::description => 'The highest page that carries anything. 1 when there is nothing at all.',
                ],
            ],
        ])
        ->and(PaginationParameters::schema())->toBe([
            [
                'name' => PaginationParameters::page,
                'in' => 'query',
                'required' => false,
                'description' => 'The page to return, counting from 1. A page past the last one is empty rather than a 404.',
                'schema' => [
                    Property::type => Property::integer,
                    Property::minimum => 1,
                    Property::default => 1,
                ],
            ],
            [
                'name' => PaginationParameters::per_page,
                'in' => 'query',
                'required' => false,
                'description' => 'How many entries a page carries. Anything above 100 is served as 100.',
                'schema' => [
                    Property::type => Property::integer,
                    Property::minimum => 1,
                    Property::maximum => PaginationParameters::max_per_page,
                    Property::default => PaginationParameters::default_per_page,
                ],
            ],
        ])
        ->and(PaginationParameters::perPage(new Request))->toBe(PaginationParameters::default_per_page)
        ->and(PaginationParameters::perPage(new Request([PaginationParameters::per_page => 5])))->toBe(5)
        ->and(PaginationParameters::perPage(new Request([PaginationParameters::per_page => 1000])))->toBe(PaginationParameters::max_per_page)
        ->and(PaginationParameters::perPage(new Request([PaginationParameters::per_page => 0])))->toBe(1);

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
        ->toThrow(ValidationException::class)
        ->and(OasRequestStub::schema()[Schema::properties] ?? [])->toBe([
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
        ->and(OasRequestStub::schema()[Schema::required] ?? [])->toBe([OasRequestStub::email, OasRequestStub::password]);

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

    $errors = OasRequestStub::validator(oasRequest([
        OasRequestStub::expires_at => now()->subDay()->toIso8601String(),
    ]))->errors();

    expect($errors->keys())->toBe([OasRequestStub::expires_at])
        ->and($errors->first(OasRequestStub::expires_at))
        ->toBe('The expires_at field must be a future date.')
        ->and(OasRequestStub::validator(oasRequest([
            OasRequestStub::expires_at => now()->addDay()->toIso8601String(),
        ]))->passes())->toBeTrue()
        ->and(UserShowResponse::schema())->toBe([
            Schema::type => Schema::object,
            Schema::required => [ApiResponse::success, ApiResponse::message, ApiResponse::data, ApiResponse::type],
            Schema::properties => [
                ApiResponse::success => [Property::type => Property::boolean, Property::enum => [true]],
                ApiResponse::message => [Property::type => Property::string],
                ApiResponse::data => [
                    Schema::type => Schema::object,
                    Schema::required => [
                        UserShowResponse::id,
                        UserShowResponse::name,
                        UserShowResponse::email,
                        UserShowResponse::email_verified_at,
                        UserShowResponse::created_at,
                        UserShowResponse::updated_at,
                    ],
                    Schema::properties => [
                        UserShowResponse::id => [
                            Property::type => Property::string,
                            Property::maxLength => 26,
                            Property::description => 'The unique identifier of the user',
                        ],
                        UserShowResponse::name => [
                            Property::type => Property::string,
                            Property::maxLength => 255,
                            Property::description => 'The users name',
                        ],
                        UserShowResponse::email => [
                            Property::type => Property::string,
                            Property::maxLength => 255,
                            Property::description => 'The users email',
                        ],
                        UserShowResponse::email_verified_at => [
                            Property::type => Property::string,
                            Property::format => Property::date_time,
                            Property::description => 'When the users email was verified',
                            Property::nullable => true,
                        ],
                        UserShowResponse::created_at => [
                            Property::type => Property::string,
                            Property::format => Property::date_time,
                            Property::description => 'When the user was created',
                            Property::nullable => true,
                        ],
                        UserShowResponse::updated_at => [
                            Property::type => Property::string,
                            Property::format => Property::date_time,
                            Property::description => 'When the user was last updated',
                            Property::nullable => true,
                        ],
                    ],
                ],
                ApiResponse::type => [
                    Property::type => Property::string,
                    Property::enum => [class_basename(UserShowResponse::class)],
                ],
            ],
        ]);

    // Api::respond() strips the empty array, so publishing `data` would
    // describe a key the response never carries.
    expect(AuthenticatedResponse::schema())->toBe([
        Schema::type => Schema::object,
        Schema::required => [ApiResponse::success, ApiResponse::message, ApiResponse::type],
        Schema::properties => [
            ApiResponse::success => [Property::type => Property::boolean, Property::enum => [true]],
            ApiResponse::message => [Property::type => Property::string],
            ApiResponse::type => [
                Property::type => Property::string,
                Property::enum => [class_basename(AuthenticatedResponse::class)],
            ],
        ],
    ]);

    // The `type` enum has to stay whatever Api::resolveType() would publish,
    // which is the payload's class basename.
    expect(OasResponseStub::schema())->toBe([
        Schema::type => Schema::object,
        Schema::required => [ApiResponse::success, ApiResponse::message, ApiResponse::data, ApiResponse::type],
        Schema::properties => [
            ApiResponse::success => [Property::type => Property::boolean, Property::enum => [true]],
            ApiResponse::message => [Property::type => Property::string],
            ApiResponse::data => [
                Schema::type => Schema::object,
                // Every field, nullable ones included: the php type decides
                // whether null is allowed, never whether the key is sent.
                Schema::required => ['name', 'count', 'ratio', 'active', 'tags', 'verified_at', 'label', 'empty_schema', 'nickname'],
                Schema::properties => [
                    'name' => [Property::type => Property::string, Property::description => 'The display name'],
                    'count' => [Property::type => Property::integer],
                    'ratio' => [Property::type => Property::number],
                    'active' => [Property::type => Property::boolean],
                    'tags' => [Property::type => Schema::array],
                    // The column's schema, with nullability taken from the
                    // property rather than from the column.
                    'verified_at' => [
                        Property::type => Property::string,
                        Property::format => Property::date_time,
                        Property::description => 'When the users email was verified',
                        Property::nullable => true,
                    ],
                    'label' => [
                        Property::type => Property::string,
                        Property::description => 'The overriding description',
                    ],
                    'empty_schema' => [Property::type => Property::string],
                    // Required like the rest, and nullable, because the model
                    // sends it as null rather than omitting it.
                    'nickname' => [Property::type => Property::string, Property::nullable => true],
                ],
            ],
            ApiResponse::type => [
                Property::type => Property::string,
                Property::enum => [class_basename(OasResponseStub::class)],
            ],
        ],
    ]);

    $offenders = [];

    foreach (responseModels() as $class) {
        $Properties = new ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC);

        $nullable = array_values(array_filter(
            $Properties,
            static fn (ReflectionProperty $Property): bool => $Property->getType()?->allowsNull() ?? false,
        ));

        if ($nullable === []) {
            continue;
        }

        // Only the fields a real payload always carries. The nullable ones are
        // left out on purpose: absent is the case that has to become null.
        $payload = [];

        foreach ($Properties as $Property) {
            if (! ($Property->getType()?->allowsNull() ?? false)) {
                $payload[$Property->getName()] = placeholderFor($Property);
            }
        }

        $initialized = get_object_vars($class::from($payload));

        foreach ($nullable as $Property) {
            if (! array_key_exists($Property->getName(), $initialized)) {
                $offenders[] = $class.'::$'.$Property->getName();
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "Declared nullable, so the schema publishes the field as required and nullable, but left\n".
        "uninitialized, so the body omits it. Add #[Describe([Describe::nullable => true])] to the class:\n  - ".
        implode("\n  - ", $offenders)
    );

    // The walk has to reach the models, rather than pass over an empty list.

    expect(responseModels())
        ->toContain(UserShowResponse::class)
        ->toContain(PaginationResponse::class)
        // One with no properties at all, which the walk still has to see.
        ->toContain(AuthenticatedResponse::class);

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

    // Layer 2: value level agreement. What `date-time` was: league admits RFC 3339, and a
    // mapping of `date_format:Y-m-d\TH:i:sP` did not, so the API documented and emitted a
    // timestamp its own request validator would have rejected. A space for the separator is
    // a value league accepts as a `date-time` to this day.
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
