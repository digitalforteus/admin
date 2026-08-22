<?php

use App\Sources\Db\App\Jobs;
use App\Sources\Db\App\Migrations;
use App\Sources\Db\App\PersonalAccessTokens;
use App\Sources\Db\App\Users;
use Tests\Fixtures\UntabledStub;
use ZeroToProd\SchemaValidator\Property;

test('a column becomes an openapi schema object, publishing nothing the vocabulary has no word for', function (): void {
    expect(Users::email->schema())->toBe([
        Property::type => Property::string,
        Property::maxLength => 255,
        Property::description => 'The users email',
    ])
        ->not->toHaveKey('unique')
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
});

// The migrations table is created by the framework rather than by a migration
// of ours, so it is the one table whose columns carry no comment.
test('an attribute is readable by name, an absent one reads as null, and the enum names its table', function (): void {
    expect(Migrations::batch->comment())->toBeNull()
        ->and(Migrations::batch->attribute('nonexistent'))->toBeNull()
        ->and(Users::email->length())->toBe(255)
        ->and(Users::email->unique())->toBeTrue()
        ->and(Users::table())->toBe('users')
        ->and(PersonalAccessTokens::table())->toBe('personal_access_tokens')
        ->and(UntabledStub::table())->toBeEmpty();
});
