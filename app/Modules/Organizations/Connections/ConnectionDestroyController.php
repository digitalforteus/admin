<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use App\Routes\OrganizationRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionDestroyController
{
    public function __invoke(Request $Request, string $organization, string $project, string $connection): RedirectResponse
    {
        $Organization = Authorize::owns($Request);

        ConnectionQuery::find($Organization, $connection)->delete();

        return redirect()
            ->to(OrganizationRoute::connections->url([
                OrganizationRoute::organizationParameter => $Organization->slug,
                OrganizationRoute::projectParameter => $project,
            ]))
            ->with('status', 'Connection deleted.');
    }
}
