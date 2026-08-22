<?php

use App\AppConfig;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use App\Routes\RouteIndex;
use App\Routes\Web;
use Tests\Fixtures\RouteIndexStub;

// A case of the registry is the whole of registering an index: an enum it does not name
// is not one, wherever that enum lives. The registry is read rather than discovered, so
// the order it declares its cases in is the order the indexes come back in.
test('the cases of the registry are the indexes, in the order it declares them', function (): void {
    expect(AppConfig::routeIndexes())
        ->toContain(Admin::class, ApiRoute::class, Auth::class, Web::class)
        ->and(AppConfig::routeIndexes())
        ->not->toContain(MiddlewareTag::class, RouteIndexStub::class)
        ->and(AppConfig::routeIndexes())->toBe(array_column(RouteIndex::cases(), 'value'));

    foreach (AppConfig::routeIndexes() as $enum) {
        expect(enum_exists($enum))->toBeTrue()
            ->and(new ReflectionEnum($enum)->getBackingType()?->getName())->toBe('string');
    }
});
