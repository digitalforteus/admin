<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\Picture;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationIconController
{
    public function __invoke(Request $Request, string $organization_id): RedirectResponse
    {
        if (! Disk::retains()) {
            return back()->withErrors([
                OrganizationIconRequest::icon => 'Uploading an icon needs a storage service that keeps it.',
            ]);
        }

        $OrganizationIconRequest = OrganizationIconRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationIconRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        $Organization = OrganizationQuery::find($organization_id);

        Picture::of($Organization, Organizations::icon, Directory::organization_icons)->put($OrganizationIconRequest->icon);

        return back()->with('status', 'Organization icon updated.');
    }
}
