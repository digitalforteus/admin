<?php

use App\Models\User;
use App\Modules\Api\Support\AdminApiSchema;
use App\Modules\Api\Support\SchemaController;
use App\Modules\Api\Support\SchemaGenerator;
use App\Routes\ApiRoute;
use App\Routes\Web;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\OpenApi\AdminSchemaController;

test('each document is served where its enum case says, describes the routes it can resolve, and is refused when unconfigured', function (): void {
    expect(Web::openapi->value)->toBe('/'.ltrim(Config::string('openapi.schemas.public.route.uri'), '/'));

    $paths = $this->getJson(Config::string('openapi.schemas.public.route.uri'))
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonStructure(['info', 'paths'])
        ->json('paths');

    foreach (ApiRoute::cases() as $ApiRoute) {
        expect($paths)->toHaveKey($ApiRoute->value);
    }

    $this->forgetCredentials();

    Route::get('/admin/schema-test', AdminSchemaController::class);

    $uri = Config::string('openapi.schemas.admin.route.uri');

    $this->getJson(Config::string('openapi.schemas.public.route.uri'))
        ->assertOk()
        ->assertJsonMissingPath('paths./admin~1schema-test');

    // Openapi clients read the document before they hold anything to read it with.
    $this->getJson($uri)
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('info.title', Config::string('app.name').' Admin API')
        ->assertJsonPath('paths./admin/schema-test.get.operationId', 'adminSchemaTest');

    $this->withToken(adminUser()->createToken('openapi-mcp')->plainTextToken)
        ->getJson($uri)
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('info.title', Config::string('app.name').' Admin API');

    // Reading the document is not reaching the operations it describes.
    $this->withToken(User::factory()->createOne()->createToken('openapi-mcp')->plainTextToken)
        ->getJson($uri)
        ->assertOk()
        ->assertHeader('content-type', 'application/json');

    foreach ([null, stdClass::class] as $attribute) {
        Config::set('openapi.schemas.invalid', $attribute === null ? [] : ['attribute' => $attribute]);

        expect(fn () => app(SchemaController::class)('invalid', app(Router::class)))
            ->toThrow(RuntimeException::class, 'OpenAPI schema [invalid] is not configured.');
    }

    Route::get('/missing-schema-action', AdminSchemaController::class.'@missing');

    $document = (new SchemaGenerator(
        app(Router::class),
        AdminApiSchema::class,
        ['info' => ['title' => 'Admin']],
    ))->document();

    expect($document)->toHaveKey('paths./admin/schema-test')
        ->and(data_get($document, 'paths./missing-schema-action'))->toBeNull();
});
