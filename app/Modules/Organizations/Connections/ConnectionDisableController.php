<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\MemberRole;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Contexts\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionDisableController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $project, string $connection): RedirectResponse
    {
        $Project = Authorize::project(MemberRole::admin);

        ConnectionQuery::disable($Project, ConnectionQuery::find($Project->organization, $connection));

        return back()->with('status', 'Connection disabled.');
    }
}
