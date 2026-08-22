<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrganizationDestroyController
{
    public function __invoke(Request $Request, string $organization_id): RedirectResponse
    {
        $Organization = OrganizationQuery::find($organization_id);

        Picture::of($Organization, Organizations::icon, Directory::organization_icons)->clear();
        $Organization->delete();

        return back()->with('status', 'Organization deleted.');
    }
}
