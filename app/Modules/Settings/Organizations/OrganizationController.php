<?php

namespace App\Modules\Settings\Organizations;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($OrganizationRequest->toArray());
        }

        Organization::query()->create([
            OrganizationRequest::name => $OrganizationRequest->name,
        ]);

        return back()->with('status', 'Organization created.');
    }
}
