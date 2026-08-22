<?php

namespace App\Http\Middleware;

use App\Models\Connection;
use App\Models\Organization;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\OrganizationRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ResolveConnection
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $parameter = $Request->route(OrganizationRoute::connectionParameter);
        $slug = $parameter instanceof Connection ? $parameter->slug : $parameter;
        $Organization = OrganizationContext::organization();

        if (!is_string($slug) || $slug === '' || !$Organization instanceof Organization) {
            return $Closure($Request);
        }

        $Connection = ConnectionQuery::bySlug($Organization, $slug);

        if ($Connection === null) {
            return redirect(OrganizationRoute::index->url([
                OrganizationRoute::organizationParameter => $Organization->slug,
            ]));
        }

        OrganizationContext::bindConnection($Request, $Connection);

        return $Closure($Request);
    }
}
