<?php

namespace App\Modules\Settings\Organizations;

use App\Models\User;
use App\Modules\Enterprises\EnterpriseQuery;
use App\Modules\Organizations\OrganizationCreator;
use App\Routes\OrganizationRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationController
{
    public function __invoke(Request $Request, string $enterprise): RedirectResponse
    {
        $User = User::authenticated($Request);
        $Enterprise = EnterpriseQuery::bySlug($User, $enterprise);

        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($OrganizationRequest->toArray());
        }

        $Organization = OrganizationCreator::create($Enterprise, $OrganizationRequest->name, $User);

        return redirect()
            ->to(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => $Organization->slug]))
            ->with('status', 'Organization created.');
    }
}
