<?php

use App\Routes\Web;

test('Bing site auth XML uses the configured Microsoft content ID', function (): void {
    config(['microsoft.content_id' => 'configured-content-id']);

    $this->get(Web::bingSiteAuth->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<user>configured-content-id</user>', escape: false);
});
