<?php

use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\OrganizationRoute;
use App\View\DataModels\OrganizationCard;
use Laravel\Head\Facades\Head;

Head::title('Organization')
    ->description('The organization this account is working in.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Projects\ProjectQuery;

    $Organization = OrganizationContext::organization();
    $Projects = ProjectQuery::forOrganization($Organization);
    $Role = MembershipQuery::role($Organization, request()->user());
    $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'Overview',
]">
    <x-status-toast/>
    <dl class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Enterprise</dt>
            <dd class="mt-1 font-medium" title="{{$Organization->enterprise->name}}">{{$Organization->enterprise->name}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Your role</dt>
            <dd class="mt-1 font-medium" title="{{$Role?->value}}">{{$Role?->label() ?? '—'}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Projects</dt>
            <dd class="mt-1 font-medium" title="{{count($Projects)}}">{{count($Projects)}}</dd>
        </div>
    </dl>
    <div class="mt-6 flex flex-wrap gap-2">
        <a class="btn btn-sm" href="{{OrganizationRoute::projects->url($parameters)}}">Projects</a>
        <a class="btn btn-sm" href="{{OrganizationRoute::members->url($parameters)}}">Members</a>
    </div>
</x-organization-card>
