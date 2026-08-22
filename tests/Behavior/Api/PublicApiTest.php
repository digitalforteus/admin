<?php

use App\Helpers\CacheKey;
use App\Helpers\HttpVerb;
use App\Models\User;
use App\Modules\Api\Public\Authenticated\AuthenticatedResponse;
use App\Modules\Api\Public\Readme\ReadmeResponse;
use App\Modules\Api\Public\User\Show\UserShowResponse;
use App\Modules\Api\Support\AbilityQuery;
use App\Modules\Api\Support\ApiResponse;
use App\Modules\Api\Support\ErrorCode;
use App\Routes\ApiRoute;
use App\Routes\Web;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

test('the public api serves its readme to anyone, and everything else to exactly the token that was granted it', function (): void {
    $readme = (string) file_get_contents(resource_path(CacheKey::api_readme->value));

    $this->assertMatchesSchema($this->getJson(ApiRoute::readme->value))
        ->assertOk()
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::message => class_basename(ReadmeResponse::class),
            ApiResponse::type => class_basename(ReadmeResponse::class),
        ])
        ->assertJsonPath(ApiResponse::data.'.'.ReadmeResponse::content, $readme);

    expect($readme)->toContain(Web::openapi->value);

    $this->forgetCredentials();

    $this->assertMatchesSchema(
        $this->withToken('invalid-token')->getJson(ApiRoute::user->value)
    )->assertStatus(401);

    Auth::forgetGuards();
    $User = User::factory()->createOne();

    $this->assertMatchesSchema(
        $this->withToken($User->createToken('test-device')->plainTextToken)->getJson(ApiRoute::user->value)
    )
        ->assertOk()
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(UserShowResponse::class),
            ApiResponse::data => [
                UserShowResponse::id => $User->id,
                UserShowResponse::name => $User->name,
                UserShowResponse::email => $User->email,
                // The model serializes its dates, so the response carries
                // whatever Model::serializeDate() published.
                UserShowResponse::email_verified_at => $User->toArray()[UserShowResponse::email_verified_at],
                UserShowResponse::created_at => $User->toArray()[UserShowResponse::created_at],
                UserShowResponse::updated_at => $User->toArray()[UserShowResponse::updated_at],
            ],
        ])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    Auth::forgetGuards();
    $Unverified = User::factory()->unverified()->createOne();

    $this->assertMatchesSchema(
        $this->withToken($Unverified->createToken('test-device')->plainTextToken)->getJson(ApiRoute::user->value)
    )->assertOk()
        // Present and null, not absent. `assertJsonPath` reads a missing key as
        // null too, so the structure assertion is the half that means anything.
        ->assertJsonStructure([ApiResponse::data => [UserShowResponse::email_verified_at]])
        ->assertJsonPath('data.email_verified_at', null);

    $this->forgetCredentials();

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

    $this->forgetCredentials();

    $User = User::factory()->createOne();

    // An endpoint no token was sent to is not gated by an ability at all.
    $this->forgetCredentials();

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
