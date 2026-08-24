<?php

namespace App\Http\Middleware;

use App\Models\Enterprise;
use App\Models\User;
use App\Modules\Enterprises\EnterpriseContext;
use App\Modules\Enterprises\EnterpriseQuery;
use App\Routes\EnterpriseRoute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ResolveEnterprise
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $parameter = $Request->route(EnterpriseRoute::enterpriseParameter);
        $slug = $parameter instanceof Enterprise ? $parameter->slug : $parameter;

        if (! is_string($slug) || $slug === '') {
            return $Closure($Request);
        }

        EnterpriseContext::bind($Request, EnterpriseQuery::bySlug(User::authenticated($Request), $slug));

        return $Closure($Request);
    }
}
