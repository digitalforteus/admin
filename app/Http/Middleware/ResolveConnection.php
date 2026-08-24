<?php

namespace App\Http\Middleware;

use App\Models\Connection;
use App\Models\Project;
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
        $Project = OrganizationContext::project();

        if (! is_string($slug) || $slug === '' || ! $Project instanceof Project) {
            return $Closure($Request);
        }

        $Connection = ConnectionQuery::bySlug($Project, $slug);

        if ($Connection === null) {
            return redirect(OrganizationRoute::project->url([
                OrganizationRoute::organizationParameter => $Project->organization->slug,
                OrganizationRoute::projectParameter => $Project->slug,
            ]));
        }

        OrganizationContext::bindConnection($Request, $Connection);

        return $Closure($Request);
    }
}
