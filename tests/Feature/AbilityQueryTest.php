<?php

use App\Helpers\HttpVerb;
use App\Modules\Api\Support\AbilityQuery;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\Fixtures\NumericApiRoute;

test('an ability is one verb and one path, however the method was sent or the path was spelled', function (): void {
    expect(HttpVerb::of(Request::create(ApiRoute::user->value, 'HEAD')))->toBe(HttpVerb::get)
        ->and(HttpVerb::of(Request::create(ApiRoute::user->value, 'PATCH')))->toBe(HttpVerb::patch)
        ->and(HttpVerb::delete->ability(ApiRoute::user->value))
        ->toBe('DELETE'.HttpVerb::separator.ApiRoute::user->value)
        ->toBe(HttpVerb::delete->ability(ltrim(ApiRoute::user->value, '/')));
});

test('the paths offered are the declared ones a token is asked for, with the verbs bound to them', function (): void {
    foreach (array_keys(AbilityQuery::get()) as $path) {
        expect(ApiRoute::tryFrom($path))->not->toBeNull();
    }

    expect(array_keys(AbilityQuery::get()))
        ->not->toContain(ApiRoute::readme->value, ApiRoute::authenticated->value)
        ->and(AbilityQuery::get()[ApiRoute::user->value])->toBe([HttpVerb::get]);

    $expected = [];

    foreach (AbilityQuery::get() as $path => $verbs) {
        foreach ($verbs as $Verb) {
            $expected[] = $Verb->ability($path);
        }
    }

    $abilities = AbilityQuery::abilities();
    $get = AbilityQuery::getAbilities();

    expect($abilities)->toBe($expected)
        ->and($abilities)->toContain(HttpVerb::get->ability(ApiRoute::user->value))
        ->and($get)->not->toBeEmpty()
        ->and(array_filter($get, static fn (string $ability): bool => str_starts_with($ability, HttpVerb::get->value.HttpVerb::separator)))->toBe($get);
});

test('admin abilities reach an administrator only, and an index that is not one publishes nothing', function (): void {
    expect(array_keys(AbilityQuery::groups()))->toBe(['public']);

    $this->actingAs(adminUser());

    expect(array_keys(AbilityQuery::groups()))->toBe(['public', 'admin'])
        ->and(AbilityQuery::groups()['public'])->toHaveKey(ApiRoute::user->value)
        ->and(AbilityQuery::groups()['admin'])->toHaveKey(Admin::api_users->value)
        ->and(AbilityQuery::abilities())->toContain(HttpVerb::get->ability(Admin::api_users->value));

    Config::set('openapi.schemas', [
        'invalid' => ['route_index' => 'NotAnEnum'],
        'numeric' => ['route_index' => NumericApiRoute::class],
    ]);

    expect(AbilityQuery::groups())->toBe([
        'numeric' => [],
    ]);
});
