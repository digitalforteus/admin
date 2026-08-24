<?php

namespace App\Modules\Organizations\Organizations;

use App\Helpers\MemberRole;
use App\Models\User;
use App\Modules\Contexts\Authorize;
use App\Modules\Organizations\OrganizationCreator;
use App\Routes\ContextRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Enterprise = Authorize::enterprise(MemberRole::admin);

        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($OrganizationRequest->toArray());
        }

        $Organization = OrganizationCreator::create(
            $Enterprise,
            $OrganizationRequest->name,
            User::authenticated($Request),
        );

        return redirect()
            ->to(ContextRoute::organization->url(ContextRoute::parameters([
                ContextRoute::organizationParameter => $Organization->slug,
            ])))
            ->with('status', 'Organization created.');
    }
}
