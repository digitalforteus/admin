<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionEnableController
{
    public function __invoke(Request $Request, string $organization, string $connection): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

        ConnectionQuery::enable($Organization, ConnectionQuery::find($Organization, $connection));

        return back()->with('status', 'Connection enabled.');
    }
}
