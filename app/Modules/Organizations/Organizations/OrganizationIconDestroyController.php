<?php

namespace App\Modules\Organizations\Organizations;

use App\Helpers\Directory;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Modules\Contexts\Authorize;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrganizationIconDestroyController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);

        Picture::of($Organization, Organizations::icon, Directory::organization_icons)->clear();

        return back()->with('status', 'Organization icon removed.');
    }
}
