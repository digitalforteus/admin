<?php

namespace App\Modules\Projects;

use App\Modules\Organizations\Authorize;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProjectUpdateController
{
    public function __invoke(Request $Request, string $organization, string $project): RedirectResponse
    {
        $Project = ProjectQuery::find(Authorize::manages($Request), $project);

        $ProjectRequest = ProjectRequest::from($Request->all());
        $Validator = Validator::make(...$ProjectRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        $Project->update([Projects::name->value => $ProjectRequest->name]);

        return back()->with('status', 'Project updated.');
    }
}
