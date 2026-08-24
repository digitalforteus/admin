<?php

namespace App\Modules\Projects;

use App\Helpers\Depth;
use App\Helpers\Directory;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Modules\Contexts\Authorize;
use App\Modules\Memberships\MembershipQuery;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProjectDestroyController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Project = Authorize::project(MemberRole::admin);
        $parameters = ContextRoute::parameters();

        Picture::of($Project, Projects::icon, Directory::project_icons)->clear();
        MembershipQuery::purge(Depth::project, $Project);

        $Project->delete();

        unset($parameters[ContextRoute::projectParameter]);

        return redirect()
            ->to(ContextRoute::organization->url($parameters))
            ->with('status', 'Project deleted.');
    }
}
