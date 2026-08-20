<?php

use App\Routes\Web;
use App\View\DataModels\DocsNav;
use App\View\DataModels\NavItem;
use App\View\ViewDirectory;
use Illuminate\Http\Request;

test('the first entry is the API documentation', function (): void {
    $items = DocsNav::items();

    expect($items[0]->label)->toBe('API')
        ->and($items[0]->route)->toBe(Web::docsApi);
});

test('the MCP documentation is listed', function (): void {
    expect(collect(DocsNav::items())->pluck('route')->all())->toContain(Web::docsMcp);
});

test('every case describes one navigation item', function (): void {
    foreach (DocsNav::cases() as $DocsNav) {
        expect($DocsNav->item())->toBeInstanceOf(NavItem::class);
    }
});

test('a docs navigation case must describe an item with named attributes', function (mixed $item, string $message): void {
    expect(static fn (): mixed => new ReflectionMethod(DocsNav::class, 'attributes')->invoke(null, $item))
        ->toThrow(LogicException::class, $message);
})->with([
    'missing item' => [null, 'Documentation navigation cases must describe a navigation item.'],
    'positional attribute' => [[Web::docsApi], 'Documentation navigation attributes must be named.'],
]);

test('every entry names an icon that exists', function (): void {
    foreach (DocsNav::items() as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }
});

test('the navigation is visible on the docs path', function (): void {
    app()->instance('request', Request::create(Web::docs->value));

    expect(DocsNav::visible())->toBeTrue();
});

test('the navigation is visible on the docs API path', function (): void {
    app()->instance('request', Request::create(Web::docsApi->value));

    expect(DocsNav::visible())->toBeTrue();
});

test('the navigation is visible on the docs MCP path', function (): void {
    app()->instance('request', Request::create(Web::docsMcp->value));

    expect(DocsNav::visible())->toBeTrue();
});

test('the navigation is hidden on other paths', function (): void {
    app()->instance('request', Request::create(Web::home->value));

    expect(DocsNav::visible())->toBeFalse();
});
