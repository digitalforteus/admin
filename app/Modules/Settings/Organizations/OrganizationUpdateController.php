<?php

namespace App\Modules\Settings\Organizations;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationUpdateController
{
    public function __invoke(Request $Request, string $organization_id): RedirectResponse
    {
        $Organization = OrganizationQuery::owned(User::authenticated($Request), $organization_id);
        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($OrganizationRequest->toArray());
        }

        $Organization->update([OrganizationRequest::name => $OrganizationRequest->name]);

        return back()->with('status', 'Organization updated.');
    }
}
