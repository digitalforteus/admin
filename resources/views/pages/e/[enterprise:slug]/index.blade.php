<?php

use App\Helpers\SvgName;
use App\Routes\EnterpriseRoute;
use App\View\DataModels\Avatar;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Enterprise')
    ->description('The organizations this enterprise holds.')
    ->hiddenFromRobots();
?>
@php
    use App\Models\User;
    use App\Modules\Enterprises\EnterpriseContext;
    use App\Modules\Enterprises\EnterpriseQuery;
    use App\Routes\OrganizationRoute;

    $User = User::authenticated(request());
    $Enterprise = EnterpriseContext::enterprise();
    $Organizations = EnterpriseQuery::organizations($Enterprise, $User);
    $manages = EnterpriseQuery::manages($Enterprise, $User);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Enterprise->name, ContextCard::title => 'Organizations']">
    <x-status-toast/>
    <div class="mt-6 flex flex-wrap gap-2">
        <a class="btn btn-primary btn-sm" data-enterprise-organization-add
           href="{{EnterpriseRoute::organizationCreate->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug])}}">Add organization</a>
        @if($manages)
            <a class="btn btn-sm" data-enterprise-settings
               href="{{EnterpriseRoute::settings->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug])}}">Enterprise settings</a>
        @endif
    </div>
    <ul class="mt-6 grid grid-cols-1 gap-2 lg:grid-cols-2">
        @forelse($Organizations as $Organization)
            <li data-enterprise-organization>
                <a class="flex items-center gap-3 rounded-box border border-base-300 p-3"
                   href="{{OrganizationRoute::index->url([OrganizationRoute::organizationParameter => $Organization->slug])}}">
                    <x-avatar :avatar="[Avatar::name => $Organization->name, Avatar::picture => $Organization->iconUrl(), Avatar::size => 'w-8', Avatar::fallback => SvgName::building]"/>
                    <span class="truncate font-medium" title="{{$Organization->name}}">{{$Organization->name}}</span>
                </a>
            </li>
        @empty
            <li data-enterprise-organizations-empty class="text-base-content/70">No organizations yet.</li>
        @endforelse
    </ul>
</x-context-card>
