<?php

use App\Routes\OrganizationRoute;
use App\View\DataModels\OrganizationCard;
use Laravel\Head\Facades\Head;

Head::title('Project')
    ->description('The project this account is working in.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Organizations\MembershipQuery;
    use App\Modules\Organizations\OrganizationContext;

    $Organization = OrganizationContext::organization();
    $Project = OrganizationContext::project();
    $manages = MembershipQuery::role($Organization, request()->user())?->manages() ?? false;
    $parameters = [
        OrganizationRoute::organizationParameter => $Organization->slug,
        OrganizationRoute::projectParameter => $Project->slug,
    ];
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Project->name,
    OrganizationCard::title => 'Overview',
]">
    <x-status-toast/>
    <dl class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Organization</dt>
            <dd class="mt-1 font-medium" title="{{$Organization->name}}">{{$Organization->name}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Enterprise</dt>
            <dd class="mt-1 font-medium" title="{{$Organization->enterprise->name}}">{{$Organization->enterprise->name}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Created by</dt>
            <dd class="mt-1 font-medium" title="{{$Project->creator?->name}}">{{$Project->creator?->name ?? '—'}}</dd>
        </div>
    </dl>
    @if($manages)
        <div class="mt-6 flex flex-wrap gap-2">
            <a class="btn btn-sm" data-project-settings
               href="{{OrganizationRoute::projectSettings->url($parameters)}}">Project settings</a>
        </div>
    @endif
</x-organization-card>
