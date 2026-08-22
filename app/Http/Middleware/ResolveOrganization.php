<?php

namespace App\Http\Middleware;

use App\Helpers\SessionKey;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\OrganizationContext;
use App\Modules\Settings\Organizations\OrganizationQuery;
use App\Routes\OrganizationRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ResolveOrganization
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $parameter = $Request->route(OrganizationRoute::organizationParameter);
        $slug = $parameter instanceof Organization ? $parameter->slug : $parameter;

        if (! is_string($slug) || $slug === '') {
            return $Closure($Request);
        }

        $Organization = OrganizationQuery::bySlug(User::authenticated($Request), $slug);

        OrganizationContext::bind($Request, $Organization);

        $Request->session()->put(SessionKey::organization->value, $Organization->slug);

        return $Closure($Request);
    }
}
