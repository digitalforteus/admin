<?php

use App\Routes\Web;
use App\View\DataModels\DocsNav;
use App\View\DataModels\NavItem;
use App\View\ViewDirectory;
use Illuminate\Http\Request;

test('every case describes one named navigation item, leading with the API and listing MCP', function (): void {
    $items = DocsNav::items();

    expect($items[0]->label)->toBe('API')
        ->and($items[0]->route)->toBe(Web::docsApi)
        ->and(collect($items)->pluck('route')->all())->toContain(Web::docsMcp);

    foreach (DocsNav::cases() as $DocsNav) {
        expect($DocsNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }

    foreach ([
        [null, 'Documentation navigation cases must describe a navigation item.'],
        [[Web::docsApi], 'Documentation navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(DocsNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }
});

test('the navigation is visible on every docs path and hidden on the others', function (): void {
    foreach ([Web::docs, Web::docsApi, Web::docsMcp] as $Web) {
        app()->instance('request', Request::create($Web->value));

        expect(DocsNav::visible())->toBeTrue();
    }

    app()->instance('request', Request::create(Web::home->value));

    expect(DocsNav::visible())->toBeFalse();
});
