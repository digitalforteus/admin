<?php

namespace App\Modules\Organizations\Organizations;

use App\Helpers\MemberRole;
use App\Modules\Contexts\Authorize;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationUpdateController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);

        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        $Organization->update([Organizations::name->value => $OrganizationRequest->name]);

        return back()->with('status', 'Organization updated.');
    }
}
