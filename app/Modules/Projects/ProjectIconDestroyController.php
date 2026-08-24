<?php

namespace App\Modules\Projects;

use App\Helpers\Directory;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Modules\Contexts\Authorize;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProjectIconDestroyController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Project = Authorize::project(MemberRole::admin);

        Picture::of($Project, Projects::icon, Directory::project_icons)->clear();

        return back()->with('status', 'Project icon removed.');
    }
}
