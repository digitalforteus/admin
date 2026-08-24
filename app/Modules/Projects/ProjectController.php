<?php

namespace App\Modules\Projects;

use App\Helpers\MemberRole;
use App\Helpers\Slug;
use App\Models\Project;
use App\Models\User;
use App\Modules\Contexts\Authorize;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProjectController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::admin);

        $ProjectRequest = ProjectRequest::from($Request->all());
        $Validator = Validator::make(...$ProjectRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($ProjectRequest->toArray());
        }

        $Project = Project::query()->create([
            Projects::organization_id->value => $Organization->id,
            Projects::name->value => $ProjectRequest->name,
            Projects::slug->value => Slug::unique(
                Project::class,
                Projects::slug->value,
                $ProjectRequest->name,
                [Projects::organization_id->value => $Organization->id],
            ),
            Projects::created_by->value => User::authenticated($Request)->id,
        ]);

        return redirect()
            ->to(ContextRoute::project->url(ContextRoute::parameters([
                ContextRoute::projectParameter => $Project->slug,
            ])))
            ->with('status', 'Project created.');
    }
}
