<?php

use App\Models\User;
use App\Modules\Api\Public\Authenticated\AuthenticatedResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\ApiRoute;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

test('no token, an invalid one, or an expired one is refused', function (): void {
    $this->getJson(ApiRoute::authenticated->value)
        ->assertStatus(401)
        ->assertJson([
            ApiResponse::success => false,
            ApiResponse::message => 'unauthorized',
            ApiResponse::type => 'error',
        ]);

    $this->assertMatchesSchema(
        $this->withToken('invalid-token')->getJson(ApiRoute::authenticated->value)
    )->assertStatus(401);

    $User = User::factory()->createOne();
    $token = $User->createToken('test-token');
    $token->accessToken->forceFill(['expires_at' => now()->subDay()])->save();

    $this->withToken($token->plainTextToken)
        ->getJson(ApiRoute::authenticated->value)
        ->assertStatus(401);
});

test('every live token of a user reaches the endpoint, which answers in the envelope', function (): void {
    $User = User::factory()->createOne();

    foreach (['device-1', 'device-2'] as $device) {
        Auth::forgetGuards();
        $this->withToken($User->createToken($device)->plainTextToken)
            ->getJson(ApiRoute::authenticated->value)
            ->assertOk();
    }

    Auth::forgetGuards();
    Sanctum::actingAs($User);

    $this->assertMatchesSchema(
        $this->withToken('any-value')->getJson(ApiRoute::authenticated->value)
    )
        ->assertOk()
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::message => class_basename(AuthenticatedResponse::class),
            ApiResponse::type => class_basename(AuthenticatedResponse::class),
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'type',
        ]);
});
