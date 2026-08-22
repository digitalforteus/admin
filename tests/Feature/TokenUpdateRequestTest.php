<?php

use App\Helpers\HttpVerb;
use App\Modules\Settings\Credentials\TokenUpdateRequest;
use App\Routes\ApiRoute;

test('only a submitted ability this api answers is kept, and everything else grants nothing', function (): void {
    $ability = HttpVerb::get->ability(ApiRoute::user->value);

    expect(TokenUpdateRequest::from()->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => []])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => [$ability]])->abilities)->toBe([$ability])
        // A wildcard, a path no endpoint is gated by, and a verb the path does
        // not answer are each dropped.
        ->and(TokenUpdateRequest::from([
            TokenUpdateRequest::abilities => [$ability, HttpVerb::every, 'PUT'.HttpVerb::separator.'/api/nowhere'],
        ])->abilities)->toBe([$ability])
        ->and(TokenUpdateRequest::from([
            TokenUpdateRequest::abilities => [HttpVerb::put->ability(ApiRoute::user->value)],
        ])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => 'GET:/api/user'])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => [['nested']]])->abilities)->toBeEmpty();
});
