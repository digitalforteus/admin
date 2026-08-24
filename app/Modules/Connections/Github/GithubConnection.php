<?php

namespace App\Modules\Connections\Github;

use App\Helpers\Rule;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Project;
use App\Modules\Connections\ConnectionPlugin;
use App\Routes\OrganizationRoute;
use App\View\DataModels\NavItem;
use App\View\DataModels\RunsTable;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;

readonly class GithubConnection implements ConnectionPlugin
{
    public function label(): string
    {
        return 'GitHub';
    }

    public function icon(): SvgName
    {
        return SvgName::github;
    }

    /** @return list<array<string, mixed>> */
    public function form(): array
    {
        return [
            GithubForm::textInput(GithubForm::token),
            GithubForm::textInput(GithubForm::owner),
            GithubForm::textInput(GithubForm::repo),
        ];
    }

    /** @return list<string> */
    public function secrets(): array
    {
        return [GithubForm::token];
    }

    /** @param  array<string, mixed>  $fields */
    public function validate(array $fields): ValidatorContract
    {
        return Validator::make($fields, [
            GithubForm::token => [Rule::required->value, Rule::string->value, Rule::max(255)],
            GithubForm::owner => [Rule::required->value, Rule::string->value, Rule::max(255)],
            GithubForm::repo => [Rule::required->value, Rule::string->value, Rule::max(255)],
        ]);
    }

    public function verify(Connection $Connection): bool
    {
        return GithubQuery::runs($Connection, 1)->ok;
    }

    public function page(Project $Project, Connection $Connection): View
    {
        $page = max(1, request()->integer(RunsTable::page, 1));
        $Runs = GithubQuery::runs($Connection, $page);

        return view('Github.page', [
            'runsTable' => [
                RunsTable::organization => $Project->organization->slug,
                RunsTable::project => $Project->slug,
                RunsTable::connection => $Connection->slug,
                RunsTable::ok => $Runs->ok,
                RunsTable::status => $Runs->status,
                RunsTable::total => $Runs->total,
                RunsTable::page => $page,
                RunsTable::runs => $Runs->rows,
            ],
        ]);
    }

    /** @return list<NavItem> */
    public function navItems(Project $Project, Connection $Connection): array
    {
        return [
            NavItem::from([
                NavItem::label => 'Workflow Runs',
                NavItem::icon => $this->icon(),
                NavItem::route => OrganizationRoute::connection,
                NavItem::parameters => [
                    OrganizationRoute::organizationParameter => $Project->organization->slug,
                    OrganizationRoute::projectParameter => $Project->slug,
                    OrganizationRoute::connectionParameter => $Connection->slug,
                ],
            ]),
        ];
    }
}
