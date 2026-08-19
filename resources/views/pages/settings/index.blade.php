<?php

use App\Routes\Auth;
use Laravel\Head\Facades\Head;

use function Laravel\Folio\render;

Head::title('Settings')
    ->hiddenFromRobots();

render(function () {
    return redirect(Auth::settingsProfile->value);
});
