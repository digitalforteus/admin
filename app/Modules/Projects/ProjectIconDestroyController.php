<?php

namespace App\Modules\Projects;

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Organizations\Authorize;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProjectIconDestroyController
{
    public function __invoke(Request $Request, string $organization, string $project): RedirectResponse
    {
        $Project = ProjectQuery::find(Authorize::manages($Request), $project);

        Picture::of($Project, Projects::icon, Directory::project_icons)->clear();

        return back()->with('status', 'Project icon removed.');
    }
}
