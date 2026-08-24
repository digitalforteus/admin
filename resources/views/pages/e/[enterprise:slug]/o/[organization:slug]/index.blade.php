<?php

use App\Helpers\SvgName;
use App\Routes\ContextRoute;
use App\View\DataModels\Avatar;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Organization')
    ->description('The projects this organization holds.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Models\User;
    use App\Modules\Contexts\Context;
    use App\Modules\Contexts\DepthQuery;

    $Organization = Context::organization();
    $Projects = DepthQuery::children(Depth::project, $Organization, User::authenticated(request()));
    $Role = Context::role(Depth::organization);
    $parameters = ContextRoute::parameters();
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Organization->name, ContextCard::title => 'Projects']">
    <x-status-toast/>
    <div class="mt-6 flex flex-wrap gap-2">
        @if($Role?->manages())
            <a class="btn btn-primary btn-sm" data-project-add
               href="{{ContextRoute::projectCreate->url($parameters)}}">Add project</a>
        @endif
        <a class="btn btn-sm" href="{{ContextRoute::members->url($parameters)}}">Members</a>
        @if($Role === MemberRole::owner)
            <a class="btn btn-sm" data-organization-settings
               href="{{ContextRoute::organizationSettings->url($parameters)}}">Organization settings</a>
        @endif
    </div>
    <ul class="mt-6 grid grid-cols-1 gap-2 lg:grid-cols-2">
        @forelse($Projects as $Project)
            <li data-project-row>
                <a class="flex items-center gap-3 rounded-box border border-base-300 p-3"
                   href="{{ContextRoute::project->url([...$parameters, ContextRoute::projectParameter => $Project->slug])}}">
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
</x-context-card>
