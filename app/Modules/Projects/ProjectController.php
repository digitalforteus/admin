<?php

namespace App\Modules\Projects;

use App\Helpers\Slug;
use App\Models\Project;
use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProjectController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

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
            ->to(OrganizationRoute::project->url([
                OrganizationRoute::organizationParameter => $Organization->slug,
                OrganizationRoute::projectParameter => $Project->slug,
            ]))
            ->with('status', 'Project created.');
    }
}
