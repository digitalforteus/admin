<?php

use App\Helpers\CacheKey;
use App\Modules\Api\Public\Readme\ReadmeResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\ApiRoute;
use App\Routes\Web;

test('the readme is served without a token, pointing to the current API contract', function (): void {
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
});
