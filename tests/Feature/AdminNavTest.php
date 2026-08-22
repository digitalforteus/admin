<?php

use App\Routes\Admin;
use App\View\DataModels\AdminNav;
use App\View\ViewDirectory;

test('the rail leads with the dashboard, lists the users and sessions pages, and names icons that exist', function (): void {
    $items = AdminNav::items();

    expect($items[0]->label)->toBe('Dashboard')
        ->and($items[0]->route)->toBe(Admin::index)
        ->and(collect($items)->pluck('route')->all())->toContain(Admin::users)
        ->and(collect($items)->pluck('route')->all())->toContain(Admin::sessions);

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }
});
