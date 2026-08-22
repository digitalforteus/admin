<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Models\User;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrganizationIconDestroyController
{
    public function __invoke(Request $Request, string $organization_id): RedirectResponse
    {
        Picture::of(OrganizationQuery::find(User::authenticated($Request), $organization_id), Organizations::icon, Directory::organization_icons)->clear();

        return back()->with('status', 'Organization icon removed.');
    }
}
