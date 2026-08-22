<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionDisableController
{
    public function __invoke(Request $Request, string $organization, string $connection): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

        ConnectionQuery::disable($Organization, ConnectionQuery::find($Organization, $connection));

        return back()->with('status', 'Connection disabled.');
    }
}
