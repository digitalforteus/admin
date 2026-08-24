<?php

namespace App\Modules\Projects;

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Modules\Contexts\Authorize;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProjectIconController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Project = Authorize::project(MemberRole::admin);

        if (! Disk::retains()) {
            return back()->withErrors([
                ProjectIconRequest::icon => 'Uploading an icon needs a storage service that keeps it.',
            ]);
        }

        $ProjectIconRequest = ProjectIconRequest::from($Request->all());
        $Validator = Validator::make(...$ProjectIconRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        Picture::of($Project, Projects::icon, Directory::project_icons)->put($ProjectIconRequest->icon);

        return back()->with('status', 'Project icon updated.');
    }
}
