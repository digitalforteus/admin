<?php

use App\Helpers\HttpVerb;
use App\Modules\Api\Support\AbilityQuery;
use App\Modules\Settings\Credentials\TokenUpdateRequest;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use App\View\DataModels\AbilityRow;
use App\View\DataModels\AbilityTable;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function abilityTable(array $overrides = []): AbilityTable
{
    return AbilityTable::from([
        AbilityTable::id => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        AbilityTable::name => 'Laptop CLI',
        ...$overrides,
    ]);
}

test('the table requires the token it manages, and reads it as granting nothing until told otherwise', function (): void {
    expect(static fn () => AbilityTable::from([AbilityTable::name => 'Laptop CLI']))
        ->toThrow(PropertyRequiredException::class)
        ->and(abilityTable()->granted)->toBeEmpty()
        ->and(abilityTable()->every())->toBeFalse()
        ->and(abilityTable([AbilityTable::granted => [HttpVerb::every]])->every())->toBeTrue()
        ->and(abilityTable()->verbs())->toBe(HttpVerb::cases());
});

test('the rows are the token-guarded paths, each holding what the token holds', function (): void {
    $paths = array_map(static fn (AbilityRow $Row): string => $Row->path, abilityTable()->rows());

    expect($paths)->toBe(array_keys(AbilityQuery::get()))
        ->and($paths)->toContain(ApiRoute::user->value)
        // A path reached without a token is never offered.
        ->and($paths)->not->toContain(ApiRoute::readme->value)
        ->and($paths)->not->toContain(ApiRoute::authenticated->value);

    $granted = [HttpVerb::get->ability(ApiRoute::user->value)];

    foreach (abilityTable([AbilityTable::granted => $granted])->rows() as $Row) {
        expect($Row->granted)->toBe($granted)
            ->and($Row->every)->toBeFalse();
    }

    foreach (abilityTable([AbilityTable::granted => [HttpVerb::every]])->rows() as $Row) {
        expect($Row->every)->toBeTrue();
    }

    $this->actingAs(adminUser());
    $groups = abilityTable()->groups();

    expect(array_keys($groups))->toBe(['public', 'admin'])
        ->and($groups['public'])->not->toBeEmpty()
        ->and($groups['admin'])->not->toBeEmpty();
});

test('the form posts back to the token it manages, naming the key the request reads', function (): void {
    expect(abilityTable()->action())
        ->toBe(Auth::settingsCredential->url([Auth::credentialParameter => abilityTable()->id]))
        ->and(abilityTable()->mcpConnection('public'))->toBe([
            'base_url' => url('/'),
            'openapi_url' => url('openapi.json'),
            'headers' => 'Authorization:Bearer <token>',
            'llms_url' => url(Web::llms->value),
        ])
        ->and(AbilityTable::field)->toBe(TokenUpdateRequest::abilities.'[]');
});
