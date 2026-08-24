<?php

use App\Helpers\SvgName;
use App\Routes\OrganizationRoute;
use App\View\DataModels\Avatar;
use App\View\DataModels\OrganizationCard;
use Laravel\Head\Facades\Head;

Head::title('Projects')
    ->description('The projects this organization holds.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Organizations\MembershipQuery;
    use App\Modules\Organizations\OrganizationContext;
    use App\Modules\Projects\ProjectQuery;

    $Organization = OrganizationContext::organization();
    $Projects = ProjectQuery::forOrganization($Organization);
    $manages = MembershipQuery::role($Organization, request()->user())?->manages() ?? false;
    $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'Projects',
]">
    <x-status-toast/>
    @if($manages)
        <div class="mt-6">
            <a class="btn btn-primary btn-sm" data-project-add
               href="{{OrganizationRoute::projectCreate->url($parameters)}}">Add project</a>
        </div>
    @endif
    <ul class="mt-6 grid grid-cols-1 gap-2 lg:grid-cols-2">
        @forelse($Projects as $Project)
            <li data-project-row>
                <a class="flex items-center gap-3 rounded-box border border-base-300 p-3"
                   href="{{OrganizationRoute::project->url([...$parameters, OrganizationRoute::projectParameter => $Project->slug])}}">
                    <x-avatar :avatar="[
                        Avatar::name => $Project->name,
                        Avatar::picture => $Project->iconUrl(),
                        Avatar::size => 'w-8',
                        Avatar::fallback => SvgName::folder,
                    ]"/>
                    <span class="truncate font-medium" title="{{$Project->name}}">{{$Project->name}}</span>
                </a>
            </li>
        @empty
            <li data-projects-empty class="text-base-content/70">No projects yet.</li>
        @endforelse
    </ul>
</x-organization-card>
