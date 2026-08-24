<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\MemberRole;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Contexts\Authorize;
use App\Routes\ContextRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionDestroyController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $project, string $connection): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);
        $parameters = ContextRoute::parameters();

        ConnectionQuery::find($Organization, $connection)->delete();

        unset($parameters[ContextRoute::connectionParameter]);

        return redirect()
            ->to(ContextRoute::connectionIndex->url($parameters))
            ->with('status', 'Connection deleted.');
    }
}
