<?php

use App\Helpers\CacheKey;
use App\Routes\Web;

// The spec's one required section: an agent that finds anything else at this path has no
// entry point to read.
test('llms.txt is served as markdown, opening with the h1 the spec requires', function (): void {
    $TestResponse = $this->get(Web::llms->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee((string) file_get_contents(resource_path(CacheKey::llms->value)), false);

    expect($TestResponse->getContent())->toStartWith('# ');
});
