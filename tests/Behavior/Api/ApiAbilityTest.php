<?php

use App\Helpers\HttpVerb;
use App\Models\User;
use App\Modules\Api\Support\AbilityQuery;
use App\Modules\Api\Support\ApiResponse;
use App\Modules\Api\Support\ErrorCode;
use App\Routes\ApiRoute;
use Illuminate\Support\Facades\Auth;

test('a token reaches exactly the verb of the path it was granted, and nothing else', function (): void {
    $User = User::factory()->createOne();

    // An endpoint no token was sent to is not gated by an ability at all.
    $this->assertMatchesSchema($this->getJson(ApiRoute::readme->value))->assertOk();

    Auth::forgetGuards();
    $granted = $User->createToken('test-device', [HttpVerb::get->ability(ApiRoute::user->value)])->plainTextToken;

    $this->assertMatchesSchema($this->withToken($granted)->getJson(ApiRoute::user->value))->assertOk();

    // The same path granted for another verb leaves this one closed.
    Auth::forgetGuards();
    $other = $User->createToken('test-device', [HttpVerb::delete->ability(ApiRoute::user->value)])->plainTextToken;

    $this->assertMatchesSchema($this->withToken($other)->getJson(ApiRoute::user->value))->assertForbidden();

    Auth::forgetGuards();
    $none = $User->createToken('test-device', [])->plainTextToken;

    foreach (AbilityQuery::get() as $path => $verbs) {
        foreach ($verbs as $HttpVerb) {
            $url = (string) preg_replace('/\{[^}]+}/', 'missing', $path);

            $this->assertMatchesSchema($this->withToken($none)->json($HttpVerb->value, $url))
                ->assertForbidden()
                ->assertJsonPath(ApiResponse::message, ErrorCode::missing_ability->value)
                ->assertJsonPath(ApiResponse::type, 'error');
        }
    }
});
