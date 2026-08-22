<?php

use App\View\DataModels\Main;
use App\View\ViewName;

test('every case names a view that exists, and the layout renders through the case', function (): void {
    foreach (ViewName::cases() as $case) {
        expect($case->exists())->toBeTrue();
    }

    $view = ViewName::main->render([Main::main => []]);

    expect($view->name())->toBe(ViewName::main->value)
        ->and($view->getData()[Main::main])->toBe([]);
});
