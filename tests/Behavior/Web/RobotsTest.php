<?php

use App\Helpers\CacheKey;
use App\Routes\Web;

// A robots.txt without a User-agent line binds no crawler to any of its rules.
test('robots.txt is served as plain text, opening with a User-agent group', function (): void {
    $TestResponse = $this->get(Web::robots->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee((string) file_get_contents(resource_path(CacheKey::robots->value)), false);

    expect($TestResponse->getContent())->toStartWith('User-agent: ');
});
