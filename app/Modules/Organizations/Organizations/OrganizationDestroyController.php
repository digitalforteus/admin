<?php

namespace App\Modules\Organizations\Organizations;

use App\Helpers\Depth;
use App\Helpers\Directory;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Modules\Contexts\Authorize;
use App\Modules\Memberships\MembershipQuery;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrganizationDestroyController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);
        $parameters = ContextRoute::parameters();

        Picture::of($Organization, Organizations::icon, Directory::organization_icons)->clear();

        MembershipQuery::purgeMany(
            Depth::project,
            array_values(array_filter(
                $Organization->projects()->pluck(Projects::id->value)->all(),
                static fn (mixed $id): bool => is_string($id),
            )),
        );

        MembershipQuery::purge(Depth::organization, $Organization);

        $Organization->delete();

        unset($parameters[ContextRoute::organizationParameter]);

        return redirect()
            ->to(ContextRoute::enterprise->url($parameters))
            ->with('status', 'Organization deleted.');
    }
}
