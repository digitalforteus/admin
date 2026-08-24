<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use App\Modules\Projects\ProjectQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionDisableController
{
    public function __invoke(Request $Request, string $organization, string $project, string $connection): RedirectResponse
    {
        $Organization = Authorize::manages($Request);
        $Project = ProjectQuery::find($Organization, $project);

        ConnectionQuery::disable($Project, ConnectionQuery::find($Organization, $connection));

        return back()->with('status', 'Connection disabled.');
    }
}
