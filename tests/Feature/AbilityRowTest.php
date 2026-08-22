<?php

use App\Helpers\HttpVerb;
use App\Routes\ApiRoute;
use App\View\DataModels\AbilityRow;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function abilityRow(array $overrides = []): AbilityRow
{
    return AbilityRow::from([
        AbilityRow::path => ApiRoute::user->value,
        AbilityRow::verbs => [HttpVerb::get, HttpVerb::patch],
        ...$overrides,
    ]);
}

test('a row requires its path and verbs, grants nothing until told, and offers only what is bound', function (): void {
    $AbilityRow = abilityRow();

    expect(static fn () => AbilityRow::from([AbilityRow::path => ApiRoute::user->value]))
        ->toThrow(PropertyRequiredException::class)
        ->and($AbilityRow->granted)->toBeEmpty()
        ->and($AbilityRow->every)->toBeFalse()
        ->and($AbilityRow->ability(HttpVerb::get))->toBe('GET'.HttpVerb::separator.ApiRoute::user->value)
        ->and($AbilityRow->bound(HttpVerb::get))->toBeTrue()
        ->and($AbilityRow->bound(HttpVerb::patch))->toBeTrue()
        ->and($AbilityRow->bound(HttpVerb::delete))->toBeFalse();
});

test('a verb is ticked only for that exact ability, or for a token holding every one', function (): void {
    $Granted = abilityRow([
        AbilityRow::granted => [HttpVerb::get->ability(ApiRoute::user->value)],
    ]);
    $Every = abilityRow([AbilityRow::every => true]);
    $Elsewhere = abilityRow([
        AbilityRow::granted => [HttpVerb::get->ability(ApiRoute::authenticated->value)],
    ]);

    expect($Granted->checked(HttpVerb::get))->toBeTrue()
        ->and($Granted->checked(HttpVerb::patch))->toBeFalse()
        ->and($Every->checked(HttpVerb::get))->toBeTrue()
        ->and($Every->checked(HttpVerb::delete))->toBeTrue()
        ->and($Elsewhere->checked(HttpVerb::get))->toBeFalse();
});
