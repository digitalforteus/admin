<?php

use App\Routes\ApiRoute;
use App\Routes\Web;
use Illuminate\Support\Facades\Config;

test('the document is served where the enum case says, and describes every api endpoint', function (): void {
    expect(Web::openapi->value)->toBe('/'.ltrim(Config::string('openapi.schemas.public.route.uri'), '/'));

    $paths = $this->getJson(Config::string('openapi.schemas.public.route.uri'))
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonStructure(['info', 'paths'])
        ->json('paths');

    foreach (ApiRoute::cases() as $ApiRoute) {
        expect($paths)->toHaveKey($ApiRoute->value);
    }
});
