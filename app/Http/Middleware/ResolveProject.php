<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Project;
use App\Modules\Organizations\OrganizationContext;
use App\Modules\Projects\ProjectQuery;
use App\Routes\OrganizationRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ResolveProject
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $parameter = $Request->route(OrganizationRoute::projectParameter);
        $slug = $parameter instanceof Project ? $parameter->slug : $parameter;
        $Organization = OrganizationContext::organization();

        if (! is_string($slug) || $slug === '' || ! $Organization instanceof Organization) {
            return $Closure($Request);
        }

        $Project = ProjectQuery::bySlug($Organization, $slug);

        if ($Project === null) {
            return redirect(OrganizationRoute::projects->url([
                OrganizationRoute::organizationParameter => $Organization->slug,
            ]));
        }

        OrganizationContext::bindProject($Request, $Project);

        return $Closure($Request);
    }
}
