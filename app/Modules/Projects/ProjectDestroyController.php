<?php

namespace App\Modules\Projects;

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Organizations\Authorize;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProjectDestroyController
{
    public function __invoke(Request $Request, string $organization, string $project): RedirectResponse
    {
        $Organization = Authorize::manages($Request);
        $Project = ProjectQuery::find($Organization, $project);

        Picture::of($Project, Projects::icon, Directory::project_icons)->clear();

        $Project->delete();

        return redirect()
            ->to(OrganizationRoute::projects->url([
                OrganizationRoute::organizationParameter => $Organization->slug,
            ]))
            ->with('status', 'Project deleted.');
    }
}
